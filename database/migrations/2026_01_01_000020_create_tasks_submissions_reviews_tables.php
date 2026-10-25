<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('teaching_allocation_id')->constrained('teaching_allocations');
            $table->foreignId('assigned_teacher_id')->constrained('users');
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->string('period_label', 100);
            $table->dateTime('due_at')->index();
            $table->dateTime('review_due_at')->nullable()->index();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('workflow_status', 40)->default('NOT_STARTED')->index();
            $table->boolean('is_overdue')->default(false)->index();
            $table->dateTime('overdue_since')->nullable();
            $table->boolean('is_review_overdue')->default(false)->index();
            $table->dateTime('review_overdue_since')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0)->index();
            $table->dateTime('escalated_at')->nullable();
            $table->dateTime('last_reminder_at')->nullable();
            $table->boolean('is_required')->default(true)->index();
            $table->boolean('is_exempt')->default(false);
            $table->text('exemption_reason')->nullable();
            $table->string('schedule_idempotency_key', 191)->nullable()->unique();
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['assigned_teacher_id', 'workflow_status', 'due_at'], 'dt_teacher_status_due_idx');
            $table->index(['academic_year_id', 'workflow_status', 'is_overdue'], 'dt_year_status_overdue_idx');
        });

        Schema::create('document_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_task_id')->unique()->constrained('document_tasks')->cascadeOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('current_status', 40)->default('SUBMITTED_PENDING_REVIEW')->index();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('last_submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('submission_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_submission_id')->constrained('document_submissions')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('submission_mode', 24)->default('PORTAL_UPLOAD'); // PORTAL_UPLOAD, DRIVE_LINK
            $table->string('drive_file_id', 191);
            $table->string('drive_folder_id', 191)->nullable();
            $table->string('drive_file_name', 255);
            $table->string('drive_file_url', 500)->nullable();
            $table->string('storage_disk_path', 500)->nullable();
            $table->string('mime_type', 150);
            $table->string('file_extension', 20);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_checksum', 64)->nullable();
            $table->boolean('reviewer_access_verified')->default(true);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->dateTime('uploaded_at');
            $table->text('teacher_note')->nullable();
            $table->timestamps();

            $table->unique(['document_submission_id', 'version_number'], 'sv_submission_version_unique');
        });

        Schema::table('document_submissions', function (Blueprint $table) {
            $table->foreign('current_version_id', 'ds_current_version_fk')
                ->references('id')
                ->on('submission_versions')
                ->nullOnDelete();
        });

        Schema::create('submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_version_id')->constrained('submission_versions')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users');
            $table->string('decision', 24)->index(); // APPROVED, RETURNED
            $table->text('remarks')->nullable();
            $table->dateTime('reviewed_at');
            $table->timestamps();

            $table->index(['submission_version_id', 'reviewed_at'], 'sr_version_reviewed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_reviews');
        Schema::table('document_submissions', function (Blueprint $table) {
            $table->dropForeign('ds_current_version_fk');
        });
        Schema::dropIfExists('submission_versions');
        Schema::dropIfExists('document_submissions');
        Schema::dropIfExists('document_tasks');
    }
};
