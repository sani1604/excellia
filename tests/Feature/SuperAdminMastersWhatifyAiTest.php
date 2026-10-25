<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DocumentTask;
use App\Models\DocumentType;
use App\Models\DriveFolderMapping;
use App\Models\EscalationRule;
use App\Models\NotificationLog;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserScopeAssignment;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuperAdminMastersWhatifyAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_whatify_whatsapp_integration_email_removal_and_google_drive_settings(): void
    {
        $admin = User::query()->where('email', 'admin@excelliaschool.edu.in')->firstOrFail();
        $coord = User::query()->where('email', 'coord.senior@excelliaschool.edu.in')->firstOrFail();
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();

        Http::fake([
            'https://whatify.in/api/v1/external/ping' => Http::response([
                'status' => 'ok',
                'timestamp' => '2026-10-25T10:30:00Z',
            ], 200),
            'https://whatify.in/api/v1/external/wallet/balance' => Http::response([
                'balance' => 1500.5,
                'currency' => 'INR',
            ], 200),
            'https://whatify.in/api/v1/external/send-message' => Http::response([
                'success' => true,
                'message_id' => 505,
                'status' => 'delivered',
                'meta_response' => ['wamid' => 'wamid.whatify.text.505'],
            ], 200),
            'https://whatify.in/api/v1/external/send-template' => Http::response([
                'success' => true,
                'message_id' => 506,
                'status' => 'delivered',
                'meta_response' => ['wamid' => 'wamid.whatify.tpl.506'],
            ], 200),
        ]);

        // 1. Super Admin configures Whatify API (encrypted X-API-Key, account ID, text mode)
        $this->actingAs($admin)->post('/admin/settings', [
            'settings_section' => 'whatsapp',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_provider' => 'whatify',
            'whatsapp_default_country_code' => '91',
            'whatify_base_url' => 'https://whatify.in/api/v1/external',
            'whatify_api_key' => 'wfy_live_secret_key_987654',
            'whatify_account_id' => 1,
            'whatify_mode' => 'text',
        ])->assertRedirect(route('admin.settings', ['tab' => 'whatsapp']));

        // Verify API key is stored encrypted (never plaintext in DB!)
        $rawRow = SystemSetting::query()->where('key', 'whatify_api_key')->firstOrFail();
        $this->assertArrayHasKey('encrypted', $rawRow->value_json);
        $this->assertStringNotContainsString('wfy_live_secret_key_987654', json_encode($rawRow->value_json));
        $this->assertSame('wfy_live_secret_key_987654', SystemSetting::getSecret('whatify_api_key'));

        // 2. Super Admin clicks "Verify Whatify Connection" -> hits /ping and /wallet/balance with X-API-Key
        $this->actingAs($admin)->post('/admin/settings/test-integration', [
            'target' => 'whatify_ping',
        ])->assertRedirect(route('admin.settings', ['tab' => 'whatsapp']))
            ->assertSessionHas('success');

        // 3. Super Admin disables/removes Email notifications completely ("setting for remover email")
        Mail::fake();
        $this->actingAs($admin)->post('/admin/settings', [
            'settings_section' => 'email',
            'mail_mailer_mode' => 'log',
            'mail_from_address' => 'noreply@excelliaschool.edu.in',
            'mail_from_name' => 'Excellia School Portal',
            // email_notifications_enabled intentionally unchecked!
        ])->assertRedirect(route('admin.settings', ['tab' => 'email']));

        $this->assertFalse((bool) SystemSetting::getValue('email_notifications_enabled', true));

        // 4. Coordinator triggers reminder -> Whatify /send-message fires, Email is skipped!
        $task = DocumentTask::query()
            ->where('assigned_teacher_id', $ananya->id)
            ->where('workflow_status', 'NOT_STARTED')
            ->firstOrFail();
        $this->actingAs($coord)->post("/overdue-monitor/{$task->id}/remind", [
            'message' => 'Kindly upload Class X Lesson Plan today.',
        ])->assertRedirect()->assertSessionHas('success');

        $waLog = NotificationLog::query()
            ->where('event_key', 'MANUAL_REMINDER')
            ->where('entity_id', $task->id)
            ->where('channel', 'WHATSAPP')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('SENT', $waLog->delivery_status);
        $this->assertSame('wamid.whatify.text.505', $waLog->provider_message_id);

        $emailCount = NotificationLog::query()
            ->where('event_key', 'MANUAL_REMINDER')
            ->where('entity_id', $task->id)
            ->where('channel', 'EMAIL')
            ->count();
        $this->assertSame(0, $emailCount);

        Http::assertSent(function (HttpClientRequest $req) {
            return $req->url() === 'https://whatify.in/api/v1/external/send-message'
                && $req->hasHeader('X-API-Key', 'wfy_live_secret_key_987654')
                && ($req['whatsapp_account_id'] ?? null) === 1
                && ! empty($req['phone'])
                && ! empty($req['message']);
        });

        // 5. Switch Whatify to approved Template mode (/send-template) and test direct WhatsApp test button
        $this->actingAs($admin)->post('/admin/settings', [
            'settings_section' => 'whatsapp',
            'whatsapp_notifications_enabled' => '1',
            'whatsapp_provider' => 'whatify',
            'whatify_base_url' => 'https://whatify.in/api/v1/external',
            'whatify_account_id' => 1,
            'whatify_mode' => 'template',
            'whatify_template_name' => 'school_submission_alert',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/admin/settings/test-integration', [
            'target' => 'whatsapp_send',
            'test_phone' => '9876543210',
        ])->assertRedirect(route('admin.settings', ['tab' => 'whatsapp']))
            ->assertSessionHas('success');

        Http::assertSent(function (HttpClientRequest $req) {
            return $req->url() === 'https://whatify.in/api/v1/external/send-template'
                && $req->hasHeader('X-API-Key', 'wfy_live_secret_key_987654')
                && ($req['template_name'] ?? '') === 'school_submission_alert'
                && is_array($req['body_params'] ?? null)
                && count($req['body_params']) === 3;
        });

        // 6. Google Drive & Google SSO Super Admin toggle (disabling SSO hides button on /login)
        $this->actingAs($admin)->post('/admin/settings', [
            'settings_section' => 'google',
            'google_drive_root_folder_id' => '1ExcelliaRootCustom2026',
            'google_drive_root_folder_name' => 'EXCELLIA ACADEMIC VAULT',
            // google_sso_enabled unchecked
        ])->assertRedirect(route('admin.settings', ['tab' => 'google']));

        $this->post('/logout');
        $this->get('/login')->assertOk()->assertDontSee('Sign in with Google Workspace');
    }

    public function test_openrouter_ai_enable_disable_by_super_admin_and_work_saver_endpoints(): void
    {
        $admin = User::query()->where('email', 'admin@excelliaschool.edu.in')->firstOrFail();
        $coord = User::query()->where('email', 'coord.senior@excelliaschool.edu.in')->firstOrFail();
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $task = DocumentTask::query()
            ->where('assigned_teacher_id', $ananya->id)
            ->where('workflow_status', 'NOT_STARTED')
            ->firstOrFail();

        // 1. By default AI is disabled: Teacher & Coordinator pages hide AI buttons and /ai/assist returns 403
        $this->actingAs($ananya)->get("/tasks/{$task->id}")
            ->assertOk()
            ->assertDontSee('AI Help: Suggest Outline & Note');

        $this->actingAs($ananya)->postJson('/ai/assist', [
            'action' => 'teacher_outline',
            'task_title' => $task->title,
        ])->assertStatus(403);

        // 2. Super Admin enables OpenRouter AI, saves encrypted key & model
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'id' => 'gen-excellia-1',
                'model' => 'openai/gpt-4o-mini',
                'choices' => [
                    [
                        'message' => [
                            'role' => 'assistant',
                            'content' => '1. Learning Objectives\n2. Warm-up & NCERT Examples\n3. Practice Worksheet',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($admin)->post('/admin/settings', [
            'settings_section' => 'ai',
            'ai_openrouter_enabled' => '1',
            'ai_allow_teachers' => '1',
            'ai_allow_coordinators' => '1',
            'openrouter_base_url' => 'https://openrouter.ai/api/v1',
            'openrouter_api_key' => 'sk-or-v1-excellia-secret-key',
            'openrouter_model' => 'openai/gpt-4o-mini',
        ])->assertRedirect(route('admin.settings', ['tab' => 'ai']));

        $this->assertSame('sk-or-v1-excellia-secret-key', SystemSetting::getSecret('openrouter_api_key'));

        // Test connection button works
        $this->actingAs($admin)->post('/admin/settings/test-integration', [
            'target' => 'openrouter_ai',
        ])->assertRedirect(route('admin.settings', ['tab' => 'ai']))
            ->assertSessionHas('success');

        // 3. Now Teacher & Coordinator see AI buttons and get real OpenRouter work-saving drafts!
        $this->actingAs($ananya)->get("/tasks/{$task->id}")
            ->assertOk()
            ->assertSee('AI Help: Suggest Outline & Note', false);

        $this->actingAs($ananya)->postJson('/ai/assist', [
            'action' => 'teacher_outline',
            'task_title' => $task->title,
            'class_name' => 'Class X',
            'subject' => 'Mathematics',
            'period' => 'November 2026',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('source', 'openrouter');

        $this->actingAs($coord)->postJson('/ai/assist', [
            'action' => 'task_instructions',
            'document_type' => 'Lesson Plans',
            'class_name' => 'Class X',
            'subject' => 'Mathematics',
            'period' => 'December 2026',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        // 4. Super Admin turns off AI for Teachers only -> Teacher blocked, Coordinator still allowed!
        $this->actingAs($admin)->post('/admin/settings', [
            'settings_section' => 'ai',
            'ai_openrouter_enabled' => '1',
            'ai_allow_coordinators' => '1',
            'openrouter_model' => 'openai/gpt-4o-mini',
            // ai_allow_teachers unchecked
        ])->assertRedirect();

        $this->actingAs($ananya)->postJson('/ai/assist', [
            'action' => 'teacher_outline',
        ])->assertStatus(403);

        $this->actingAs($coord)->postJson('/ai/assist', [
            'action' => 'review_feedback',
            'decision' => 'RETURN',
            'task_title' => $task->title,
            'teacher_name' => 'Ananya Rao',
        ])->assertOk();
    }

    public function test_super_admin_full_crud_masters_roles_scopes_academics_doc_types_and_mappings(): void
    {
        $admin = User::query()->where('email', 'admin@excelliaschool.edu.in')->firstOrFail();
        $coord = User::query()->where('email', 'coord.primary@excelliaschool.edu.in')->firstOrFail();
        $year = AcademicYear::current();

        // 1. Super Admin Dashboard highlights Super Admin Control Center
        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Super Admin Control Center');

        // 2. Role Master CRUD: Create -> Update -> Delete custom role; protect core role
        $this->actingAs($admin)->post('/admin/roles', [
            'key' => 'exam_cell',
            'name' => 'Examination Cell',
            'description' => 'Coordinates term papers',
        ])->assertRedirect();

        $customRole = Role::query()->where('key', 'EXAM_CELL')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/roles/{$customRole->id}", [
            'name' => 'Central Examination Cell',
            'description' => 'Updated description',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertSame('Central Examination Cell', $customRole->fresh()->name);

        $this->actingAs($admin)->delete("/admin/roles/{$customRole->id}")->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $customRole->id]);

        $superAdminRole = Role::query()->where('key', Role::SUPER_ADMIN)->firstOrFail();
        $this->actingAs($admin)->delete("/admin/roles/{$superAdminRole->id}")->assertSessionHas('error');

        // 3. Scope Mapping Master CRUD: Create -> Edit -> Delete
        $this->actingAs($admin)->post('/admin/scopes', [
            'user_id' => $coord->id,
            'academic_year_id' => $year->id,
            'scope_type' => 'STAGE',
            'scope_value' => 'MIDDLE',
            'can_review' => '1',
        ])->assertRedirect();

        $scope = UserScopeAssignment::query()->where('user_id', $coord->id)->where('scope_value', 'MIDDLE')->firstOrFail();
        $this->actingAs($admin)->patch("/admin/scopes/{$scope->id}", [
            'can_review' => '1',
            'can_access_restricted' => '1',
            'is_active' => '1',
        ])->assertRedirect();
        $this->assertTrue($scope->fresh()->can_access_restricted);

        $this->actingAs($admin)->delete("/admin/scopes/{$scope->id}")->assertRedirect();
        $this->assertDatabaseMissing('user_scope_assignments', ['id' => $scope->id]);

        // 4. Academic Masters Tabs & Safe Delete CRUD (Year, Class, Section, Subject, DocType, EscalationRule)
        foreach (['allocations', 'years', 'classes', 'sections', 'subjects'] as $tab) {
            $this->actingAs($admin)->get("/admin/academics?tab={$tab}")->assertOk();
        }
        foreach (['general', 'email', 'whatsapp', 'google', 'ai', 'rules'] as $sTab) {
            $this->actingAs($admin)->get("/admin/settings?tab={$sTab}")->assertOk();
        }

        $this->actingAs($admin)->post('/admin/classes', [
            'code' => 'C_FOUNDATION',
            'name' => 'Foundation Bridge',
            'display_order' => 25,
            'stage' => 'PRIMARY',
        ])->assertRedirect();
        $newCls = SchoolClass::query()->where('code', 'C_FOUNDATION')->firstOrFail();

        $this->actingAs($admin)->post('/admin/sections', [
            'academic_year_id' => $year->id,
            'class_id' => $newCls->id,
            'name' => 'Z',
            'display_order' => 1,
        ])->assertRedirect();
        $newSec = Section::query()->where('class_id', $newCls->id)->firstOrFail();
        $this->actingAs($admin)->delete("/admin/sections/{$newSec->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/admin/classes/{$newCls->id}")->assertRedirect();
        $this->assertDatabaseMissing('classes', ['id' => $newCls->id]);

        $this->actingAs($admin)->post('/admin/subjects', [
            'code' => 'ART_TEMP',
            'name' => 'Fine Arts Elective',
            'category' => 'Arts',
        ])->assertRedirect();
        $newSub = Subject::query()->where('code', 'ART_TEMP')->firstOrFail();
        $this->actingAs($admin)->delete("/admin/subjects/{$newSub->id}")->assertRedirect();
        $this->assertDatabaseMissing('subjects', ['id' => $newSub->id]);

        $this->actingAs($admin)->post('/admin/document-types', [
            'code' => 'TEMP_PORTFOLIO',
            'name' => 'Student Portfolio Log',
            'folder_code' => '09',
            'folder_name' => '09 – Student Portfolio',
            'extensions_csv' => 'pdf,docx',
            'max_file_size_mb' => 15,
            'sensitivity_level' => 'STANDARD',
        ])->assertRedirect();
        $newDoc = DocumentType::query()->where('code', 'TEMP_PORTFOLIO')->firstOrFail();
        $this->actingAs($admin)->delete("/admin/document-types/{$newDoc->id}")->assertRedirect();
        $this->assertDatabaseMissing('document_types', ['id' => $newDoc->id]);

        $mapping = DriveFolderMapping::query()->firstOrFail();
        $this->actingAs($admin)->delete("/admin/drive-mappings/{$mapping->id}")->assertRedirect();
        $this->assertDatabaseMissing('drive_folder_mappings', ['id' => $mapping->id]);

        $escRule = EscalationRule::query()->firstOrFail();
        $this->actingAs($admin)->delete("/admin/escalation-rules/{$escRule->id}")->assertRedirect();
        $this->assertDatabaseMissing('escalation_rules', ['id' => $escRule->id]);
    }
}
