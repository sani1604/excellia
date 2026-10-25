<?php

namespace Tests\Feature;

use App\Enums\WorkflowStatus;
use App\Models\AuditEvent;
use App\Models\DocumentTask;
use App\Models\NotificationLog;
use App\Models\SubmissionVersion;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OverdueEscalationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityOverdueReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function loginApi(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'Excellia@2026',
        ])->assertOk()->json('token');
    }

    public function test_cross_teacher_isolation_restricted_file_controls_and_no_public_drive_links(): void
    {
        $ananyaToken = $this->loginApi('ananya.rao@excelliaschool.edu.in');
        $rohit = User::query()->where('email', 'rohit.joshi@excelliaschool.edu.in')->firstOrFail();
        $priya = User::query()->where('email', 'priya.menon@excelliaschool.edu.in')->firstOrFail();

        // 1. Teacher A (Ananya) CANNOT access Teacher B's (Rohit's) task or submission by changing ID
        $rohitTask = DocumentTask::query()
            ->where('assigned_teacher_id', $rohit->id)
            ->whereHas('submission')
            ->with('submission')
            ->firstOrFail();

        $this->withToken($ananyaToken)->getJson("/api/tasks/{$rohitTask->id}")->assertStatus(403);
        $this->withToken($ananyaToken)->getJson("/api/submissions/{$rohitTask->submission->id}")->assertStatus(403);
        $this->withToken($ananyaToken)->getJson("/api/submissions/{$rohitTask->submission->id}/history")->assertStatus(403);

        // 2. STRICTLY_RESTRICTED Student Answer Sheets (Priya's Class X Chemistry task):
        // Director, Viewer, and even Configuration-only Super Admin (without explicit restricted grant) MUST be blocked!
        $restrictedVersion = SubmissionVersion::query()
            ->whereHas('submission.task', fn ($q) => $q->where('assigned_teacher_id', $priya->id)
                ->whereHas('documentType', fn ($dq) => $dq->where('code', 'STUDENT_ANSWER_SHEETS')))
            ->firstOrFail();

        $directorToken = $this->loginApi('director@excelliaschool.edu.in');
        $viewerToken = $this->loginApi('viewer@excelliaschool.edu.in');
        $adminToken = $this->loginApi('admin@excelliaschool.edu.in');
        $priyaToken = $this->loginApi('priya.menon@excelliaschool.edu.in');
        $seniorCoordToken = $this->loginApi('coord.senior@excelliaschool.edu.in');

        // Director sees reports, NOT restricted student answer sheets
        $this->withToken($directorToken)
            ->getJson("/api/submission-versions/{$restrictedVersion->id}/access-link")
            ->assertStatus(403);

        // Read-only Viewer blocked from restricted student answer sheets
        $this->withToken($viewerToken)
            ->getJson("/api/submission-versions/{$restrictedVersion->id}/access-link")
            ->assertStatus(403);

        // Super Admin config role does NOT automatically grant access to Student Answer Sheets (Section 2.3)
        $this->withToken($adminToken)
            ->getJson("/api/submission-versions/{$restrictedVersion->id}/access-link")
            ->assertStatus(403);

        // Denied attempts are audited!
        $this->assertTrue(
            AuditEvent::query()
                ->where('action_key', 'file.restricted_access_denied')
                ->where('entity_id', $restrictedVersion->id)
                ->exists()
        );

        // Assigned Teacher (Priya) AND Scoped Reviewer (Senior Coordinator) CAN access and log file.restricted_accessed
        $this->withToken($priyaToken)
            ->getJson("/api/submission-versions/{$restrictedVersion->id}/access-link")
            ->assertOk();

        $this->withToken($seniorCoordToken)
            ->getJson("/api/submission-versions/{$restrictedVersion->id}/access-link")
            ->assertOk();

        $this->assertTrue(
            AuditEvent::query()
                ->where('action_key', 'file.restricted_accessed')
                ->where('entity_id', $restrictedVersion->id)
                ->exists()
        );

        // 3. Public "Anyone with the link" Google Drive URLs are blocked
        $ananyaOpenTask = DocumentTask::query()
            ->whereHas('assignedTeacher', fn ($q) => $q->where('email', 'ananya.rao@excelliaschool.edu.in'))
            ->where('workflow_status', WorkflowStatus::NOT_STARTED->value)
            ->firstOrFail();

        $this->withToken($ananyaToken)
            ->postJson("/api/tasks/{$ananyaOpenTask->id}/submissions", [
                'drive_file_id_or_url' => 'https://drive.google.com/file/d/1PublicFileId12345/view?usp=sharing_public&anyone=true',
                'drive_file_name' => 'lesson-plan.pdf',
                'file_size_bytes' => 204800,
                'mime_type' => 'application/pdf',
            ])
            ->assertStatus(422);
    }

    public function test_overdue_scheduler_idempotency_scoped_escalations_and_report_reconciliation(): void
    {
        /** @var OverdueEscalationService $overdueService */
        $overdueService = app(OverdueEscalationService::class);

        $firstRun = $overdueService->processAll();
        $countAfterFirstRun = NotificationLog::query()->count();
        $this->assertGreaterThan(0, $firstRun['evaluated_tasks']);

        // Running scheduler repeatedly at the same timestamp MUST NOT create duplicate notification logs!
        $overdueService->processAll();
        $countAfterSecondRun = NotificationLog::query()->count();
        $this->assertSame($countAfterFirstRun, $countAfterSecondRun);

        // Approved tasks are never marked overdue
        $this->assertSame(
            0,
            DocumentTask::query()
                ->where('workflow_status', WorkflowStatus::APPROVED->value)
                ->where('is_overdue', true)
                ->count()
        );

        // Verify Primary wing overdue task (Rohit Class IV EVS) escalated ONLY to Primary Coordinator / Headmistress / Principal, NOT Senior Coordinator!
        $rohitOverdueTask = DocumentTask::query()
            ->whereHas('assignedTeacher', fn ($q) => $q->where('email', 'rohit.joshi@excelliaschool.edu.in'))
            ->where('is_overdue', true)
            ->firstOrFail();

        $seniorCoord = User::query()->where('email', 'coord.senior@excelliaschool.edu.in')->firstOrFail();
        $primaryCoord = User::query()->where('email', 'coord.primary@excelliaschool.edu.in')->firstOrFail();

        $this->assertTrue(
            NotificationLog::query()
                ->where('entity_type', 'document_task')
                ->where('entity_id', $rohitOverdueTask->id)
                ->where('recipient_user_id', $primaryCoord->id)
                ->exists()
        );
        $this->assertFalse(
            NotificationLog::query()
                ->where('entity_type', 'document_task')
                ->where('entity_id', $rohitOverdueTask->id)
                ->where('recipient_user_id', $seniorCoord->id)
                ->exists()
        );

        // Reconcile Dashboard KPIs against task-level report records, ensuring cancelled tasks are excluded from expected totals
        $principalToken = $this->loginApi('principal@excelliaschool.edu.in');
        $dashBefore = $this->withToken($principalToken)->getJson('/api/reports/dashboard')->assertOk()->json('data.summary');

        // Cancel one open task and verify Expected count drops by 1 and Cancelled tasks are excluded
        $openTask = DocumentTask::query()->where('workflow_status', WorkflowStatus::NOT_STARTED->value)->firstOrFail();
        $this->withToken($principalToken)
            ->postJson("/api/tasks/{$openTask->id}/cancel", ['reason' => 'Syllabus merged for term'])
            ->assertOk();

        $dashAfter = $this->withToken($principalToken)->getJson('/api/reports/dashboard')->assertOk()->json('data.summary');
        $this->assertSame($dashBefore['expected'] - 1, $dashAfter['expected']);

        $classReport = $this->withToken($principalToken)->getJson('/api/reports/class-wise')->assertOk()->json('data');
        $sumExpectedRows = array_sum(array_column($classReport['rows'], 'expected'));
        $this->assertSame($dashAfter['expected'], $sumExpectedRows);

        // Verify AuditService redacts secrets/tokens/passwords automatically
        /** @var AuditService $audit */
        $audit = app(AuditService::class);
        $redacted = $audit->redact([
            'email' => 'test@excelliaschool.edu.in',
            'password' => 'SuperSecret123',
            'oauth_refresh_token' => 'ya29.secret',
            'nested' => ['private_key' => 'BEGIN RSA PRIVATE KEY'],
        ]);
        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['oauth_refresh_token']);
        $this->assertSame('[REDACTED]', $redacted['nested']['private_key']);
    }

    public function test_web_portal_screens_render_for_roles_and_csv_export_streams(): void
    {
        $principal = User::query()->where('email', 'principal@excelliaschool.edu.in')->firstOrFail();
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $admin = User::query()->where('email', 'admin@excelliaschool.edu.in')->firstOrFail();

        // Teacher screens
        $this->actingAs($ananya)->get('/dashboard')->assertOk()->assertSee('My Work & Academic Submissions');
        $this->actingAs($ananya)->get('/my-work')->assertOk();
        $this->actingAs($ananya)->get('/my-submissions')->assertOk();
        $this->actingAs($ananya)->get('/admin/users')->assertStatus(403);

        // Principal screens & CSV export
        $this->actingAs($principal)->get('/dashboard')->assertOk()->assertSee('Completion Rate');
        $this->actingAs($principal)->get('/review-queue')->assertOk();
        $this->actingAs($principal)->get('/overdue-monitor')->assertOk();
        $this->actingAs($principal)->get('/reports?tab=class-wise')->assertOk();
        $this->actingAs($principal)->get('/reports?tab=teacher-wise')->assertOk();
        $this->actingAs($principal)->get('/reports?tab=subject-wise')->assertOk();
        $this->actingAs($principal)->get('/reports?tab=overdue')->assertOk();
        $this->actingAs($principal)->get('/reports/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Admin Master screens
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/admin/academics')->assertOk();
        $this->actingAs($admin)->get('/admin/document-types')->assertOk();
        $this->actingAs($admin)->get('/admin/drive-mappings')->assertOk();
        $this->actingAs($admin)->get('/admin/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk();
    }
}
