# Excellia School — Academic Document Submission & Monitoring System

A complete, mobile-responsive academic document submission, versioned review workflow, overdue escalation, multi-channel notification (Email + Whatify WhatsApp), Google Drive / SSO, and OpenRouter AI portal built with **Laravel 12** and **MySQL** for **Excellia School (Academic Year 2026–27)**.

---

## Complete Feature Matrix (100% Implemented & Tested)

### 1. Role-Based Access Control (RBAC), Super Admin & Scope Mapping Master
- **7 Built-in Roles + Custom Role CRUD**: `SUPER_ADMIN`, `TEACHER`, `COORDINATOR`, `HEADMISTRESS`, `PRINCIPAL`, `DIRECTOR`, `VIEWER` plus custom roles via `/admin/roles?tab=roles`, backed by `roles`, `permissions`, `role_permissions`, and `user_scope_assignments`.
- **Fine-Grained Scope Mapping CRUD (`/admin/roles?tab=scopes`)**: Create, edit, deactivate, and delete staff supervision scopes (`WHOLE_SCHOOL` / `SCHOOL`, `STAGE` Wing, `CLASS`, `SECTION`, `SUBJECT`) enforced server-side on every web route, REST API endpoint, report query, and file download/access check.
- **Least-Privilege Sensitivity Enforcement**: `STANDARD`, `RESTRICTED`, and `STRICTLY_RESTRICTED` document controls ensure school-wide report viewers (`DIRECTOR`, `VIEWER`) and configuration-only admins (`SUPER_ADMIN` without explicit grant) cannot open `04 – Student Answer Sheets` or restricted assessments. Every allowed or denied restricted-file access is logged in `audit_events`.

### 2. Complete Super Admin Master CRUDs (Zero Hard-Coded Lists)
- **User & Staff Master (`/admin/users`)**: Canonicalised unique email, employee code, WhatsApp phone, role assignment, encrypted Google OAuth columns, safe deactivation that blocks login while preserving historical submissions/reviews and flagging active allocations for reassignment, plus 1-click teacher reassignment.
- **Academic & Teacher Master (`/admin/academics` — 5 Clean Tabs)**:
  - **Teacher Allocations**: Independent Class Teacher assignment, multi-teacher subject support when approved, duplicate prevention, timetable verification, and automatic Drive folder provisioning.
  - **Academic Years**: `2026-27` (Active/Current), `2025-26` (Archived), `2027-28` (Draft) with single-current-year enforcement and global header switcher.
  - **Classes (`PG`, `Nursery`, `KG`, `Class I`–`XII`)**, **Sections** (`unique(academic_year_id, class_id, name)`), and **Subjects** with create, inline edit, and safe delete.
- **Document Type Master (`/admin/document-types`)**: Configurable folder codes (`00`–`05` + custom), sensitivity policies, allowed extensions, max file sizes, and default cadences.
- **Google Drive Folder Mapping Master (`/admin/drive-mappings`)**: Maps canonical folders under `EXCELLIA SCHOOL – ACADEMIC SHARING – 2026–27`, auto-provisions folders for verified allocations, and supports manual create/edit/verify/delete.

### 3. Tasks, Multi-Version Submissions & Review Workflow
- **Workflow States**: `NOT_STARTED` → `SUBMITTED_PENDING_REVIEW` → `RETURNED_FOR_CORRECTION` → `RESUBMITTED_PENDING_REVIEW` → `APPROVED`, plus `CANCELLED` and audited `Reopen` of `APPROVED` tasks with mandatory reason — tracked separately from `is_overdue`, `overdue_since`, `is_review_overdue`, and `escalation_level`.
- **Secure Upload & Drive Linking**: Private vault / Google Drive API v3 upload with automatic canonical naming (`Class_Section_Subject_DocType_Period_Teacher_vN.ext`), institutional Google Drive link validation (rejects public `"Anyone with the link"` URLs), strict extension/MIME/executable/size checks, SHA-256 checksums, and safe `502` handling on Drive outages.
- **Immutable History**: Complete version history (`v1`, `v2`, ...) and `submission_reviews` (`APPROVED` or `RETURNED` with mandatory remarks).

### 4. Super Admin Integrations & Settings Hub (`/admin/settings` — 6 Tabs)
All secrets (API keys, OAuth secrets, SMTP passwords) are stored encrypted at rest (`Crypt::encryptString`) in `system_settings` and masked in UI & API responses.
1. **General School Rules (`?tab=general`)**: Timezone (`Asia/Kolkata`), Review SLA hours, Submission Modes, and School Holidays.
2. **Email Reminders ON / OFF (`?tab=email`)**: Master Enable/Disable email switch (stops all email reminders immediately when unchecked), Mailer Mode (`log` vs `smtp`), Sender Address/Name, SMTP credentials, and **Send Test Email**.
3. **WhatsApp — Official Whatify API (`?tab=whatsapp`)**: Native integration with `https://whatify.in/api/v1/external` (`X-API-Key` header) supporting **Text Mode** (`POST /send-message`), **Template Mode** (`POST /send-template` with `template_name` & positional `body_params`), optional `whatsapp_account_id`, **Verify Connection** (`GET /ping` + `GET /wallet/balance`), and **Send Test WhatsApp** (plus Meta Cloud, Webhook, and Sandbox adapters).
4. **Google Drive & Google Workspace SSO (`?tab=google`)**: Live Google Drive API v3 vs Local Vault switch, Root Folder ID/Name, OAuth/Service Account credentials, **Verify Drive Connection**, and **Google Workspace SSO** ON/OFF toggle (`@excelliaschool.edu.in` domain verified).
5. **AI Work-Saver — OpenRouter (`?tab=ai`)**: **Super Admin Master Enable/Disable** switch, separate Teacher and Coordinator/Leadership role toggles, encrypted OpenRouter API key, model selector, and one-click AI drafting buttons for Teacher outlines/notes, Coordinator task instructions, and Review feedback.
6. **Alert & Escalation Rules (`?tab=rules`)**: Event channel toggles (`IN_APP`, `EMAIL`, `WHATSAPP`) and IST timezone-aware `excellia:process-overdue` escalation rules (`L1` Coordinator → `L2` Headmistress/Principal → `L3` Principal/Director).

### 5. Reconciled Reports & Mobile-First School UI
- **Reports & CSV Export (`/reports`)**: Class-wise, Teacher-wise, Subject-wise, and Delay/Escalation reports excluding `CANCELLED` tasks from expected denominators and reconciling 1:1 with task-level rows.
- **Mobile & Desktop Responsive**: Sticky mobile header with slide-over navigation drawer, touch-friendly controls, scrollable tab bars, and responsive card/table layouts.

---

## Demo Accounts (Password for all: `Excellia@2026`)

| Role | Email | Scope / Notes |
|---|---|---|
| `SUPER_ADMIN` | `admin@excelliaschool.edu.in` | Full Masters, Scope Mapping & Integrations Hub (`can_access_restricted_files = false` by default) |
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

# Run full automated acceptance & feature test suite (18 tests, 322+ assertions)
php artisan test
```
