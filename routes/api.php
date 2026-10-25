<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\MasterApiController;
use App\Http\Controllers\Api\ReportGovernanceApiController;
use App\Http\Controllers\Api\TaskSubmissionApiController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthApiController::class, 'login']);
Route::post('/auth/google', [AuthApiController::class, 'googleLogin']);

Route::middleware(['active.user'])->group(function () {
    Route::get('/me', [AuthApiController::class, 'me']);

    // Users
    Route::get('/users', [AuthApiController::class, 'indexUsers'])->middleware('permission:users.view');
    Route::post('/users', [AuthApiController::class, 'storeUser'])->middleware('permission:users.create');
    Route::patch('/users/{user}', [AuthApiController::class, 'updateUser'])->middleware('permission:users.update');
    Route::post('/users/{user}/deactivate', [AuthApiController::class, 'deactivateUser'])->middleware('permission:users.deactivate');

    // Roles, Permissions & Scopes
    Route::get('/roles', [MasterApiController::class, 'roles'])->middleware('permission:roles.view|roles.manage');
    Route::get('/permissions', [MasterApiController::class, 'permissions'])->middleware('permission:roles.view|permissions.manage');
    Route::post('/roles/{role}/permissions', [MasterApiController::class, 'syncRolePermissions'])->middleware('permission:permissions.manage');
    Route::post('/scope-assignments', [MasterApiController::class, 'storeScopeAssignment'])->middleware('permission:roles.manage|users.update');

    // Academic Masters
    Route::get('/academic-years', [MasterApiController::class, 'academicYears']);
    Route::post('/academic-years', [MasterApiController::class, 'storeAcademicYear'])->middleware('permission:academic_years.manage');
    Route::patch('/academic-years/{academicYear}', [MasterApiController::class, 'updateAcademicYear'])->middleware('permission:academic_years.manage');

    Route::get('/classes', [MasterApiController::class, 'classes']);
    Route::post('/classes', [MasterApiController::class, 'storeClass'])->middleware('permission:classes.manage');
    Route::patch('/classes/{schoolClass}', [MasterApiController::class, 'updateClass'])->middleware('permission:classes.manage');

    Route::get('/sections', [MasterApiController::class, 'sections']);
    Route::post('/sections', [MasterApiController::class, 'storeSection'])->middleware('permission:sections.manage');
    Route::patch('/sections/{section}', [MasterApiController::class, 'updateSection'])->middleware('permission:sections.manage');

    Route::get('/subjects', [MasterApiController::class, 'subjects']);
    Route::post('/subjects', [MasterApiController::class, 'storeSubject'])->middleware('permission:subjects.manage');
    Route::patch('/subjects/{subject}', [MasterApiController::class, 'updateSubject'])->middleware('permission:subjects.manage');

    Route::get('/teaching-allocations', [MasterApiController::class, 'teachingAllocations'])->middleware('permission:teacher_allocations.manage|tasks.view_scope|tasks.view_own|reports.view_school');
    Route::post('/teaching-allocations', [MasterApiController::class, 'storeTeachingAllocation'])->middleware('permission:teacher_allocations.manage');
    Route::patch('/teaching-allocations/{teachingAllocation}', [MasterApiController::class, 'updateTeachingAllocation'])->middleware('permission:teacher_allocations.manage');
    Route::post('/teaching-allocations/{teachingAllocation}/verify', [MasterApiController::class, 'verifyTeachingAllocation'])->middleware('permission:teacher_allocations.manage');

    Route::get('/document-types', [MasterApiController::class, 'documentTypes']);
    Route::post('/document-types', [MasterApiController::class, 'storeDocumentType'])->middleware('permission:file_types.manage');
    Route::patch('/document-types/{documentType}', [MasterApiController::class, 'updateDocumentType'])->middleware('permission:file_types.manage');

    Route::get('/drive-folder-mappings', [MasterApiController::class, 'driveMappings'])->middleware('permission:drive_mapping.manage|tasks.view_scope');
    Route::post('/drive-folder-mappings', [MasterApiController::class, 'storeDriveMapping'])->middleware('permission:drive_mapping.manage');
    Route::post('/drive-folder-mappings/provision', [MasterApiController::class, 'provisionDriveMappings'])->middleware('permission:drive_mapping.manage');
    Route::patch('/drive-folder-mappings/{driveFolderMapping}', [MasterApiController::class, 'updateDriveMapping'])->middleware('permission:drive_mapping.manage');
    Route::post('/drive-folder-mappings/{driveFolderMapping}/verify', [MasterApiController::class, 'verifyDriveMapping'])->middleware('permission:drive_mapping.manage');

    // Tasks
    Route::get('/tasks/my', [TaskSubmissionApiController::class, 'myTasks'])->middleware('permission:tasks.view_own');
    Route::post('/tasks/generate', [TaskSubmissionApiController::class, 'generateTasks'])->middleware('permission:tasks.create|tasks.assign');
    Route::get('/tasks', [TaskSubmissionApiController::class, 'indexTasks'])->middleware('permission:tasks.view_own|tasks.view_scope|reports.view_school');
    Route::post('/tasks', [TaskSubmissionApiController::class, 'storeTask'])->middleware('permission:tasks.create|tasks.assign');
    Route::get('/tasks/{task}', [TaskSubmissionApiController::class, 'showTask'])->middleware('permission:tasks.view_own|tasks.view_scope|reports.view_school');
    Route::patch('/tasks/{task}', [TaskSubmissionApiController::class, 'updateTask'])->middleware('permission:tasks.update');
    Route::post('/tasks/{task}/cancel', [TaskSubmissionApiController::class, 'cancelTask'])->middleware('permission:tasks.cancel');
    Route::post('/tasks/{task}/reopen', [TaskSubmissionApiController::class, 'reopenTask'])->middleware('permission:tasks.update');

    // Submissions & Reviews
    Route::post('/tasks/{task}/submissions', [TaskSubmissionApiController::class, 'submitTask'])->middleware('permission:submissions.create_own|submissions.resubmit_own');
    Route::post('/submissions/{submission}/versions', [TaskSubmissionApiController::class, 'addVersion'])->middleware('permission:submissions.resubmit_own|submissions.create_own');
    Route::get('/submissions/my', [TaskSubmissionApiController::class, 'mySubmissions'])->middleware('permission:submissions.view_own');
    Route::get('/submissions/{submission}', [TaskSubmissionApiController::class, 'showSubmission'])->middleware('permission:submissions.view_own|submissions.review|reports.view_scope|reports.view_school');
    Route::post('/submissions/{submission}/approve', [TaskSubmissionApiController::class, 'approveSubmission'])->middleware('permission:submissions.approve');
    Route::post('/submissions/{submission}/return', [TaskSubmissionApiController::class, 'returnSubmission'])->middleware('permission:submissions.return');
    Route::get('/submissions/{submission}/history', [TaskSubmissionApiController::class, 'submissionHistory'])->middleware('permission:submissions.view_own|submissions.review|reports.view_scope|reports.view_school');
    Route::get('/submission-versions/{version}/access-link', [TaskSubmissionApiController::class, 'fileAccessLink']);

    // Reports, Notifications, Rules, Settings & Audit
    Route::get('/reports/dashboard', [ReportGovernanceApiController::class, 'dashboard'])->middleware('permission:reports.view_own|reports.view_scope|reports.view_school');
    Route::get('/reports/management-mis', [ReportGovernanceApiController::class, 'managementMis'])->middleware('permission:reports.view_scope|reports.view_school');
    Route::get('/reports/class-wise', [ReportGovernanceApiController::class, 'classWise'])->middleware('permission:reports.view_scope|reports.view_school');
    Route::get('/reports/teacher-wise', [ReportGovernanceApiController::class, 'teacherWise'])->middleware('permission:reports.view_own|reports.view_scope|reports.view_school');
    Route::get('/reports/subject-wise', [ReportGovernanceApiController::class, 'subjectWise'])->middleware('permission:reports.view_scope|reports.view_school');
    Route::get('/reports/overdue', [ReportGovernanceApiController::class, 'overdue'])->middleware('permission:reports.view_own|reports.view_scope|reports.view_school');

    Route::get('/notifications', [ReportGovernanceApiController::class, 'notifications']);
    Route::post('/notifications/{notification}/read', [ReportGovernanceApiController::class, 'markNotificationRead']);
    Route::post('/tasks/{task}/send-reminder', [ReportGovernanceApiController::class, 'sendReminder'])->middleware('permission:notifications.manage|submissions.review|reports.view_scope|reports.view_school');

    Route::get('/notification-rules', [ReportGovernanceApiController::class, 'notificationRules'])->middleware('permission:notifications.manage|system.settings.manage');
    Route::patch('/notification-rules/{notificationRule}', [ReportGovernanceApiController::class, 'updateNotificationRule'])->middleware('permission:notifications.manage|system.settings.manage');

    Route::get('/escalation-rules', [ReportGovernanceApiController::class, 'escalationRules'])->middleware('permission:notifications.manage|system.settings.manage');
    Route::post('/escalation-rules', [ReportGovernanceApiController::class, 'storeEscalationRule'])->middleware('permission:notifications.manage|system.settings.manage');
    Route::patch('/escalation-rules/{escalationRule}', [ReportGovernanceApiController::class, 'updateEscalationRule'])->middleware('permission:notifications.manage|system.settings.manage');

    Route::get('/settings', [ReportGovernanceApiController::class, 'settings'])->middleware('permission:system.settings.manage|notifications.manage');
    Route::patch('/settings', [ReportGovernanceApiController::class, 'updateSettings'])->middleware('permission:system.settings.manage');

    Route::get('/audit-events', [ReportGovernanceApiController::class, 'auditEvents'])->middleware('permission:audit.view');
});
