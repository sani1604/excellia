<?php

use App\Http\Controllers\Web\AdminMastersController;
use App\Http\Controllers\Web\AiPortalController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\ManagementMisController;
use App\Http\Controllers\Web\ReportsPortalController;
use App\Http\Controllers\Web\ReviewTaskPortalController;
use App\Http\Controllers\Web\TeacherPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');
Route::post('/login/demo-switch', [AuthController::class, 'demoSwitch'])->name('login.demo');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['active.user'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Super-Admin-Controlled OpenRouter AI Work-Saver Endpoint
    Route::post('/ai/assist', [AiPortalController::class, 'assist'])->name('ai.assist');

    // Notifications (all authenticated active users)
    Route::get('/notifications', [AdminMastersController::class, 'notifications'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [AdminMastersController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [AdminMastersController::class, 'markAllNotificationsRead'])->name('notifications.read-all');

    // Teacher workflow & secure file access
    Route::get('/my-work', [TeacherPortalController::class, 'myTasks'])
        ->middleware('permission:tasks.view_own')
        ->name('teacher.tasks');

    Route::get('/my-submissions', [TeacherPortalController::class, 'mySubmissions'])
        ->middleware('permission:submissions.view_own')
        ->name('teacher.submissions');

    Route::get('/tasks/{task}', [TeacherPortalController::class, 'showTask'])
        ->middleware('permission:tasks.view_own|tasks.view_scope|reports.view_school')
        ->name('tasks.show');

    Route::post('/tasks/{task}/submit', [TeacherPortalController::class, 'submitTask'])
        ->middleware('permission:submissions.create_own|submissions.resubmit_own')
        ->name('tasks.submit');

    Route::get('/submission-versions/{version}/file', [TeacherPortalController::class, 'openVersionFile'])
        ->name('submissions.versions.file');

    // Coordinator / Reviewer & Task Assignment screens
    Route::get('/review-queue', [ReviewTaskPortalController::class, 'reviewQueue'])
        ->middleware('permission:submissions.review|reports.view_scope|reports.view_school')
        ->name('reviews.queue');

    Route::get('/reviews/{submission}', [ReviewTaskPortalController::class, 'reviewDetail'])
        ->middleware('permission:submissions.review|submissions.view_own|reports.view_scope|reports.view_school')
        ->name('reviews.show');

    Route::post('/reviews/{submission}/approve', [ReviewTaskPortalController::class, 'approve'])
        ->middleware('permission:submissions.approve')
        ->name('reviews.approve');

    Route::post('/reviews/{submission}/return', [ReviewTaskPortalController::class, 'returnForCorrection'])
        ->middleware('permission:submissions.return')
        ->name('reviews.return');

    Route::get('/overdue-monitor', [ReviewTaskPortalController::class, 'overdueMonitor'])
        ->middleware('permission:tasks.view_scope|reports.view_scope|reports.view_school')
        ->name('overdue.monitor');

    Route::post('/overdue-monitor/{task}/remind', [ReviewTaskPortalController::class, 'sendReminder'])
        ->middleware('permission:notifications.manage|submissions.review|reports.view_scope|reports.view_school')
        ->name('overdue.remind');

    Route::post('/overdue-monitor/evaluate', [ReviewTaskPortalController::class, 'evaluateOverdue'])
        ->middleware('permission:notifications.manage|system.settings.manage|tasks.assign')
        ->name('overdue.evaluate');

    Route::get('/task-management', [ReviewTaskPortalController::class, 'taskManagement'])
        ->middleware('permission:tasks.create|tasks.assign|tasks.update')
        ->name('tasks.manage');

    Route::post('/task-management/store', [ReviewTaskPortalController::class, 'storeTask'])
        ->middleware('permission:tasks.create|tasks.assign')
        ->name('tasks.store');

    Route::post('/task-management/generate', [ReviewTaskPortalController::class, 'generateSchedule'])
        ->middleware('permission:tasks.create|tasks.assign')
        ->name('tasks.generate');

    Route::patch('/task-management/{task}', [ReviewTaskPortalController::class, 'updateTask'])
        ->middleware('permission:tasks.update')
        ->name('tasks.update');

    Route::post('/task-management/{task}/cancel', [ReviewTaskPortalController::class, 'cancelTask'])
        ->middleware('permission:tasks.cancel')
        ->name('tasks.cancel');

    Route::post('/task-management/{task}/reopen', [ReviewTaskPortalController::class, 'reopenTask'])
        ->middleware('permission:tasks.update')
        ->name('tasks.reopen');

    // Executive Management Analytics, Visual Charts & Custom MIS Query Builder
    Route::get('/management-mis', [ManagementMisController::class, 'index'])
        ->middleware('permission:reports.view_school|reports.view_scope')
        ->name('management.mis');

    Route::get('/management-mis/export', [ManagementMisController::class, 'exportCsv'])
        ->middleware('permission:reports.view_school|reports.view_scope')
        ->name('management.mis.export');

    Route::post('/management-mis/presets', [ManagementMisController::class, 'storePreset'])
        ->middleware('permission:reports.view_school|reports.view_scope')
        ->name('management.mis.presets.store');

    Route::delete('/management-mis/presets/{presetId}', [ManagementMisController::class, 'destroyPreset'])
        ->middleware('permission:reports.view_school|reports.view_scope')
        ->name('management.mis.presets.destroy');

    // Reports & CSV export
    Route::get('/reports', [ReportsPortalController::class, 'index'])
        ->middleware('permission:reports.view_own|reports.view_scope|reports.view_school')
        ->name('reports.index');

    Route::get('/reports/export', [ReportsPortalController::class, 'exportCsv'])
        ->middleware('permission:reports.view_own|reports.view_scope|reports.view_school')
        ->name('reports.export');

    // Super Admin Masters, Mappings & Integrations
    Route::prefix('admin')->name('admin.')->group(function () {
        // 1. User & Staff Master CRUD
        Route::get('/users', [AdminMastersController::class, 'users'])
            ->middleware('permission:users.view')
            ->name('users');
        Route::post('/users', [AdminMastersController::class, 'storeUser'])
            ->middleware('permission:users.create')
            ->name('users.store');
        Route::patch('/users/{user}', [AdminMastersController::class, 'updateUser'])
            ->middleware('permission:users.update')
            ->name('users.update');
        Route::post('/users/{user}/deactivate', [AdminMastersController::class, 'deactivateUser'])
            ->middleware('permission:users.deactivate')
            ->name('users.deactivate');

        // 2. Role, Permission & Scope Mapping Master CRUD
        Route::get('/roles', [AdminMastersController::class, 'rolesPermissions'])
            ->middleware('permission:roles.view|roles.manage')
            ->name('roles');
        Route::post('/roles', [AdminMastersController::class, 'storeRole'])
            ->middleware('permission:roles.manage')
            ->name('roles.store');
        Route::patch('/roles/{role}', [AdminMastersController::class, 'updateRole'])
            ->middleware('permission:roles.manage')
            ->name('roles.update');
        Route::delete('/roles/{role}', [AdminMastersController::class, 'destroyRole'])
            ->middleware('permission:roles.manage')
            ->name('roles.destroy');
        Route::post('/roles/{role}/permissions', [AdminMastersController::class, 'syncRolePermissions'])
            ->middleware('permission:permissions.manage')
            ->name('roles.permissions');

        Route::post('/scopes', [AdminMastersController::class, 'storeScopeAssignment'])
            ->middleware('permission:roles.manage|users.update')
            ->name('scopes.store');
        Route::patch('/scopes/{scopeAssignment}', [AdminMastersController::class, 'updateScopeAssignment'])
            ->middleware('permission:roles.manage|users.update')
            ->name('scopes.update');
        Route::post('/scopes/{scopeAssignment}/revoke', [AdminMastersController::class, 'revokeScopeAssignment'])
            ->middleware('permission:roles.manage|users.update')
            ->name('scopes.revoke');
        Route::delete('/scopes/{scopeAssignment}', [AdminMastersController::class, 'destroyScopeAssignment'])
            ->middleware('permission:roles.manage|users.update')
            ->name('scopes.destroy');

        // 3. Academic Masters (Years, Classes, Sections, Subjects & Teacher-Subject Mapping CRUD)
        Route::get('/academics', [AdminMastersController::class, 'academicMasters'])
            ->middleware('permission:academic_years.manage|classes.manage|sections.manage|subjects.manage|teacher_allocations.manage')
            ->name('academics');
        Route::post('/academic-years', [AdminMastersController::class, 'storeAcademicYear'])
            ->middleware('permission:academic_years.manage')
            ->name('academic-years.store');
        Route::patch('/academic-years/{academicYear}', [AdminMastersController::class, 'updateAcademicYear'])
            ->middleware('permission:academic_years.manage')
            ->name('academic-years.update');
        Route::post('/academic-years/{academicYear}/current', [AdminMastersController::class, 'setCurrentYear'])
            ->middleware('permission:academic_years.manage')
            ->name('academic-years.current');
        Route::delete('/academic-years/{academicYear}', [AdminMastersController::class, 'destroyAcademicYear'])
            ->middleware('permission:academic_years.manage')
            ->name('academic-years.destroy');

        Route::post('/classes', [AdminMastersController::class, 'storeClass'])
            ->middleware('permission:classes.manage')
            ->name('classes.store');
        Route::patch('/classes/{schoolClass}', [AdminMastersController::class, 'updateClass'])
            ->middleware('permission:classes.manage')
            ->name('classes.update');
        Route::delete('/classes/{schoolClass}', [AdminMastersController::class, 'destroyClass'])
            ->middleware('permission:classes.manage')
            ->name('classes.destroy');

        Route::post('/sections', [AdminMastersController::class, 'storeSection'])
            ->middleware('permission:sections.manage')
            ->name('sections.store');
        Route::patch('/sections/{section}', [AdminMastersController::class, 'updateSection'])
            ->middleware('permission:sections.manage')
            ->name('sections.update');
        Route::delete('/sections/{section}', [AdminMastersController::class, 'destroySection'])
            ->middleware('permission:sections.manage')
            ->name('sections.destroy');

        Route::post('/subjects', [AdminMastersController::class, 'storeSubject'])
            ->middleware('permission:subjects.manage')
            ->name('subjects.store');
        Route::patch('/subjects/{subject}', [AdminMastersController::class, 'updateSubject'])
            ->middleware('permission:subjects.manage')
            ->name('subjects.update');
        Route::delete('/subjects/{subject}', [AdminMastersController::class, 'destroySubject'])
            ->middleware('permission:subjects.manage')
            ->name('subjects.destroy');

        Route::post('/allocations', [AdminMastersController::class, 'storeAllocation'])
            ->middleware('permission:teacher_allocations.manage')
            ->name('allocations.store');
        Route::patch('/allocations/{allocation}', [AdminMastersController::class, 'updateAllocation'])
            ->middleware('permission:teacher_allocations.manage')
            ->name('allocations.update');
        Route::post('/allocations/{allocation}/verify', [AdminMastersController::class, 'verifyAllocation'])
            ->middleware('permission:teacher_allocations.manage')
            ->name('allocations.verify');
        Route::delete('/allocations/{allocation}', [AdminMastersController::class, 'destroyAllocation'])
            ->middleware('permission:teacher_allocations.manage')
            ->name('allocations.destroy');

        // 4. Document Type Master CRUD
        Route::get('/document-types', [AdminMastersController::class, 'documentTypes'])
            ->middleware('permission:file_types.manage')
            ->name('document-types');
        Route::post('/document-types', [AdminMastersController::class, 'storeDocumentType'])
            ->middleware('permission:file_types.manage')
            ->name('document-types.store');
        Route::patch('/document-types/{documentType}', [AdminMastersController::class, 'updateDocumentType'])
            ->middleware('permission:file_types.manage')
            ->name('document-types.update');
        Route::delete('/document-types/{documentType}', [AdminMastersController::class, 'destroyDocumentType'])
            ->middleware('permission:file_types.manage')
            ->name('document-types.destroy');

        // 5. Google Drive Folder Mapping Master CRUD
        Route::get('/drive-mappings', [AdminMastersController::class, 'driveMappings'])
            ->middleware('permission:drive_mapping.manage')
            ->name('drive-mappings');
        Route::post('/drive-mappings', [AdminMastersController::class, 'storeDriveMapping'])
            ->middleware('permission:drive_mapping.manage')
            ->name('drive-mappings.store');
        Route::post('/drive-mappings/provision', [AdminMastersController::class, 'provisionDriveMappings'])
            ->middleware('permission:drive_mapping.manage')
            ->name('drive-mappings.provision');
        Route::patch('/drive-mappings/{mapping}', [AdminMastersController::class, 'updateDriveMapping'])
            ->middleware('permission:drive_mapping.manage')
            ->name('drive-mappings.update');
        Route::post('/drive-mappings/{mapping}/verify', [AdminMastersController::class, 'verifyDriveMapping'])
            ->middleware('permission:drive_mapping.manage')
            ->name('drive-mappings.verify');
        Route::delete('/drive-mappings/{mapping}', [AdminMastersController::class, 'destroyDriveMapping'])
            ->middleware('permission:drive_mapping.manage')
            ->name('drive-mappings.destroy');

        // 6. Integrations (Email, Whatify WhatsApp, Google Drive/SSO, OpenRouter AI) & System Settings
        Route::get('/settings', [AdminMastersController::class, 'settings'])
            ->middleware('permission:system.settings.manage|notifications.manage')
            ->name('settings');
        Route::post('/settings', [AdminMastersController::class, 'updateSettings'])
            ->middleware('permission:system.settings.manage')
            ->name('settings.update');
        Route::post('/settings/test-integration', [AdminMastersController::class, 'testIntegration'])
            ->middleware('permission:system.settings.manage')
            ->name('settings.test');
        Route::patch('/notification-rules/{notificationRule}', [AdminMastersController::class, 'updateNotificationRule'])
            ->middleware('permission:notifications.manage|system.settings.manage')
            ->name('notification-rules.update');
        Route::post('/escalation-rules', [AdminMastersController::class, 'storeEscalationRule'])
            ->middleware('permission:notifications.manage|system.settings.manage')
            ->name('escalation-rules.store');
        Route::patch('/escalation-rules/{escalationRule}', [AdminMastersController::class, 'updateEscalationRule'])
            ->middleware('permission:notifications.manage|system.settings.manage')
            ->name('escalation-rules.update');
        Route::delete('/escalation-rules/{escalationRule}', [AdminMastersController::class, 'destroyEscalationRule'])
            ->middleware('permission:notifications.manage|system.settings.manage')
            ->name('escalation-rules.destroy');

        // 7. Audit Log
        Route::get('/audit-logs', [AdminMastersController::class, 'auditLogs'])
            ->middleware('permission:audit.view')
            ->name('audit-logs');
    });
});
