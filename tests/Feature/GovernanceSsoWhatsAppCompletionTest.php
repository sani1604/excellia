<?php

namespace Tests\Feature;

use App\Enums\WorkflowStatus;
use App\Models\AcademicYear;
use App\Models\AuditEvent;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\EscalationRule;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAllocation;
use App\Models\User;
use App\Services\OverdueEscalationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GovernanceSsoWhatsAppCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_google_workspace_sso_login_callback_and_domain_security(): void
    {
        config([
            'excellia.sso.google_client_id' => 'test-google-client-id.apps.googleusercontent.com',
            'excellia.sso.google_client_secret' => 'test-google-client-secret',
            'excellia.sso.allowed_hosted_domain' => 'excelliaschool.edu.in',
        ]);

        // 1. Redirect generates CSRF state & Google OAuth URL with hd=excelliaschool.edu.in
        $redirectRes = $this->get('/auth/google')->assertRedirect();
        $targetUrl = $redirectRes->headers->get('Location');
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', (string) $targetUrl);
        $this->assertStringContainsString('hd=excelliaschool.edu.in', (string) $targetUrl);

        parse_str((string) parse_url((string) $targetUrl, PHP_URL_QUERY), $queryParams);
        $state = $queryParams['state'] ?? '';
        $this->assertNotEmpty($state);

        // 2 & 3. Mock first callback (outside domain gmail.com -> rejected), then second callback (@excelliaschool.edu.in -> succeeds)
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::sequence()
                ->push(['access_token' => 'ya29.outside_token'], 200)
                ->push([
                    'access_token' => 'ya29.excellia_valid_token',
                    'refresh_token' => '1//excellia_refresh_secret',
                ], 200),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::sequence()
                ->push([
                    'sub' => 'google-sub-outside',
                    'email' => 'intruder@gmail.com',
                    'email_verified' => true,
                ], 200)
                ->push([
                    'sub' => 'google-sub-ananya-2026',
                    'email' => 'ANANYA.RAO@excelliaschool.edu.in',
                    'email_verified' => true,
                ], 200),
        ]);

        $this->withSession(['google_oauth_state' => $state])
            ->get("/auth/google/callback?code=valid-code&state={$state}")
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->withSession(['google_oauth_state' => 'valid_state_2'])
            ->get('/auth/google/callback?code=auth-code-2&state=valid_state_2')
            ->assertRedirect('/dashboard');

        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $this->assertAuthenticatedAs($ananya);
        $this->assertSame('google_workspace', $ananya->login_provider);
        $this->assertSame('google-sub-ananya-2026', $ananya->provider_subject_id);

        // Ensure no token secret leaked into audit_events
        $ssoAudit = AuditEvent::query()->where('action_key', 'auth.google_sso_login')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('ya29.excellia_valid_token', json_encode($ssoAudit->after_json));
    }

    public function test_whatsapp_channel_dispatch_notification_rule_toggles_and_single_notification_read(): void
    {
        $admin = User::query()->where('email', 'admin@excelliaschool.edu.in')->firstOrFail();
        $rohit = User::query()->where('email', 'rohit.joshi@excelliaschool.edu.in')->firstOrFail();
        $primaryCoord = User::query()->where('email', 'coord.primary@excelliaschool.edu.in')->firstOrFail();

        $rohit->update(['phone' => '9876543210']);

        // Enable WhatsApp via Admin Settings UI POST
        $this->actingAs($admin)->post('/admin/settings', [
            'school_timezone' => 'Asia/Kolkata',
            'review_sla_hours' => 48,
            'holidays_csv' => '2026-10-02, 2026-11-08',
            'submission_modes' => ['PORTAL_UPLOAD', 'DRIVE_LINK'],
            'whatsapp_notifications_enabled' => '1',
        ])->assertRedirect();

        // Test Meta Cloud API WhatsApp provider with mocked Graph endpoint
        config([
            'excellia.whatsapp.provider' => 'meta_cloud',
            'excellia.whatsapp.phone_number_id' => '109876543210',
            'excellia.whatsapp.access_token' => 'EAAG_test_whatsapp_token',
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.Excellia2026TestMsg01']],
            ], 200),
        ]);

        $overdueTask = DocumentTask::query()
            ->where('assigned_teacher_id', $rohit->id)
            ->where('is_overdue', true)
            ->firstOrFail();

        app(OverdueEscalationService::class)->sendManualReminder($overdueTask, $primaryCoord, 'Urgent: Please upload Class IV EVS worksheet.');

        $waLog = NotificationLog::query()
            ->where('recipient_user_id', $rohit->id)
            ->where('event_key', 'MANUAL_REMINDER')
            ->where('channel', 'WHATSAPP')
            ->firstOrFail();

        $this->assertSame('SENT', $waLog->delivery_status);
        $this->assertSame('wamid.Excellia2026TestMsg01', $waLog->provider_message_id);

        // Single notification mark-as-read works for owner and rejects other users
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $this->actingAs($ananya)->post("/notifications/{$waLog->id}/read")->assertStatus(403);

        $this->actingAs($rohit)->post("/notifications/{$waLog->id}/read")->assertRedirect();
        $this->assertNotNull($waLog->fresh()->read_at);

        // Disabling MANUAL_REMINDER NotificationRule suppresses subsequent manual reminder notifications
        $manualRule = NotificationRule::query()->where('event_key', 'MANUAL_REMINDER')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/notification-rules/{$manualRule->id}", [
            'channels' => ['IN_APP', 'WHATSAPP'],
            // is_active omitted -> false
        ])->assertRedirect();

        $this->assertFalse($manualRule->fresh()->is_active);

        $beforeCount = NotificationLog::query()->count();
        app(OverdueEscalationService::class)->sendManualReminder($overdueTask, $primaryCoord, 'Second attempt while rule disabled.');
        $this->assertSame($beforeCount, NotificationLog::query()->count());

        // EscalationRule create & update via Web Admin
        $this->actingAs($admin)->post('/admin/escalation-rules', [
            'name' => 'Overdue +7 Days — Principal Escalation',
            'trigger_type' => 'OVERDUE_DAYS',
            'threshold_value' => 7,
            'escalation_level' => 3,
            'target_role_key' => 'PRINCIPAL',
            'channels' => ['IN_APP', 'WHATSAPP'],
            'is_active' => '1',
        ])->assertRedirect();

        $newEscRule = EscalationRule::query()->where('name', 'Overdue +7 Days — Principal Escalation')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/escalation-rules/{$newEscRule->id}", [
            'name' => 'Overdue +7 Days — Principal Final Escalation',
            'threshold_value' => 8,
            'escalation_level' => 3,
            'target_role_key' => 'PRINCIPAL',
            'channels' => ['IN_APP', 'EMAIL', 'WHATSAPP'],
            'skip_weekends' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame(8, $newEscRule->fresh()->threshold_value);
        $this->assertTrue($newEscRule->fresh()->skip_weekends);
    }

    public function test_web_admin_master_edits_teacher_reassignment_year_switcher_and_drive_provisioning(): void
    {
        $admin = User::query()->where('email', 'admin@excelliaschool.edu.in')->firstOrFail();
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $priya = User::query()->where('email', 'priya.menon@excelliaschool.edu.in')->firstOrFail();

        // 1. Deactivate Ananya -> flags her allocations -> Reassign Class X-A Math allocation via Web Admin PATCH
        // Verify approved task stays owned by Ananya while open task moves to Priya!
        $mathAllocXA = TeachingAllocation::query()
            ->where('teacher_user_id', $ananya->id)
            ->whereHas('section', fn ($q) => $q->where('name', 'A'))
            ->firstOrFail();

        $mathAllocXB = TeachingAllocation::query()
            ->where('teacher_user_id', $ananya->id)
            ->whereHas('section', fn ($q) => $q->where('name', 'B'))
            ->firstOrFail();

        $approvedTaskXA = DocumentTask::query()
            ->where('teaching_allocation_id', $mathAllocXA->id)
            ->where('workflow_status', WorkflowStatus::APPROVED->value)
            ->firstOrFail();

        $openTaskXB = DocumentTask::query()
            ->where('teaching_allocation_id', $mathAllocXB->id)
            ->where('workflow_status', WorkflowStatus::NOT_STARTED->value)
            ->firstOrFail();

        $this->actingAs($admin)->post("/admin/users/{$ananya->id}/deactivate", ['reason' => 'Transfer'])->assertRedirect();
        $this->assertTrue($mathAllocXA->fresh()->needs_reassignment);

        // Reassign both allocations via Web Admin PATCH
        $this->actingAs($admin)->patch("/admin/allocations/{$mathAllocXA->id}", [
            'teacher_user_id' => $priya->id,
            'is_class_teacher' => '1',
            'reassign_open_tasks' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($admin)->patch("/admin/allocations/{$mathAllocXB->id}", [
            'teacher_user_id' => $priya->id,
            'reassign_open_tasks' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        // Historical APPROVED task ownership preserved for Ananya; open task reassigned to Priya!
        $this->assertSame($ananya->id, $approvedTaskXA->fresh()->assigned_teacher_id);
        $this->assertSame($priya->id, $openTaskXB->fresh()->assigned_teacher_id);
        $this->assertFalse($mathAllocXA->fresh()->needs_reassignment);

        // 2. Web Admin inline edits for Class, Section, Subject, DocumentType, and Current Academic Year Switcher
        $cls = SchoolClass::query()->where('code', 'C10')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/classes/{$cls->id}", [
            'name' => 'Class X',
            'display_order' => $cls->display_order,
            'stage' => 'SECONDARY',
            'drive_folder_label' => '14 – Class X',
            'is_active' => '1',
        ])->assertRedirect();

        $sec = Section::query()->where('class_id', $cls->id)->firstOrFail();
        $this->actingAs($admin)->patch("/admin/sections/{$sec->id}", [
            'name' => $sec->name,
            'display_order' => 1,
            'is_active' => '1',
        ])->assertRedirect();

        $sub = Subject::query()->where('code', 'MAT')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/subjects/{$sub->id}", [
            'code' => 'MAT',
            'name' => 'Mathematics',
            'category' => 'Core STEM',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('Core STEM', $sub->fresh()->category);

        $docType = DocumentType::query()->where('code', 'LESSON_PLANS')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/document-types/{$docType->id}", [
            'name' => 'Lesson Plans & Weekly Split',
            'folder_name' => $docType->folder_name,
            'extensions_csv' => 'pdf,doc,docx',
            'max_file_size_mb' => 30,
            'sensitivity_level' => 'STANDARD',
            'default_due_rule' => 'MONTHLY',
            'requires_review' => '1',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame(30, $docType->fresh()->max_file_size_mb);

        $year2027 = AcademicYear::query()->where('name', '2027-28')->firstOrFail();
        $year2026 = AcademicYear::query()->where('name', '2026-27')->firstOrFail();
        $this->actingAs($admin)->post("/admin/academic-years/{$year2027->id}/current")->assertRedirect();
        $this->assertSame('2027-28', AcademicYear::current()->name);
        $this->actingAs($admin)->post("/admin/academic-years/{$year2026->id}/current")->assertRedirect();
        $this->assertSame('2026-27', AcademicYear::current()->name);

        // 3. Idempotent Canonical Drive Folder Provisioner via API & Web
        $beforeMappings = DriveFolderMapping::query()->count();
        $this->actingAs($admin)->post('/admin/drive-mappings/provision', [
            'academic_year_id' => $year2026->id,
        ])->assertRedirect();
        $afterFirstProvision = DriveFolderMapping::query()->count();
        $this->assertGreaterThan($beforeMappings, $afterFirstProvision);

        // Second run creates 0 duplicate mappings!
        $this->actingAs($admin)->post('/admin/drive-mappings/provision', [
            'academic_year_id' => $year2026->id,
        ])->assertRedirect();
        $this->assertSame($afterFirstProvision, DriveFolderMapping::query()->count());
    }
}
