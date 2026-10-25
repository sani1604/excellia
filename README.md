# Excellia School — Academic Document Submission & Monitoring System (MVP)

A role-based academic document submission, versioned review workflow, overdue escalation, and compliance reporting portal built with **Laravel 12** and **MySQL** for **Excellia School (Academic Year 2026–27)**.

---

## Core Capabilities

1. **Role-Based Access Control (RBAC) & Scoped Authorization**
   - 7 standard roles (`SUPER_ADMIN`, `TEACHER`, `COORDINATOR`, `HEADMISTRESS`, `PRINCIPAL`, `DIRECTOR`, `VIEWER`) backed by `roles`, `permissions`, `role_permissions`, and `user_scope_assignments`.
   - Fine-grained academic scopes (`STAGE`, `CLASS`, `SECTION`, `SUBJECT`, `SCHOOL`) enforced server-side on every web route, REST API endpoint, report query, and file download/access check.
   - Least-privilege sensitivity enforcement (`STANDARD`, `RESTRICTED`, `STRICTLY_RESTRICTED`): school-wide report access (`DIRECTOR`, `VIEWER`) and configuration-only admin access (`SUPER_ADMIN` without explicit grant) do **not** expose `04 – Student Answer Sheets` or restricted assessments. Every allowed or denied restricted-file access is logged in `audit_events`.

2. **Database-Configurable Masters (No Hard-Coded Academic Lists)**
   - **Users**: Canonicalised unique email, employee code, role, encrypted Google OAuth token columns, and safe deactivation that blocks login while preserving historical submissions/reviews and flagging active allocations for reassignment.
   - **Academic Years**: `2026-27` (Active/Current), `2025-26` (Archived), `2027-28` (Draft) with single-current-year enforcement.
   - **Classes & Sections**: Seeded with `PG`, `Nursery`, `KG`, and `Class I`–`Class XII` (15 classes) plus year-scoped sections (`unique(academic_year_id, class_id, name)`).
   - **Subjects & Timetable-Verified Teaching Allocations**: Global subject master, independent Class Teacher support, multi-teacher subject support where approved, duplicate prevention, and timetable verification checks before recurring task generation.
   - **Document Type Master (`00`–`05`)**: Configurable folder codes, sensitivity policies, allowed extensions, max file sizes, and default cadences (`00` Subject Overview & Calendar, `01` Lesson Plans, `02` Worksheets & Assignments, `03` Question Papers & Marking Schemes, `04` Student Answer Sheets, `05` Assessment Analysis & Remedial Action).
   - **Google Drive Folder Mapping Master**: Maps canonical folders under `EXCELLIA SCHOOL – ACADEMIC SHARING – 2026–27` with validation that mapped subjects are actively taught in the selected class/section.

3. **Tasks, Multi-Version Submissions & Review Workflow**
   - Workflow statuses: `NOT_STARTED`, `SUBMITTED_PENDING_REVIEW`, `RETURNED_FOR_CORRECTION`, `RESUBMITTED_PENDING_REVIEW`, `APPROVED`, `CANCELLED` — tracked separately from `is_overdue`, `overdue_since`, and `escalation_level`.
   - Portal file upload (stored in private storage / Google Workspace Drive API adapter with automatic canonical naming) and institutional Google Drive file linking (`no public "Anyone with the link" URLs` allowed).
   - Strict server-side extension, MIME, executable blocklist, and max-size validation; simulated/real Drive upload outages fail safely (`502`) without creating false submission records.
   - Full version history (`v1`, `v2`, ...) and immutable `submission_reviews` (`APPROVED` or `RETURNED` with mandatory remarks); authorised reopening of approved tasks requires a reason and records an audit event.

4. **Timezone-Aware Overdue Engine, Multi-Channel Notifications (In-App, Email, WhatsApp) & Reconciled Reports**
   - Scheduled Artisan command `php artisan excellia:process-overdue` evaluates deadlines in `Asia/Kolkata` (`IST`), distinguishes reviewer SLA breaches from teacher delays, escalates strictly to in-scope recipients (`L1` Coordinator -> `L2` Headmistress/Principal -> `L3` Principal/Director), and prevents duplicate notifications via idempotency keys.
   - Configurable `notification_rules` and `escalation_rules` controllable directly from `/admin/settings` across **In-App**, **Email**, and **WhatsApp** (`meta_cloud` Meta Graph API, generic `webhook` gateway, or `sandbox` logger).
   - **Google Workspace SSO** (`/auth/google` -> `/auth/google/callback` and `POST /api/auth/google`) enforcing CSRF `state`, verified `@excelliaschool.edu.in` domain matching, active user validation, and encrypted token storage alongside email/password sign-in.
   - Full Admin Web UI & REST API master controls including inline editing, safe teacher deactivation + allocation reassignment (`PATCH /admin/allocations/{id}`), single-current-academic-year switcher, and idempotent canonical Google Drive folder auto-provisioner (`POST /admin/drive-mappings/provision`).
   - Class-wise, Teacher-wise, Subject-wise, and Delay/Escalation reports exclude `CANCELLED` tasks from expected denominators, reconcile 1:1 with task-level rows, and support CSV export.

---

## Demo Accounts (Password for all: `Excellia@2026`)

| Role | Email | Scope / Notes |
|---|---|---|
| `SUPER_ADMIN` | `admin@excelliaschool.edu.in` | Full system & master configuration (`can_access_restricted_files = false` by default) |
| `PRINCIPAL` | `principal@excelliaschool.edu.in` | Full school oversight, approvals, task reopening, restricted academic review |
| `DIRECTOR` | `director@excelliaschool.edu.in` | School-wide compliance dashboards & escalation summaries (metadata/aggregates only) |
| `HEADMISTRESS` | `headmistress@excelliaschool.edu.in` | Scoped to `PRE_PRIMARY` & `PRIMARY` stages |
| `COORDINATOR` | `coord.primary@excelliaschool.edu.in` | Reviewer for `PRE_PRIMARY` & `PRIMARY` stages |
| `COORDINATOR` | `coord.senior@excelliaschool.edu.in` | Reviewer for `MIDDLE`, `SECONDARY` & `SENIOR_SECONDARY` stages |
| `TEACHER` | `ananya.rao@excelliaschool.edu.in` | Class X-A & X-B Mathematics (Class Teacher X-A) |
| `TEACHER` | `rohit.joshi@excelliaschool.edu.in` | Class IV-A EVS & KG-A English |
| `TEACHER` | `priya.menon@excelliaschool.edu.in` | Class X-A Science & Class XII-A Chemistry |
| `VIEWER` | `viewer@excelliaschool.edu.in` | Read-only observer scoped to `SECONDARY` stage |

---

## Setup & Commands

```bash
# Install dependencies & build frontend assets
composer install
npm install && npm run build

# Configure environment & run migrations + seeder
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed

# Run timezone-aware overdue & escalation processor
php artisan excellia:process-overdue

# Run automated acceptance & feature test suite
php artisan test
```
