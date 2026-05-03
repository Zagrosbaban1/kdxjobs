<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['jobseeker', 'company', 'admin', 'superadmin'])->default('jobseeker');
            $table->string('full_name', 160)->nullable();
            $table->string('company_name', 180)->nullable();
            $table->string('email', 180)->unique();
            $table->string('phone', 60)->nullable();
            $table->string('password_hash', 255);
            $table->text('skills')->nullable();
            $table->string('industry', 160)->nullable();
            $table->string('location', 180)->nullable();
            $table->string('cv_file', 255)->nullable();
            $table->string('logo_file', 255)->nullable();
            $table->string('profile_photo', 255)->nullable();
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 180);
            $table->string('industry', 160);
            $table->string('location', 180)->default('Remote');
            $table->string('logo_file', 255)->nullable();
            $table->text('description')->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('verified');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('recruiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('location', 180);
            $table->string('salary', 120);
            $table->enum('type', ['Full-time', 'Part-time', 'Remote', 'Hybrid', 'Contract'])->default('Full-time');
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->enum('status', ['pending', 'active', 'closed'])->default('active');
            $table->date('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('job_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->string('tag', 80);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('applicant_name', 160);
            $table->string('applicant_email', 180);
            $table->string('applicant_phone', 60)->nullable();
            $table->string('role', 180);
            $table->text('cover_note')->nullable();
            $table->string('cv_file', 255)->nullable();
            $table->enum('status', ['New', 'Reviewed', 'Shortlisted', 'Interview', 'Accepted', 'Rejected'])->default('New');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_id')->constrained('jobs')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'job_id'], 'saved_user_job');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('body', 255);
            $table->string('link', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('title', 180);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('location', 180)->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('service_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('sender_role', ['jobseeker', 'admin', 'superadmin']);
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->string('excerpt', 255)->nullable();
            $table->mediumText('content');
            $table->string('cover_image')->nullable();
            $table->string('category', 80)->nullable();
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('service_messages');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('saved_jobs');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('job_tags');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('users');
    }
};
