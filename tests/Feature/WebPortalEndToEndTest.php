<?php

namespace Tests\Feature;

use App\Enums\WorkflowStatus;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\SubmissionVersion;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebPortalEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    public function test_web_portal_teacher_upload_coordinator_review_and_gated_download_workflow(): void
    {
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $seniorCoord = User::query()->where('email', 'coord.senior@excelliaschool.edu.in')->firstOrFail();
        $principal = User::query()->where('email', 'principal@excelliaschool.edu.in')->firstOrFail();

        $task = DocumentTask::query()
            ->where('assigned_teacher_id', $ananya->id)
            ->where('workflow_status', WorkflowStatus::NOT_STARTED->value)
            ->firstOrFail();

        // Teacher views task detail page and notifications page
        $this->actingAs($ananya)->get("/tasks/{$task->id}")->assertOk()->assertSee($task->title);
        $this->actingAs($ananya)->get('/notifications')->assertOk();

        // Teacher uploads PDF via Web Portal
        $file = UploadedFile::fake()->create('math-lesson-plan-web.pdf', 250, 'application/pdf');
        $this->actingAs($ananya)
            ->post("/tasks/{$task->id}/submit", [
                'submission_mode' => 'PORTAL_UPLOAD',
                'file' => $file,
                'teacher_note' => 'Uploaded via web portal.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect("/tasks/{$task->id}");

        $this->assertSame(WorkflowStatus::SUBMITTED_PENDING_REVIEW->value, $task->fresh()->workflow_status);
        $submission = DocumentSubmission::query()->where('document_task_id', $task->id)->firstOrFail();
        $version = SubmissionVersion::query()->where('document_submission_id', $submission->id)->firstOrFail();

        // Re-render task detail AFTER submission exists (verifies timeline & download buttons render cleanly)
        $this->actingAs($ananya)->get("/tasks/{$task->id}")->assertOk()->assertSee($version->drive_file_name);

        // Authorised teacher & scoped coordinator can stream/download uploaded PDF; out-of-scope teacher cannot
        $this->actingAs($ananya)->get("/submission-versions/{$version->id}/file")->assertOk();
        $this->actingAs($seniorCoord)->get("/submission-versions/{$version->id}/file")->assertOk();

        $rohit = User::query()->where('email', 'rohit.joshi@excelliaschool.edu.in')->firstOrFail();
        $this->actingAs($rohit)->get("/submission-versions/{$version->id}/file")->assertStatus(403);

        // Coordinator views task management & review detail screen and approves
        $this->actingAs($seniorCoord)->get('/task-management')->assertOk();
        $this->actingAs($seniorCoord)->get("/reviews/{$submission->id}")->assertOk()->assertSee('Approve Submission');
        $this->actingAs($seniorCoord)
            ->post("/reviews/{$submission->id}/approve", ['remarks' => 'Approved via web portal.'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('reviews.show', $submission));

        $this->assertSame(WorkflowStatus::APPROVED->value, $task->fresh()->workflow_status);

        // Principal views approved review detail (with reopen form) and reopens approved task with reason
        $this->actingAs($principal)->get("/reviews/{$submission->id}")->assertOk();
        $this->actingAs($principal)
            ->post("/task-management/{$task->id}/reopen", ['reason' => 'CBSE circular update requires addendum'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(WorkflowStatus::RETURNED_FOR_CORRECTION->value, $task->fresh()->workflow_status);
    }
}
