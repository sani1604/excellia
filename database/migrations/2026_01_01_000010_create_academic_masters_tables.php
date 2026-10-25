<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false)->index();
            $table->string('status', 24)->default('DRAFT')->index(); // DRAFT, ACTIVE, ARCHIVED
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name', 80);
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->string('stage', 40)->nullable()->index(); // PRE_PRIMARY, PRIMARY, MIDDLE, SECONDARY, SENIOR_SECONDARY
            $table->string('drive_folder_label', 120)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_id')->constrained('classes');
            $table->string('name', 32);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['academic_year_id', 'class_id', 'name'], 'sections_year_class_name_unique');
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable()->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 64)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('user_scope_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->string('scope_type', 32); // SCHOOL, STAGE, CLASS, SECTION, SUBJECT, TEACHER
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('scope_value', 64)->nullable();
            $table->boolean('can_review')->default(false);
            $table->boolean('can_access_restricted')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'scope_type'], 'usa_user_active_scope_idx');
        });

        Schema::create('teaching_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('subject_id')->nullable()->constrained('subjects');
            $table->foreignId('teacher_user_id')->constrained('users');
            $table->boolean('is_class_teacher')->default(false)->index();
            $table->boolean('is_co_class_teacher')->default(false);
            $table->date('allocation_start_date')->nullable();
            $table->date('allocation_end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('needs_reassignment')->default(false)->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['academic_year_id', 'class_id', 'section_id', 'subject_id'], 'ta_year_class_sec_subj_idx');
            $table->index(['teacher_user_id', 'is_active'], 'ta_teacher_active_idx');
        });

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('folder_code', 16);
            $table->string('folder_name', 180);
            $table->json('allowed_extensions');
            $table->unsignedInteger('max_file_size_mb')->default(25);
            $table->string('sensitivity_level', 32)->default('STANDARD'); // STANDARD, RESTRICTED, STRICTLY_RESTRICTED
            $table->boolean('is_restricted')->default(false)->index();
            $table->string('access_policy_key', 64)->default('STANDARD_ACADEMIC');
            $table->foreignId('default_reviewer_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('default_due_rule', 64)->nullable(); // ONCE_YEARLY, MONTHLY, WEEKLY, PER_ASSESSMENT
            $table->boolean('requires_review')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('drive_folder_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('section_id')->constrained('sections');
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('teacher_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->string('drive_folder_id', 191);
            $table->string('drive_folder_name', 255);
            $table->string('drive_folder_url', 500)->nullable();
            $table->string('access_policy_key', 64)->default('STANDARD_ACADEMIC');
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(
                ['academic_year_id', 'class_id', 'section_id', 'subject_id', 'document_type_id'],
                'dfm_context_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_folder_mappings');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('teaching_allocations');
        Schema::dropIfExists('user_scope_assignments');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('academic_years');
    }
};
