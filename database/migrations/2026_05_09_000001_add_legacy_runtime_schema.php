<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saved_searches')) {
            Schema::create('saved_searches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('signature', 64);
                $table->string('query_text', 255)->nullable();
                $table->string('types', 255)->nullable();
                $table->string('locations', 255)->nullable();
                $table->string('industries', 255)->nullable();
                $table->string('tags', 255)->nullable();
                $table->string('statuses', 120)->nullable();
                $table->string('deadline_filter', 40)->nullable();
                $table->integer('min_salary')->default(0);
                $table->integer('max_salary')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['user_id', 'signature'], 'saved_search_user_signature');
            });
        }
        $this->addColumnIfMissing('saved_searches', 'statuses', fn(Blueprint $table) => $table->string('statuses', 120)->nullable()->after('tags'));
        $this->addColumnIfMissing('saved_searches', 'deadline_filter', fn(Blueprint $table) => $table->string('deadline_filter', 40)->nullable()->after('statuses'));

        if (!Schema::hasTable('contact_messages')) {
            Schema::create('contact_messages', function (Blueprint $table): void {
                $table->id();
                $table->string('full_name', 160);
                $table->string('email', 180);
                $table->string('subject', 180);
                $table->text('message');
                $table->boolean('is_read')->default(false);
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table): void {
                $table->string('setting_key', 80)->primary();
                $table->text('setting_value')->nullable();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }

        $this->addColumnIfMissing('users', 'cv_text', fn(Blueprint $table) => $table->mediumText('cv_text')->nullable()->after('cv_file'));
        $this->addColumnIfMissing('users', 'cv_ai_skills', fn(Blueprint $table) => $table->text('cv_ai_skills')->nullable()->after('cv_text'));
        $this->addColumnIfMissing('users', 'cv_ai_years', fn(Blueprint $table) => $table->integer('cv_ai_years')->nullable()->after('cv_ai_skills'));
        $this->addColumnIfMissing('users', 'cv_ai_summary', fn(Blueprint $table) => $table->text('cv_ai_summary')->nullable()->after('cv_ai_years'));
        $this->addColumnIfMissing('users', 'cv_ai_json', fn(Blueprint $table) => $table->json('cv_ai_json')->nullable()->after('cv_ai_summary'));
        $this->addColumnIfMissing('users', 'cv_ai_updated_at', fn(Blueprint $table) => $table->timestamp('cv_ai_updated_at')->nullable()->after('cv_ai_json'));

        $this->addColumnIfMissing('applications', 'cv_ai_skills', fn(Blueprint $table) => $table->text('cv_ai_skills')->nullable()->after('cv_file'));
        $this->addColumnIfMissing('applications', 'cv_ai_years', fn(Blueprint $table) => $table->integer('cv_ai_years')->nullable()->after('cv_ai_skills'));
        $this->addColumnIfMissing('applications', 'cv_ai_summary', fn(Blueprint $table) => $table->text('cv_ai_summary')->nullable()->after('cv_ai_years'));
        $this->addColumnIfMissing('applications', 'cv_ai_json', fn(Blueprint $table) => $table->json('cv_ai_json')->nullable()->after('cv_ai_summary'));
        $this->addColumnIfMissing('applications', 'cv_text', fn(Blueprint $table) => $table->mediumText('cv_text')->nullable()->after('cv_ai_json'));
        $this->addColumnIfMissing('applications', 'ai_match_score', fn(Blueprint $table) => $table->integer('ai_match_score')->nullable()->after('cv_text'));
        $this->addColumnIfMissing('applications', 'ai_match_fit', fn(Blueprint $table) => $table->string('ai_match_fit', 40)->nullable()->after('ai_match_score'));
        $this->addColumnIfMissing('applications', 'ai_match_summary', fn(Blueprint $table) => $table->text('ai_match_summary')->nullable()->after('ai_match_fit'));
        $this->addColumnIfMissing('applications', 'ai_match_json', fn(Blueprint $table) => $table->json('ai_match_json')->nullable()->after('ai_match_summary'));
        $this->addColumnIfMissing('applications', 'ai_match_updated_at', fn(Blueprint $table) => $table->timestamp('ai_match_updated_at')->nullable()->after('ai_match_json'));

        DB::table('app_settings')->insertOrIgnore([
            ['setting_key' => 'ai_matching_mode', 'setting_value' => 'balanced'],
            ['setting_key' => 'email_notifications_enabled', 'setting_value' => '1'],
        ]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn([
                'cv_ai_skills',
                'cv_ai_years',
                'cv_ai_summary',
                'cv_ai_json',
                'cv_text',
                'ai_match_score',
                'ai_match_fit',
                'ai_match_summary',
                'ai_match_json',
                'ai_match_updated_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'cv_text',
                'cv_ai_skills',
                'cv_ai_years',
                'cv_ai_summary',
                'cv_ai_json',
                'cv_ai_updated_at',
            ]);
        });

        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('saved_searches');
    }

    private function addColumnIfMissing(string $tableName, string $columnName, Closure $callback): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, $callback);
    }
};
