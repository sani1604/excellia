<?php

namespace Tests\Feature;

use App\Enums\WorkflowStatus;
use App\Models\AcademicYear;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\TeachingAllocation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    private function loginApi(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'Excellia@2026',
        ])->assertOk()->json('token');
    }

    public function test_teacher_sees_only_own_tasks_and_full_submit_return_resubmit_approve_lifecycle(): void
    {
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $ananyaToken = $this->loginApi($ananya->email);
        $seniorCoordToken = $this->loginApi('coord.senior@excelliaschool.edu.in');
        $primaryCoordToken = $this->loginApi('coord.primary@excelliaschool.edu.in');

        // 1. Teacher sees ONLY own assigned tasks
        $tasksRes = $this->withToken($ananyaToken)->getJson('/api/tasks')->assertOk();
        foreach ($tasksRes->json('data') as $taskRow) {
            $this->assertSame($ananya->id, $taskRow['assigned_teacher_id']);
        }

        // Pick Ananya's NOT_STARTED task (Class X-B Mathematics Lesson Plan)
        $openTask = DocumentTask::query()
            ->where('assigned_teacher_id', $ananya->id)
            ->where('workflow_status', WorkflowStatus::NOT_STARTED->value)
            ->firstOrFail();

        // 2. Reject executable / unsupported file format server-side
        $badExe = UploadedFile::fake()->create('malware.exe', 120, 'application/x-msdownload');
        $this->withToken($ananyaToken)
            ->postJson("/api/tasks/{$openTask->id}/submissions", ['file' => $badExe])
            ->assertStatus(422);

        // 3. Simulated Google Drive outage MUST fail cleanly and NOT create a false submission
        config(['excellia.drive.simulate_outage' => true]);
        $validPdf = UploadedFile::fake()->create('november-lesson-plan.pdf', 300, 'application/pdf');
        $this->withToken($ananyaToken)
            ->postJson("/api/tasks/{$openTask->id}/submissions", ['file' => $validPdf])
            ->assertStatus(502)
            ->assertJsonPath('error_code', 'GOOGLE_DRIVE_UNAVAILABLE');

        $this->assertDatabaseMissing('document_submissions', ['document_task_id' => $openTask->id]);
        $this->assertSame(WorkflowStatus::NOT_STARTED->value, $openTask->fresh()->workflow_status);
        config(['excellia.drive.simulate_outage' => false]);

        // 4. Valid PDF upload creates v1 and sets SUBMITTED_PENDING_REVIEW
        $v1Pdf = UploadedFile::fake()->create('november-lesson-plan-v1.pdf', 350, 'application/pdf');
        $subRes = $this->withToken($ananyaToken)
            ->postJson("/api/tasks/{$openTask->id}/submissions", [
                'file' => $v1Pdf,
                'teacher_note' => 'Initial November lesson plan uploaded.',
            ])
            ->assertCreated();

        $this->assertSame(1, $subRes->json('data.version_number'));
        $this->assertSame(WorkflowStatus::SUBMITTED_PENDING_REVIEW->value, $openTask->fresh()->workflow_status);

        $submission = DocumentSubmission::query()->where('document_task_id', $openTask->id)->firstOrFail();

        // 5. Out-of-scope Primary Coordinator CANNOT review Class X task
        $this->withToken($primaryCoordToken)
            ->postJson("/api/submissions/{$submission->id}/approve")
            ->assertStatus(403);

        // Headmistress without explicit can_review flag CANNOT approve
        $hmToken = $this->loginApi('headmistress@excelliaschool.edu.in');
        $rohitPendingSub = DocumentSubmission::query()
            ->whereHas('task', fn ($q) => $q->where('workflow_status', WorkflowStatus::SUBMITTED_PENDING_REVIEW->value)
                ->whereHas('assignedTeacher', fn ($tq) => $tq->where('email', 'rohit.joshi@excelliaschool.edu.in')))
            ->firstOrFail();
        $this->withToken($hmToken)
            ->postJson("/api/submissions/{$rohitPendingSub->id}/approve")
            ->assertStatus(403);

        // 6. Return WITHOUT remarks (or whitespace remarks) is blocked
        $this->withToken($seniorCoordToken)
            ->postJson("/api/submissions/{$submission->id}/return", ['remarks' => '   '])
            ->assertStatus(422);

        // 7. Scoped Senior Coordinator returns with clear remarks
        $this->withToken($seniorCoordToken)
            ->postJson("/api/submissions/{$submission->id}/return", [
                'remarks' => 'Please add weekly formative quiz schedule for Section B.',
            ])
            ->assertOk();

        $this->assertSame(WorkflowStatus::RETURNED_FOR_CORRECTION->value, $openTask->fresh()->workflow_status);

        // 8. Teacher sees remarks and resubmits -> creates v2 while preserving v1 + v1 review decision
        $v2Pdf = UploadedFile::fake()->create('november-lesson-plan-v2.pdf', 420, 'application/pdf');
        $v2Res = $this->withToken($ananyaToken)
            ->postJson("/api/submissions/{$submission->id}/versions", [
                'file' => $v2Pdf,
                'teacher_note' => 'Added weekly formative quiz schedule in Table 3.',
            ])
            ->assertCreated();

        $this->assertSame(2, $v2Res->json('data.version_number'));
        $this->assertSame(WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value, $openTask->fresh()->workflow_status);

        $historyRes = $this->withToken($ananyaToken)
            ->getJson("/api/submissions/{$submission->id}/history")
            ->assertOk();

        $this->assertCount(2, $historyRes->json('versions'));

        // 9. Senior Coordinator approves v2 -> closes task
        $this->withToken($seniorCoordToken)
            ->postJson("/api/submissions/{$submission->id}/approve", [
                'remarks' => 'All formative checks included. Approved.',
            ])
            ->assertOk();

        $this->assertSame(WorkflowStatus::APPROVED->value, $openTask->fresh()->workflow_status);
        $this->assertNotNull($submission->fresh()->closed_at);

        // 10. Teacher cannot overwrite/submit on an already APPROVED closed task unless reopened
        $v3Pdf = UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf');
        $this->withToken($ananyaToken)
            ->postJson("/api/tasks/{$openTask->id}/submissions", ['file' => $v3Pdf])
            ->assertStatus(422);
    }

    public function test_recurring_schedule_task_generation_is_idempotent_and_blocks_unverified_allocations(): void
    {
        $seniorCoordToken = $this->loginApi('coord.senior@excelliaschool.edu.in');
        $adminToken = $this->loginApi('admin@excelliaschool.edu.in');
        $year = AcademicYear::current();
        $lessonPlanType = DocumentType::query()->where('code', 'LESSON_PLANS')->firstOrFail();

        $payload = [
            'academic_year_id' => $year->id,
            'document_type_id' => $lessonPlanType->id,
            'period_label' => 'January 2027',
            'due_at' => '2027-01-05 17:00:00',
            'title_prefix' => 'January Lesson Plan',
        ];

        // First run creates tasks for all verified allocations in Senior Coordinator's scope
        $firstRun = $this->withToken($seniorCoordToken)
            ->postJson('/api/tasks/generate', $payload)
            ->assertOk();
        $createdFirst = $firstRun->json('created_count');
        $this->assertGreaterThan(0, $createdFirst);

        // Second identical run creates 0 duplicates!
        $secondRun = $this->withToken($seniorCoordToken)
            ->postJson('/api/tasks/generate', $payload)
            ->assertOk();
        $this->assertSame(0, $secondRun->json('created_count'));
        $this->assertGreaterThanOrEqual($createdFirst, $secondRun->json('skipped_count'));

        // Unverified allocation blocks recurring task generation until verified
        $alloc = TeachingAllocation::query()->whereNotNull('subject_id')->firstOrFail();
        $alloc->update(['verified_at' => null, 'verified_by' => null]);

        $this->withToken($adminToken)
            ->postJson('/api/tasks/generate', [
                'academic_year_id' => $year->id,
                'document_type_id' => $lessonPlanType->id,
                'period_label' => 'February 2027',
                'due_at' => '2027-02-05 17:00:00',
            ])
            ->assertStatus(422);
    }
}
