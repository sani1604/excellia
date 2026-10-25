<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 64)->unique();
            $table->string('name', 150);
            $table->json('channels'); // ["IN_APP", "EMAIL"]
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('trigger_type', 40); // DUE_SOON, ON_DUE_DATE, OVERDUE_DAYS, REVIEW_SLA_HOURS
            $table->integer('threshold_value'); // e.g. -2, 0, 1, 3, 5, 48
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->string('target_role_key', 64); // TEACHER, COORDINATOR, HEADMISTRESS, PRINCIPAL, DIRECTOR
            $table->json('channels');
            $table->boolean('skip_weekends')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 191)->unique();
            $table->string('event_key', 64)->index();
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id');
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 24)->default('IN_APP'); // IN_APP, EMAIL
            $table->string('subject', 255);
            $table->text('message_summary');
            $table->dateTime('read_at')->nullable()->index();
            $table->dateTime('sent_at')->nullable()->index();
            $table->string('delivery_status', 24)->default('SENT'); // SENT, DELIVERED, SKIPPED, FAILED
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->string('provider_message_id', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'read_at', 'created_at'], 'nl_recipient_unread_idx');
            $table->index(['entity_type', 'entity_id'], 'nl_entity_idx');
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_key', 80)->index();
            $table->string('entity_type', 64)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value_json');
            $table->string('group', 50)->default('general')->index();
            $table->string('description', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('escalation_rules');
        Schema::dropIfExists('notification_rules');
    }
};
