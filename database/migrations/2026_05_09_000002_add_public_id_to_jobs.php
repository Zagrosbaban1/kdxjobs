<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('jobs', 'public_id')) {
            Schema::table('jobs', function (Blueprint $table): void {
                $table->string('public_id', 48)->nullable()->after('id')->unique();
            });
        }

        DB::table('jobs')
            ->whereNull('public_id')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($jobs): void {
                foreach ($jobs as $job) {
                    do {
                        $publicId = 'job_' . bin2hex(random_bytes(12));
                    } while (DB::table('jobs')->where('public_id', $publicId)->exists());

                    DB::table('jobs')
                        ->where('id', $job->id)
                        ->update(['public_id' => $publicId]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('jobs', 'public_id')) {
            Schema::table('jobs', function (Blueprint $table): void {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            });
        }
    }
};
