<?php

namespace Database\Seeders;

use App\Enums\WorkflowStatus;
use App\Models\AcademicYear;
use App\Models\AuditEvent;
use App\Models\DocumentSubmission;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\EscalationRule;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SubmissionReview;
use App\Models\SubmissionVersion;
use App\Models\SystemSetting;
use App\Models\TeachingAllocation;
use App\Models\User;
use App\Models\UserScopeAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Standard Roles (Section 2.1)
        $rolesConfig = [
            Role::SUPER_ADMIN => [
                'name' => 'System Administrator',
                'description' => 'Configure users, roles, permissions, academic masters, document types, Drive mappings and system settings.',
            ],
            Role::TEACHER => [
                'name' => 'Teacher',
                'description' => 'View own assigned tasks, submit/resubmit documents, view own status and remarks.',
            ],
            Role::COORDINATOR => [
                'name' => 'Academic Coordinator',
                'description' => 'Review within assigned scope, approve/return, monitor delays and view scoped reports.',
            ],
            Role::HEADMISTRESS => [
                'name' => 'Headmistress',
                'description' => 'Monitor assigned classes/academic bands, escalations and reports; review only if explicitly authorised.',
            ],
            Role::PRINCIPAL => [
                'name' => 'Principal',
                'description' => 'School-level oversight, reports and authorised review/escalation actions.',
            ],
            Role::DIRECTOR => [
                'name' => 'Director / Senior Authority',
                'description' => 'High-level reports and escalation visibility; restricted file access only if authorised.',
            ],
            Role::VIEWER => [
                'name' => 'Read-only Viewer',
                'description' => 'Read-only access to explicitly assigned records/reports.',
            ],
        ];

        $roles = [];
        foreach ($rolesConfig as $key => $meta) {
            $roles[$key] = Role::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'is_system_role' => true,
                    'is_active' => true,
                ]
            );
        }

        // 2. Permission Keys (Section 2.2 + restricted file control)
        $permissionDefinitions = [
            ['users.view', 'View Users', 'users'],
            ['users.create', 'Create Users', 'users'],
            ['users.update', 'Update Users', 'users'],
            ['users.deactivate', 'Deactivate Users', 'users'],
            ['roles.view', 'View Roles', 'access'],
            ['roles.manage', 'Manage Roles & Scopes', 'access'],
            ['permissions.manage', 'Manage Role Permissions', 'access'],
            ['academic_years.manage', 'Manage Academic Years', 'masters'],
            ['classes.manage', 'Manage Classes', 'masters'],
            ['sections.manage', 'Manage Sections', 'masters'],
            ['subjects.manage', 'Manage Subjects', 'masters'],
            ['teacher_allocations.manage', 'Manage Teaching Allocations', 'masters'],
            ['file_types.manage', 'Manage Document Types', 'masters'],
            ['drive_mapping.manage', 'Manage Google Drive Mappings', 'masters'],
            ['tasks.view_own', 'View Own Assigned Tasks', 'tasks'],
            ['tasks.view_scope', 'View Scoped Tasks', 'tasks'],
            ['tasks.create', 'Create Tasks', 'tasks'],
            ['tasks.assign', 'Assign & Schedule Tasks', 'tasks'],
            ['tasks.update', 'Update & Reopen Tasks', 'tasks'],
            ['tasks.cancel', 'Cancel Tasks', 'tasks'],
            ['submissions.create_own', 'Submit Own Documents', 'submissions'],
            ['submissions.resubmit_own', 'Resubmit Own Documents', 'submissions'],
            ['submissions.view_own', 'View Own Submissions', 'submissions'],
            ['submissions.review', 'Review Scoped Submissions', 'submissions'],
            ['submissions.approve', 'Approve Submissions', 'submissions'],
            ['submissions.return', 'Return Submissions for Correction', 'submissions'],
            ['reports.view_own', 'View Own Compliance Reports', 'reports'],
            ['reports.view_scope', 'View Scoped Academic Reports', 'reports'],
            ['reports.view_school', 'View School-Wide Reports', 'reports'],
            ['notifications.manage', 'Manage Notifications & Reminders', 'governance'],
            ['audit.view', 'View Audit Events', 'governance'],
            ['system.settings.manage', 'Manage System Settings', 'governance'],
            ['files.view_restricted', 'Explicit Restricted File Access', 'governance'],
        ];

        $permissions = [];
        foreach ($permissionDefinitions as [$key, $name, $module]) {
            $permissions[$key] = Permission::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'module' => $module, 'description' => $name]
            );
        }

        $rolePermissionMatrix = [
            Role::SUPER_ADMIN => [
                'users.view', 'users.create', 'users.update', 'users.deactivate',
                'roles.view', 'roles.manage', 'permissions.manage',
                'academic_years.manage', 'classes.manage', 'sections.manage', 'subjects.manage',
                'teacher_allocations.manage', 'file_types.manage', 'drive_mapping.manage',
                'tasks.view_scope', 'tasks.create', 'tasks.assign', 'tasks.update', 'tasks.cancel',
                'reports.view_school', 'notifications.manage', 'audit.view', 'system.settings.manage',
            ],
            Role::TEACHER => [
                'tasks.view_own', 'submissions.create_own', 'submissions.resubmit_own',
                'submissions.view_own', 'reports.view_own',
            ],
            Role::COORDINATOR => [
                'tasks.view_scope', 'tasks.create', 'tasks.assign', 'tasks.update', 'tasks.cancel',
                'submissions.review', 'submissions.approve', 'submissions.return',
                'reports.view_scope', 'notifications.manage',
            ],
            Role::HEADMISTRESS => [
                'tasks.view_scope', 'submissions.review', 'submissions.approve', 'submissions.return',
                'reports.view_scope', 'notifications.manage',
            ],
            Role::PRINCIPAL => [
                'users.view', 'roles.view', 'tasks.view_scope', 'tasks.create', 'tasks.assign',
                'tasks.update', 'tasks.cancel', 'submissions.review', 'submissions.approve',
                'submissions.return', 'reports.view_school', 'notifications.manage', 'audit.view',
            ],
            Role::DIRECTOR => [
                'reports.view_school', 'audit.view',
            ],
            Role::VIEWER => [
                'tasks.view_scope', 'reports.view_scope',
            ],
        ];

        foreach ($rolePermissionMatrix as $roleKey => $permKeys) {
            $ids = array_map(fn ($k) => $permissions[$k]->id, $permKeys);
            $roles[$roleKey]->permissions()->sync($ids);
        }

        // 3. System Settings & Escalation Rules
        SystemSetting::setValue('school_name', 'Excellia School', 'general', 'Institution display name');
        SystemSetting::setValue('school_timezone', 'Asia/Kolkata', 'workflow', 'School timezone for deadlines & overdue checks');
        SystemSetting::setValue('submission_modes', ['PORTAL_UPLOAD', 'DRIVE_LINK'], 'workflow', 'Permitted teacher submission methods');
        SystemSetting::setValue('allow_co_class_teachers', false, 'academics', 'Whether co-class-teachers are allowed per section');
        SystemSetting::setValue('review_sla_hours', 48, 'workflow', 'Coordinator review SLA window in hours');
        SystemSetting::setValue('exclude_holidays_from_overdue', false, 'workflow', 'Skip school holidays during overdue day count');
        SystemSetting::setValue('holidays_dates', ['2026-08-15', '2026-10-02', '2026-10-20', '2026-11-08', '2027-01-26'], 'workflow', 'School holidays');
        SystemSetting::setValue('email_notifications_enabled', false, 'notifications', 'Enable email notifications alongside in-app alerts');
        SystemSetting::setValue('whatsapp_notifications_enabled', false, 'notifications', 'Enable WhatsApp notifications alongside in-app alerts');

        $notifEvents = [
            ['TASK_ASSIGNED', 'Task Assigned'],
            ['DUE_SOON', 'Upcoming Deadline Reminder'],
            ['DUE_DATE_REACHED', 'Due Date Reached'],
            ['OVERDUE_REMINDER', 'Overdue Reminder'],
            ['MANUAL_REMINDER', 'Manual Coordinator / Leadership Reminder'],
            ['ESCALATION_THRESHOLD_REACHED', 'Escalation Threshold Reached'],
            ['SUBMISSION_RECEIVED', 'Submission Received'],
            ['RESUBMISSION_RECEIVED', 'Resubmission Received'],
            ['SUBMISSION_APPROVED', 'Submission Approved'],
            ['SUBMISSION_RETURNED', 'Submission Returned for Correction'],
            ['REVIEW_SLA_BREACH', 'Reviewer SLA Breach'],
        ];
        foreach ($notifEvents as [$ekey, $ename]) {
            NotificationRule::query()->updateOrCreate(
                ['event_key' => $ekey],
                ['name' => $ename, 'channels' => ['IN_APP', 'EMAIL', 'WHATSAPP'], 'is_active' => true]
            );
        }

        $escalationDefaults = [
            ['Upcoming Deadline (3 Days Prior)', 'DUE_SOON', -3, 0, Role::TEACHER],
            ['Due Date Reminder', 'ON_DUE_DATE', 0, 0, Role::TEACHER],
            ['Overdue +1 Day — Academic Coordinator', 'OVERDUE_DAYS', 1, 1, Role::COORDINATOR],
            ['Overdue +3 Days — Headmistress', 'OVERDUE_DAYS', 3, 2, Role::HEADMISTRESS],
            ['Overdue +3 Days — Principal', 'OVERDUE_DAYS', 3, 2, Role::PRINCIPAL],
            ['Overdue +5 Days — Director / Senior Authority', 'OVERDUE_DAYS', 5, 3, Role::DIRECTOR],
            ['Reviewer SLA Breach (48 Hours)', 'REVIEW_SLA_HOURS', 48, 1, Role::COORDINATOR],
        ];
        foreach ($escalationDefaults as [$rname, $trigger, $threshold, $level, $targetRole]) {
            EscalationRule::query()->updateOrCreate(
                ['name' => $rname],
                [
                    'trigger_type' => $trigger,
                    'threshold_value' => $threshold,
                    'escalation_level' => $level,
                    'target_role_key' => $targetRole,
                    'channels' => ['IN_APP', 'EMAIL', 'WHATSAPP'],
                    'skip_weekends' => false,
                    'is_active' => true,
                ]
            );
        }

        // 4. Academic Years (2025-26 Archived, 2026-27 Active Current, 2027-28 Draft)
        AcademicYear::query()->updateOrCreate(
            ['name' => '2025-26'],
            [
                'start_date' => '2025-04-01',
                'end_date' => '2026-03-31',
                'is_current' => false,
                'status' => 'ARCHIVED',
            ]
        );

        $year2026 = AcademicYear::query()->updateOrCreate(
            ['name' => '2026-27'],
            [
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_current' => true,
                'status' => 'ACTIVE',
            ]
        );

        AcademicYear::query()->updateOrCreate(
            ['name' => '2027-28'],
            [
                'start_date' => '2027-04-01',
                'end_date' => '2028-03-31',
                'is_current' => false,
                'status' => 'DRAFT',
            ]
        );

        // 5. All 15 Classes (PG, Nursery, KG, Class I .. Class XII) matching Section 3.5 & Section 10 Drive labels
        $classDefs = [
            ['PG', 'PG', 1, 'PRE_PRIMARY', '02 – PG'],
            ['NUR', 'Nursery', 2, 'PRE_PRIMARY', '03 – Nursery'],
            ['KG', 'KG', 3, 'PRE_PRIMARY', '04 – KG'],
            ['C1', 'Class I', 4, 'PRIMARY', '05 – Class I'],
            ['C2', 'Class II', 5, 'PRIMARY', '06 – Class II'],
            ['C3', 'Class III', 6, 'PRIMARY', '07 – Class III'],
            ['C4', 'Class IV', 7, 'PRIMARY', '08 – Class IV'],
            ['C5', 'Class V', 8, 'PRIMARY', '09 – Class V'],
            ['C6', 'Class VI', 9, 'MIDDLE', '10 – Class VI'],
            ['C7', 'Class VII', 10, 'MIDDLE', '11 – Class VII'],
            ['C8', 'Class VIII', 11, 'MIDDLE', '12 – Class VIII'],
            ['C9', 'Class IX', 12, 'SECONDARY', '13 – Class IX'],
            ['C10', 'Class X', 13, 'SECONDARY', '14 – Class X'],
            ['C11', 'Class XI', 14, 'SENIOR_SECONDARY', '15 – Class XI'],
            ['C12', 'Class XII', 15, 'SENIOR_SECONDARY', '16 – Class XII'],
        ];

        $classes = [];
        $sections = [];
        foreach ($classDefs as [$code, $name, $order, $stage, $folderLabel]) {
            $cls = SchoolClass::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'display_order' => $order,
                    'stage' => $stage,
                    'drive_folder_label' => $folderLabel,
                    'is_active' => true,
                ]
            );
            $classes[$code] = $cls;

            foreach (['A' => 1, 'B' => 2] as $secName => $secOrder) {
                $sections["{$code}-{$secName}"] = Section::query()->updateOrCreate(
                    [
                        'academic_year_id' => $year2026->id,
                        'class_id' => $cls->id,
                        'name' => $secName,
                    ],
                    ['display_order' => $secOrder, 'is_active' => true]
                );
            }
        }

        // 6. Global Subject Master (Section 3.7)
        $subjectDefs = [
            ['ENG', 'English', 'Languages'],
            ['HIN', 'Hindi', 'Languages'],
            ['MAT', 'Mathematics', 'STEM'],
            ['EVS', 'Environmental Studies', 'Foundational'],
            ['SCI', 'Science', 'STEM'],
            ['SST', 'Social Studies', 'Humanities'],
            ['PHY', 'Physics', 'STEM'],
            ['CHE', 'Chemistry', 'STEM'],
            ['CSC', 'Computer Science', 'STEM'],
            ['ECE', 'Early Childhood Readiness', 'Pre-Primary'],
        ];

        $subjects = [];
        foreach ($subjectDefs as [$code, $name, $cat]) {
            $subjects[$code] = Subject::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $cat, 'description' => "{$name} academic curriculum", 'is_active' => true]
            );
        }

        // 7. Initial Document Type Master 00-05 (Section 3.9)
        $docTypeDefs = [
            [
                'code' => 'SUBJECT_OVERVIEW',
                'name' => 'Subject Overview & Calendar',
                'folder_code' => '00',
                'folder_name' => '00 – Subject Overview & Calendar',
                'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
                'max_file_size_mb' => 20,
                'sensitivity_level' => 'STANDARD',
                'is_restricted' => false,
                'access_policy_key' => 'STANDARD_ACADEMIC',
                'default_due_rule' => 'ONCE_YEARLY',
                'display_order' => 1,
            ],
            [
                'code' => 'LESSON_PLANS',
                'name' => 'Lesson Plans',
                'folder_code' => '01',
                'folder_name' => '01 – Lesson Plans',
                'allowed_extensions' => ['pdf', 'doc', 'docx'],
                'max_file_size_mb' => 25,
                'sensitivity_level' => 'STANDARD',
                'is_restricted' => false,
                'access_policy_key' => 'STANDARD_ACADEMIC',
                'default_due_rule' => 'MONTHLY',
                'display_order' => 2,
            ],
            [
                'code' => 'QUESTION_PAPERS',
                'name' => 'Assessment Question Papers',
                'folder_code' => '02',
                'folder_name' => '02 – Assessment Question Papers [RESTRICTED]',
                'allowed_extensions' => ['pdf', 'doc', 'docx'],
                'max_file_size_mb' => 20,
                'sensitivity_level' => 'RESTRICTED',
                'is_restricted' => true,
                'access_policy_key' => 'RESTRICTED_ASSESSMENT',
                'default_due_rule' => 'PER_ASSESSMENT',
                'display_order' => 3,
            ],
            [
                'code' => 'ANSWER_KEYS',
                'name' => 'Answer Keys & Marking Schemes',
                'folder_code' => '03',
                'folder_name' => '03 – Answer Keys & Marking Schemes [RESTRICTED]',
                'allowed_extensions' => ['pdf', 'doc', 'docx', 'xlsx'],
                'max_file_size_mb' => 20,
                'sensitivity_level' => 'RESTRICTED',
                'is_restricted' => true,
                'access_policy_key' => 'RESTRICTED_ASSESSMENT',
                'default_due_rule' => 'PER_ASSESSMENT',
                'display_order' => 4,
            ],
            [
                'code' => 'STUDENT_ANSWER_SHEETS',
                'name' => 'Student Answer Sheets',
                'folder_code' => '04',
                'folder_name' => '04 – Student Answer Sheets [STRICTLY RESTRICTED]',
                'allowed_extensions' => ['pdf'],
                'max_file_size_mb' => 50,
                'sensitivity_level' => 'STRICTLY_RESTRICTED',
                'is_restricted' => true,
                'access_policy_key' => 'STRICTLY_RESTRICTED_ANSWER_SHEETS',
                'default_due_rule' => 'PER_ASSESSMENT',
                'display_order' => 5,
            ],
            [
                'code' => 'ASSESSMENT_ANALYSIS',
                'name' => 'Assessment Analysis & Remedial Action',
                'folder_code' => '05',
                'folder_name' => '05 – Assessment Analysis & Remedial Action',
                'allowed_extensions' => ['pdf', 'xls', 'xlsx', 'docx'],
                'max_file_size_mb' => 25,
                'sensitivity_level' => 'RESTRICTED',
                'is_restricted' => true,
                'access_policy_key' => 'RESTRICTED_ASSESSMENT',
                'default_due_rule' => 'PER_ASSESSMENT',
                'display_order' => 6,
            ],
        ];

        $docTypes = [];
        foreach ($docTypeDefs as $dt) {
            $docTypes[$dt['code']] = DocumentType::query()->updateOrCreate(
                ['code' => $dt['code']],
                array_merge($dt, [
                    'description' => $dt['name'] . ' submission category',
                    'default_reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                    'requires_review' => true,
                    'is_active' => true,
                ])
            );
        }

        // 8. Users across all 7 roles (Password: Excellia@2026)
        $defaultPassword = Hash::make('Excellia@2026');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-ADM-001',
                'full_name' => 'Siddharth Kulkarni',
                'phone' => '+91-9810010001',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::SUPER_ADMIN]->id,
                'can_access_restricted_files' => false, // Explicit per Section 2.3!
                'is_active' => true,
            ]
        );

        $principal = User::query()->updateOrCreate(
            ['email' => 'principal@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-PRN-001',
                'full_name' => 'Dr. Meenakshi Verma',
                'phone' => '+91-9810010002',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::PRINCIPAL]->id,
                'can_access_restricted_files' => false,
                'is_active' => true,
            ]
        );

        $director = User::query()->updateOrCreate(
            ['email' => 'director@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-DIR-001',
                'full_name' => 'Rajeshwar Khanna',
                'phone' => '+91-9810010003',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::DIRECTOR]->id,
                'can_access_restricted_files' => false,
                'is_active' => true,
            ]
        );

        $headmistress = User::query()->updateOrCreate(
            ['email' => 'headmistress@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-HM-001',
                'full_name' => 'Kavita Nair',
                'phone' => '+91-9810010004',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::HEADMISTRESS]->id,
                'can_access_restricted_files' => false,
                'is_active' => true,
            ]
        );

        $coordPrimary = User::query()->updateOrCreate(
            ['email' => 'coord.primary@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-CRD-001',
                'full_name' => 'Neha Sharma',
                'phone' => '+91-9810010005',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::COORDINATOR]->id,
                'can_access_restricted_files' => false,
                'is_active' => true,
            ]
        );

        $coordSenior = User::query()->updateOrCreate(
            ['email' => 'coord.senior@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-CRD-002',
                'full_name' => 'Arvind Deshmukh',
                'phone' => '+91-9810010006',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::COORDINATOR]->id,
                'can_access_restricted_files' => false,
                'is_active' => true,
            ]
        );

        $teacherAnanya = User::query()->updateOrCreate(
            ['email' => 'ananya.rao@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-TCH-101',
                'full_name' => 'Ananya Rao',
                'phone' => '+91-9810010101',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::TEACHER]->id,
                'is_active' => true,
            ]
        );

        $teacherRohit = User::query()->updateOrCreate(
            ['email' => 'rohit.joshi@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-TCH-102',
                'full_name' => 'Rohit Joshi',
                'phone' => '+91-9810010102',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::TEACHER]->id,
                'is_active' => true,
            ]
        );

        $teacherPriya = User::query()->updateOrCreate(
            ['email' => 'priya.menon@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-TCH-103',
                'full_name' => 'Priya Menon',
                'phone' => '+91-9810010103',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::TEACHER]->id,
                'is_active' => true,
            ]
        );

        $viewer = User::query()->updateOrCreate(
            ['email' => 'viewer@excelliaschool.edu.in'],
            [
                'employee_code' => 'EXC-VWR-001',
                'full_name' => 'Suresh Iyer',
                'phone' => '+91-9810010201',
                'password' => $defaultPassword,
                'primary_role_id' => $roles[Role::VIEWER]->id,
                'is_active' => true,
            ]
        );

        // 9. Scope Assignments (Section 2.3)
        // Primary Coordinator: PRE_PRIMARY & PRIMARY with review rights
        foreach (['PRE_PRIMARY', 'PRIMARY'] as $stage) {
            UserScopeAssignment::query()->updateOrCreate(
                [
                    'user_id' => $coordPrimary->id,
                    'academic_year_id' => $year2026->id,
                    'scope_type' => UserScopeAssignment::SCOPE_STAGE,
                    'scope_value' => $stage,
                ],
                ['can_review' => true, 'can_access_restricted' => true, 'is_active' => true, 'assigned_by' => $admin->id]
            );

            // Headmistress monitors PRE_PRIMARY & PRIMARY (monitoring only unless can_review toggled)
            UserScopeAssignment::query()->updateOrCreate(
                [
                    'user_id' => $headmistress->id,
                    'academic_year_id' => $year2026->id,
                    'scope_type' => UserScopeAssignment::SCOPE_STAGE,
                    'scope_value' => $stage,
                ],
                ['can_review' => false, 'can_access_restricted' => false, 'is_active' => true, 'assigned_by' => $admin->id]
            );
        }

        // Senior Coordinator: MIDDLE, SECONDARY & SENIOR_SECONDARY with review rights
        foreach (['MIDDLE', 'SECONDARY', 'SENIOR_SECONDARY'] as $stage) {
            UserScopeAssignment::query()->updateOrCreate(
                [
                    'user_id' => $coordSenior->id,
                    'academic_year_id' => $year2026->id,
                    'scope_type' => UserScopeAssignment::SCOPE_STAGE,
                    'scope_value' => $stage,
                ],
                ['can_review' => true, 'can_access_restricted' => true, 'is_active' => true, 'assigned_by' => $admin->id]
            );
        }

        // Viewer: read-only access to SECONDARY stage
        UserScopeAssignment::query()->updateOrCreate(
            [
                'user_id' => $viewer->id,
                'academic_year_id' => $year2026->id,
                'scope_type' => UserScopeAssignment::SCOPE_STAGE,
                'scope_value' => 'SECONDARY',
            ],
            ['can_review' => false, 'can_access_restricted' => false, 'is_active' => true, 'assigned_by' => $admin->id]
        );

        // 10. Verified Teaching Allocations & Drive Folder Mappings
        $allocSpecs = [
            ['C10', 'A', 'MAT', $teacherAnanya, true],
            ['C10', 'B', 'MAT', $teacherAnanya, false],
            ['C12', 'A', 'PHY', $teacherAnanya, true],
            ['C4', 'A', 'ENG', $teacherRohit, true],
            ['C4', 'A', 'EVS', $teacherRohit, false],
            ['C5', 'B', 'ENG', $teacherRohit, true],
            ['C8', 'A', 'SCI', $teacherPriya, true],
            ['C10', 'A', 'CHE', $teacherPriya, false],
            ['KG', 'A', 'ECE', $teacherRohit, false],
        ];

        $allocations = [];
        foreach ($allocSpecs as $idx => [$cCode, $secName, $subCode, $teacher, $isCt]) {
            $cls = $classes[$cCode];
            $sec = $sections["{$cCode}-{$secName}"];
            $sub = $subjects[$subCode];

            $alloc = TeachingAllocation::query()->updateOrCreate(
                [
                    'academic_year_id' => $year2026->id,
                    'class_id' => $cls->id,
                    'section_id' => $sec->id,
                    'subject_id' => $sub->id,
                    'teacher_user_id' => $teacher->id,
                ],
                [
                    'is_class_teacher' => $isCt,
                    'is_co_class_teacher' => false,
                    'allocation_start_date' => '2026-04-01',
                    'is_active' => true,
                    'verified_by' => $admin->id,
                    'verified_at' => now()->subDays(20),
                ]
            );
            $allocations["{$cCode}_{$secName}_{$subCode}"] = $alloc;

            // Create verified Drive folder mappings for Lesson Plans & Question Papers
            foreach (['LESSON_PLANS', 'QUESTION_PAPERS', 'STUDENT_ANSWER_SHEETS'] as $dtCode) {
                $dt = $docTypes[$dtCode];
                $folderId = sprintf('1ExcMap_%s_%s_%s_%s', $cCode, $secName, $subCode, $dt->folder_code);
                DriveFolderMapping::query()->updateOrCreate(
                    [
                        'academic_year_id' => $year2026->id,
                        'class_id' => $cls->id,
                        'section_id' => $sec->id,
                        'subject_id' => $sub->id,
                        'teacher_user_id' => $teacher->id,
                        'document_type_id' => $dt->id,
                    ],
                    [
                        'drive_folder_id' => $folderId,
                        'drive_folder_name' => "{$cls->drive_folder_label} / Section {$secName} / {$sub->name} – {$teacher->full_name} / {$dt->folder_name}",
                        'drive_folder_url' => "https://drive.google.com/drive/folders/{$folderId}",
                        'access_policy_key' => $dt->access_policy_key,
                        'is_active' => true,
                        'verified_by' => $admin->id,
                        'verified_at' => now()->subDays(18),
                    ]
                );
            }
        }

        // 11. Sample Tasks, Submissions, Versions & Reviews demonstrating every workflow state
        if (DocumentTask::query()->count() === 0) {
            $now = now('Asia/Kolkata');

            // Task 1: APPROVED (Ananya - Class X-A Mathematics Lesson Plan October 2026)
            $t1 = DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C10_A_MAT']->id,
                'assigned_teacher_id' => $teacherAnanya->id,
                'document_type_id' => $docTypes['LESSON_PLANS']->id,
                'title' => 'Lesson Plan — Quadratic Equations & Trigonometry',
                'instructions' => 'Include weekly learning outcomes, NCERT exercises, and board-style practice problems.',
                'period_label' => 'October 2026',
                'due_at' => $now->copy()->subDays(8),
                'reviewer_user_id' => $coordSenior->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::APPROVED->value,
                'is_overdue' => false,
                'is_required' => true,
                'created_by' => $coordSenior->id,
            ]);
            $s1 = DocumentSubmission::query()->create([
                'document_task_id' => $t1->id,
                'current_status' => WorkflowStatus::APPROVED->value,
                'submitted_at' => $now->copy()->subDays(9),
                'last_submitted_at' => $now->copy()->subDays(9),
                'approved_at' => $now->copy()->subDays(7),
                'closed_at' => $now->copy()->subDays(7),
            ]);
            $v1 = SubmissionVersion::query()->create([
                'document_submission_id' => $s1->id,
                'version_number' => 1,
                'submission_mode' => 'PORTAL_UPLOAD',
                'drive_file_id' => '1ExcDrive_SeedMathOctLPv1',
                'drive_folder_id' => '1ExcMap_C10_A_MAT_01',
                'drive_file_name' => '01_class10a-math-october-lesson-plan_v1.pdf',
                'drive_file_url' => 'https://drive.google.com/file/d/1ExcDrive_SeedMathOctLPv1/view',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size_bytes' => 482304,
                'uploaded_by' => $teacherAnanya->id,
                'uploaded_at' => $now->copy()->subDays(9),
                'teacher_note' => 'Completed 4-week plan with board competency mapping.',
            ]);
            $s1->update(['current_version_id' => $v1->id]);
            SubmissionReview::query()->create([
                'submission_version_id' => $v1->id,
                'reviewer_user_id' => $coordSenior->id,
                'decision' => 'APPROVED',
                'remarks' => 'Well-structured weekly breakdown and clear formative check-ins. Approved.',
                'reviewed_at' => $now->copy()->subDays(7),
            ]);

            // Task 2: RETURNED_FOR_CORRECTION (Ananya - Class XII-A Physics Half-Yearly Question Paper [RESTRICTED])
            $t2 = DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C12_A_PHY']->id,
                'assigned_teacher_id' => $teacherAnanya->id,
                'document_type_id' => $docTypes['QUESTION_PAPERS']->id,
                'title' => 'Half-Yearly Assessment Question Paper — Physics',
                'instructions' => 'Follow CBSE 70-mark blueprint with 20% case-study questions.',
                'period_label' => 'Half-Yearly Nov 2026',
                'due_at' => $now->copy()->addDays(2),
                'reviewer_user_id' => $coordSenior->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                'is_overdue' => false,
                'is_required' => true,
                'created_by' => $coordSenior->id,
            ]);
            $s2 = DocumentSubmission::query()->create([
                'document_task_id' => $t2->id,
                'current_status' => WorkflowStatus::RETURNED_FOR_CORRECTION->value,
                'submitted_at' => $now->copy()->subDays(2),
                'last_submitted_at' => $now->copy()->subDays(2),
            ]);
            $v2 = SubmissionVersion::query()->create([
                'document_submission_id' => $s2->id,
                'version_number' => 1,
                'submission_mode' => 'PORTAL_UPLOAD',
                'drive_file_id' => '1ExcDrive_SeedPhyQPv1',
                'drive_folder_id' => '1ExcMap_C12_A_PHY_02',
                'drive_file_name' => '02_class12a-physics-hy-qp_v1.pdf',
                'drive_file_url' => 'https://drive.google.com/file/d/1ExcDrive_SeedPhyQPv1/view',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size_bytes' => 612000,
                'uploaded_by' => $teacherAnanya->id,
                'uploaded_at' => $now->copy()->subDays(2),
                'teacher_note' => 'Initial draft of 70-mark Physics paper.',
            ]);
            $s2->update(['current_version_id' => $v2->id]);
            SubmissionReview::query()->create([
                'submission_version_id' => $v2->id,
                'reviewer_user_id' => $coordSenior->id,
                'decision' => 'RETURNED',
                'remarks' => 'Section D internal choice is missing in Q32 and total marks sum to 68 instead of 70. Please revise and resubmit.',
                'reviewed_at' => $now->copy()->subDay(),
            ]);

            // Task 3: RESUBMITTED_PENDING_REVIEW with v1 (returned) + v2 (resubmitted) (Priya - Class X-A Chemistry Student Answer Sheets [STRICTLY RESTRICTED])
            $t3 = DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C10_A_CHE']->id,
                'assigned_teacher_id' => $teacherPriya->id,
                'document_type_id' => $docTypes['STUDENT_ANSWER_SHEETS']->id,
                'title' => 'Unit Test 2 Top/Median/Low Sample Student Answer Sheets',
                'instructions' => 'Upload scanned PDF bundle of 3 evaluated sample scripts per board moderation policy.',
                'period_label' => 'Unit Test 2 — Oct 2026',
                'due_at' => $now->copy()->addDays(1),
                'review_due_at' => $now->copy()->addHours(30),
                'reviewer_user_id' => $coordSenior->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
                'is_overdue' => false,
                'is_required' => true,
                'created_by' => $coordSenior->id,
            ]);
            $s3 = DocumentSubmission::query()->create([
                'document_task_id' => $t3->id,
                'current_status' => WorkflowStatus::RESUBMITTED_PENDING_REVIEW->value,
                'submitted_at' => $now->copy()->subDays(3),
                'last_submitted_at' => $now->copy()->subHours(6),
            ]);
            $v3_1 = SubmissionVersion::query()->create([
                'document_submission_id' => $s3->id,
                'version_number' => 1,
                'submission_mode' => 'PORTAL_UPLOAD',
                'drive_file_id' => '1ExcDrive_SeedCheScriptsV1',
                'drive_folder_id' => '1ExcMap_C10_A_CHE_04',
                'drive_file_name' => '04_class10a-chem-ut2-scripts_v1.pdf',
                'drive_file_url' => 'https://drive.google.com/file/d/1ExcDrive_SeedCheScriptsV1/view',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size_bytes' => 2450000,
                'uploaded_by' => $teacherPriya->id,
                'uploaded_at' => $now->copy()->subDays(3),
                'teacher_note' => 'Top and median answer scripts uploaded.',
            ]);
            SubmissionReview::query()->create([
                'submission_version_id' => $v3_1->id,
                'reviewer_user_id' => $coordSenior->id,
                'decision' => 'RETURNED',
                'remarks' => 'Low-band sample script is missing from the PDF bundle. Please include all 3 moderation bands.',
                'reviewed_at' => $now->copy()->subDays(2),
            ]);
            $v3_2 = SubmissionVersion::query()->create([
                'document_submission_id' => $s3->id,
                'version_number' => 2,
                'submission_mode' => 'PORTAL_UPLOAD',
                'drive_file_id' => '1ExcDrive_SeedCheScriptsV2',
                'drive_folder_id' => '1ExcMap_C10_A_CHE_04',
                'drive_file_name' => '04_class10a-chem-ut2-scripts_v2.pdf',
                'drive_file_url' => 'https://drive.google.com/file/d/1ExcDrive_SeedCheScriptsV2/view',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size_bytes' => 3390000,
                'uploaded_by' => $teacherPriya->id,
                'uploaded_at' => $now->copy()->subHours(6),
                'teacher_note' => 'Added evaluated low-band sample script and summary index sheet.',
            ]);
            $s3->update(['current_version_id' => $v3_2->id]);

            // Task 4: SUBMITTED_PENDING_REVIEW (Rohit - Class IV-A English Lesson Plan Nov 2026)
            $t4 = DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C4_A_ENG']->id,
                'assigned_teacher_id' => $teacherRohit->id,
                'document_type_id' => $docTypes['LESSON_PLANS']->id,
                'title' => 'Monthly Lesson Plan — Reading Comprehension & Grammar',
                'instructions' => 'Include differentiated reading activities for Section A.',
                'period_label' => 'November 2026',
                'due_at' => $now->copy()->addDays(4),
                'review_due_at' => $now->copy()->addHours(24),
                'reviewer_user_id' => $coordPrimary->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
                'is_overdue' => false,
                'is_required' => true,
                'created_by' => $coordPrimary->id,
            ]);
            $s4 = DocumentSubmission::query()->create([
                'document_task_id' => $t4->id,
                'current_status' => WorkflowStatus::SUBMITTED_PENDING_REVIEW->value,
                'submitted_at' => $now->copy()->subHours(18),
                'last_submitted_at' => $now->copy()->subHours(18),
            ]);
            $v4 = SubmissionVersion::query()->create([
                'document_submission_id' => $s4->id,
                'version_number' => 1,
                'submission_mode' => 'PORTAL_UPLOAD',
                'drive_file_id' => '1ExcDrive_SeedEngNovLPv1',
                'drive_folder_id' => '1ExcMap_C4_A_ENG_01',
                'drive_file_name' => '01_class4a-english-nov-lp_v1.pdf',
                'drive_file_url' => 'https://drive.google.com/file/d/1ExcDrive_SeedEngNovLPv1/view',
                'mime_type' => 'application/pdf',
                'file_extension' => 'pdf',
                'file_size_bytes' => 315000,
                'uploaded_by' => $teacherRohit->id,
                'uploaded_at' => $now->copy()->subHours(18),
                'teacher_note' => 'Submitted ahead of November cycle.',
            ]);
            $s4->update(['current_version_id' => $v4->id]);

            // Task 5: OVERDUE & ESCALATED L2 (Rohit - Class IV-A EVS Assessment Analysis — 4 days overdue)
            $t5 = DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C4_A_EVS']->id,
                'assigned_teacher_id' => $teacherRohit->id,
                'document_type_id' => $docTypes['ASSESSMENT_ANALYSIS']->id,
                'title' => 'Unit Test 2 Assessment Analysis & Remedial Plan — EVS',
                'instructions' => 'Identify students scoring below 50% and attach remedial worksheet schedule.',
                'period_label' => 'Unit Test 2 — Oct 2026',
                'due_at' => $now->copy()->subDays(4),
                'reviewer_user_id' => $coordPrimary->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::NOT_STARTED->value,
                'is_overdue' => true,
                'overdue_since' => $now->copy()->subDays(4),
                'escalation_level' => 2,
                'escalated_at' => $now->copy()->subDay(),
                'last_reminder_at' => $now->copy()->subDay(),
                'is_required' => true,
                'created_by' => $coordPrimary->id,
            ]);

            // Task 6: NOT_STARTED Due Soon (Ananya - Class X-B Mathematics Lesson Plan Nov 2026)
            DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C10_B_MAT']->id,
                'assigned_teacher_id' => $teacherAnanya->id,
                'document_type_id' => $docTypes['LESSON_PLANS']->id,
                'title' => 'Monthly Lesson Plan — Circles & Surface Areas',
                'instructions' => 'Upload November lesson plan PDF.',
                'period_label' => 'November 2026',
                'due_at' => $now->copy()->addDays(2),
                'reviewer_user_id' => $coordSenior->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::NOT_STARTED->value,
                'is_overdue' => false,
                'is_required' => true,
                'created_by' => $coordSenior->id,
            ]);

            // Task 7: OVERDUE L3 Escalated (Priya - Class VIII-A Science Answer Key — 6 days overdue)
            $t7 = DocumentTask::query()->create([
                'academic_year_id' => $year2026->id,
                'teaching_allocation_id' => $allocations['C8_A_SCI']->id,
                'assigned_teacher_id' => $teacherPriya->id,
                'document_type_id' => $docTypes['ANSWER_KEYS']->id,
                'title' => 'Unit Test 2 Marking Scheme & Answer Key — Science',
                'instructions' => 'Upload step-marking rubric for moderation archive.',
                'period_label' => 'Unit Test 2 — Oct 2026',
                'due_at' => $now->copy()->subDays(6),
                'reviewer_user_id' => $coordSenior->id,
                'reviewer_role_id' => $roles[Role::COORDINATOR]->id,
                'workflow_status' => WorkflowStatus::NOT_STARTED->value,
                'is_overdue' => true,
                'overdue_since' => $now->copy()->subDays(6),
                'escalation_level' => 3,
                'escalated_at' => $now->copy()->subHours(12),
                'last_reminder_at' => $now->copy()->subHours(12),
                'is_required' => true,
                'created_by' => $coordSenior->id,
            ]);

            // Seed initial Notification & Audit entries
            NotificationLog::query()->create([
                'idempotency_key' => hash('sha256', 'seed_notif_returned_ananya'),
                'event_key' => 'SUBMISSION_RETURNED',
                'entity_type' => 'document_task',
                'entity_id' => $t2->id,
                'recipient_user_id' => $teacherAnanya->id,
                'channel' => 'IN_APP',
                'subject' => "Returned for Correction: {$t2->title}",
                'message_summary' => 'Arvind Deshmukh returned v1 for correction: Section D internal choice is missing in Q32 and total marks sum to 68.',
                'sent_at' => $now->copy()->subDay(),
                'delivery_status' => 'DELIVERED',
            ]);

            NotificationLog::query()->create([
                'idempotency_key' => hash('sha256', 'seed_notif_esc_principal'),
                'event_key' => 'ESCALATION_THRESHOLD_REACHED',
                'entity_type' => 'document_task',
                'entity_id' => $t5->id,
                'recipient_user_id' => $principal->id,
                'channel' => 'IN_APP',
                'subject' => "[Escalation L2] {$t5->title}",
                'message_summary' => 'Task assigned to Rohit Joshi (Class IV-A EVS) has crossed the 3-day overdue threshold.',
                'sent_at' => $now->copy()->subDay(),
                'delivery_status' => 'DELIVERED',
            ]);

            NotificationLog::query()->create([
                'idempotency_key' => hash('sha256', 'seed_notif_esc_director'),
                'event_key' => 'ESCALATION_THRESHOLD_REACHED',
                'entity_type' => 'document_task',
                'entity_id' => $t7->id,
                'recipient_user_id' => $director->id,
                'channel' => 'IN_APP',
                'subject' => "[Escalation L3] {$t7->title}",
                'message_summary' => 'Task assigned to Priya Menon (Class VIII-A Science) has crossed the 5-day senior escalation threshold.',
                'sent_at' => $now->copy()->subHours(12),
                'delivery_status' => 'DELIVERED',
            ]);

            AuditEvent::query()->create([
                'actor_user_id' => $admin->id,
                'action_key' => 'system.initialized',
                'entity_type' => 'academic_year',
                'entity_id' => $year2026->id,
                'after_json' => ['academic_year' => '2026-27', 'classes_seeded' => 15, 'document_types_seeded' => 6],
                'ip_address' => '127.0.0.1',
                'created_at' => $now->copy()->subDays(20),
            ]);
        }
    }
}
