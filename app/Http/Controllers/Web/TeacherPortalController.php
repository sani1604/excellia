<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\SubmissionVersion;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\GoogleDriveService;
use App\Services\ReportingService;
use App\Services\ScopeAuthorizationService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TeacherPortalController extends Controller
{
    public function __construct(
        private readonly TaskWorkflowService $workflow,
        private readonly ScopeAuthorizationService $scopeAuth,
        private readonly GoogleDriveService $driveService,
        private readonly ReportingService $reporting,
        private readonly AuditService $audit,
    ) {}

    public function myTasks(Request $request): View
    {
        $user = $request->user();
        $currentYear = AcademicYear::current();
        $yearId = $request->integer('academic_year_id') ?: $currentYear?->id;

        $tasks = DocumentTask::query()
            ->with([
                'academicYear',
                'documentType',
                'teachingAllocation.schoolClass',
                'teachingAllocation.section',
                'teachingAllocation.subject',
                'submission.currentVersion.latestReview.reviewer',
            ])
            ->where('assigned_teacher_id', $user->id)
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->when($request->filled('filter'), function ($q) use ($request) {
                match ($request->string('filter')->toString()) {
                    'pending' => $q->where('workflow_status', 'NOT_STARTED'),
                    'returned' => $q->where('workflow_status', 'RETURNED_FOR_CORRECTION'),
                    'under_review' => $q->whereIn('workflow_status', ['SUBMITTED_PENDING_REVIEW', 'RESUBMITTED_PENDING_REVIEW']),
                    'approved' => $q->where('workflow_status', 'APPROVED'),
                    'overdue' => $q->where('is_overdue', true)->whereNotIn('workflow_status', ['APPROVED', 'CANCELLED']),
                    default => null,
                };
            })
            ->orderBy('due_at')
            ->get();

        $allForSummary = DocumentTask::query()
            ->where('assigned_teacher_id', $user->id)
            ->when($yearId, fn ($q) => $q->where('academic_year_id', $yearId))
            ->get();

        return view('teacher.my-work', [
            'tasks' => $tasks,
            'summary' => $this->reporting->summarizeTasks($allForSummary),
            'activeFilter' => $request->string('filter')->toString(),
        ]);
    }

    public function showTask(Request $request, DocumentTask $task): View
    {
        $task->load([
            'academicYear',
            'assignedTeacher',
            'reviewer',
            'documentType',
            'teachingAllocation.schoolClass',
            'teachingAllocation.section',
            'teachingAllocation.subject',
            'submission.versions.uploader',
            'submission.versions.reviews.reviewer',
            'submission.currentVersion.latestReview.reviewer',
        ]);

        if (! $this->scopeAuth->canViewTask($request->user(), $task)) {
            abort(403, 'You are not authorised to view this task.');
        }

        $folderMapping = $this->driveService->resolveFolderMappingForTask($task);
        $canonicalHierarchy = $this->driveService->buildCanonicalFolderHierarchy($task);
        $allowedModes = (array) SystemSetting::getValue('submission_modes', ['PORTAL_UPLOAD', 'DRIVE_LINK']);

        return view('teacher.task-detail', [
            'task' => $task,
            'canSubmit' => $this->scopeAuth->canSubmitTask($request->user(), $task) && $task->canTeacherSubmit(),
            'canReview' => $this->scopeAuth->canReviewTask($request->user(), $task) && $task->canReviewerDecide(),
            'canManage' => $this->scopeAuth->canManageTaskInScope($request->user(), $task->teachingAllocation),
            'folderMapping' => $folderMapping,
            'canonicalHierarchy' => $canonicalHierarchy,
            'allowedModes' => $allowedModes,
            'scopeAuth' => $this->scopeAuth,
        ]);
    }

    public function submitTask(Request $request, DocumentTask $task): RedirectResponse
    {
        $validated = $request->validate([
            'submission_mode' => ['required', 'string', 'in:PORTAL_UPLOAD,DRIVE_LINK'],
            'file' => ['nullable', 'file'],
            'drive_file_id_or_url' => ['nullable', 'string', 'max:500'],
            'drive_file_name' => ['nullable', 'string', 'max:255'],
            'file_size_mb' => ['nullable', 'numeric', 'min:0.01', 'max:500'],
            'teacher_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $uploadedFile = $validated['submission_mode'] === 'PORTAL_UPLOAD' ? $request->file('file') : null;
        $drivePayload = null;

        if ($validated['submission_mode'] === 'DRIVE_LINK') {
            $ext = mb_strtolower(pathinfo((string) ($validated['drive_file_name'] ?? 'document.pdf'), PATHINFO_EXTENSION)) ?: 'pdf';
            $mimeMap = config('excellia.security.allowed_mime_map', []);
            $inferredMime = $mimeMap[$ext][0] ?? 'application/pdf';
            $sizeBytes = (int) round(((float) ($validated['file_size_mb'] ?? 1.0)) * 1024 * 1024);

            $drivePayload = [
                'drive_file_id_or_url' => $validated['drive_file_id_or_url'] ?? '',
                'drive_file_name' => $validated['drive_file_name'] ?? 'academic-document.pdf',
                'file_size_bytes' => max(1024, $sizeBytes),
                'mime_type' => $inferredMime,
            ];
        }

        $version = $this->workflow->submitTaskDocument(
            $task,
            $request->user(),
            $uploadedFile,
            $drivePayload,
            $validated['teacher_note'] ?? null
        );

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', "Submitted version v{$version->version_number} ({$version->drive_file_name}) successfully. Status updated to Pending Review.");
    }

    public function mySubmissions(Request $request): View
    {
        $submissions = DocumentSubmission::query()
            ->with([
                'task.academicYear',
                'task.documentType',
                'task.teachingAllocation.schoolClass',
                'task.teachingAllocation.section',
                'task.teachingAllocation.subject',
                'currentVersion.latestReview.reviewer',
                'versions',
            ])
            ->whereHas('task', fn ($q) => $q->where('assigned_teacher_id', $request->user()->id))
            ->orderByDesc('last_submitted_at')
            ->paginate(20);

        return view('teacher.my-submissions', [
            'submissions' => $submissions,
        ]);
    }

    /**
     * Secure file access endpoint enforcing backend user, permission, scope, and restricted-document policy.
     */
    public function openVersionFile(Request $request, SubmissionVersion $version): Response
    {
        $version->loadMissing([
            'submission.task.documentType',
            'submission.task.teachingAllocation.schoolClass',
            'submission.task.teachingAllocation.section',
            'submission.task.teachingAllocation.subject',
            'uploader',
        ]);

        $user = $request->user();
        $task = $version->submission?->task;
        $docType = $task?->documentType;
        $isRestricted = (bool) ($docType?->is_restricted || in_array($docType?->sensitivity_level, ['RESTRICTED', 'STRICTLY_RESTRICTED'], true));

        if (! $this->scopeAuth->canAccessSubmissionFile($user, $version)) {
            if ($isRestricted) {
                $this->audit->log(
                    'file.restricted_access_denied',
                    'submission_version',
                    $version->id,
                    null,
                    ['document_type' => $docType?->code, 'sensitivity' => $docType?->sensitivity_level],
                    $user,
                    $request
                );
            }

            abort(403, 'Restricted File Policy: You do not have explicit authorisation to open this academic document.');
        }

        if ($isRestricted) {
            $this->audit->log(
                'file.restricted_accessed',
                'submission_version',
                $version->id,
                null,
                [
                    'document_type' => $docType?->code,
                    'sensitivity' => $docType?->sensitivity_level,
                    'drive_file_id' => $version->drive_file_id,
                ],
                $user,
                $request
            );
        }

        $disk = config('excellia.drive.local_private_disk', 'local');
        if ($version->storage_disk_path && Storage::disk($disk)->exists($version->storage_disk_path)) {
            return Storage::disk($disk)->download(
                $version->storage_disk_path,
                $version->drive_file_name,
                [
                    'Content-Type' => $version->mime_type,
                    'Cache-Control' => 'private, no-store, max-age=0',
                ]
            );
        }

        // For Drive-linked or seeded Drive metadata items, render a secure institutional Drive metadata & access receipt
        $summaryContent = implode("\n", [
            'EXCELLIA SCHOOL — SECURE ACADEMIC DRIVE ACCESS RECEIPT',
            '======================================================',
            'Academic Year : ' . ($task?->academicYear?->name ?? '2026-27'),
            'Task Title    : ' . ($task?->title ?? ''),
            'Document Type : ' . ($docType?->name ?? '') . ' [' . ($docType?->sensitivity_level ?? 'STANDARD') . ']',
            'Class/Section : ' . ($task?->teachingAllocation?->schoolClass?->name ?? '') . ' - ' . ($task?->teachingAllocation?->section?->name ?? ''),
            'Subject       : ' . ($task?->teachingAllocation?->subject?->name ?? ''),
            'Version       : v' . $version->version_number . ' (' . $version->submission_mode . ')',
            'Drive File ID : ' . $version->drive_file_id,
            'File Name     : ' . $version->drive_file_name,
            'Drive URL     : ' . ($version->drive_file_url ?? 'N/A'),
            'Uploaded By   : ' . ($version->uploader?->full_name ?? ''),
            'Uploaded At   : ' . ($version->uploaded_at?->toDateTimeString() ?? ''),
            'Teacher Note  : ' . ($version->teacher_note ?? '-'),
            'Verified By   : Excellia Portal RBAC & Least-Privilege Policy (No Public Links)',
        ]);

        return response($summaryContent, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . pathinfo($version->drive_file_name, PATHINFO_FILENAME) . '_drive_record.txt"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
