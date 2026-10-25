<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_system_role')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('module', 64)->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 50)->nullable()->unique();
            $table->string('full_name', 160);
            $table->string('email', 191)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('password')->nullable();
            $table->string('api_token_hash', 64)->nullable()->unique();
            $table->string('login_provider', 40)->default('local');
            $table->string('provider_subject_id', 191)->nullable()->index();
            $table->foreignId('primary_role_id')->constrained('roles');
            $table->boolean('can_access_restricted_files')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivation_reason', 255)->nullable();
            $table->boolean('needs_reassignment_review')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 191)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
