<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementDashboardChartsAndCustomMisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_management_director_and_principal_see_visual_charts_and_scoped_access_is_enforced(): void
    {
        $director = User::query()->where('email', 'director@excelliaschool.edu.in')->firstOrFail();
        $principal = User::query()->where('email', 'principal@excelliaschool.edu.in')->firstOrFail();
        $primaryCoord = User::query()->where('email', 'coord.primary@excelliaschool.edu.in')->firstOrFail();
        $teacher = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();

        // 1. Director (Management) sees visual charts on /dashboard and full 6-chart gallery on /management-mis
        $this->actingAs($director)->get('/dashboard')
            ->assertOk()
            ->assertSee('dashStatusPieChart', false)
            ->assertSee('dashStageBarChart', false)
            ->assertSee('Executive Charts & Custom MIS', false);

        $this->actingAs($director)->get('/management-mis')
            ->assertOk()
            ->assertSee('Executive Management Dashboard & Custom MIS')
            ->assertSee('mgmtStatusPieCanvas', false)
            ->assertSee('mgmtStageBarCanvas', false)
            ->assertSee('mgmtClassBarCanvas', false)
            ->assertSee('mgmtDocHorizCanvas', false)
            ->assertSee('mgmtTeacherBarCanvas', false)
            ->assertSee('mgmtTrendCanvas', false)
            ->assertSee('Whole-School Visibility', false);

        // 2. Principal also has whole-school management MIS access
        $this->actingAs($principal)->get('/management-mis')
            ->assertOk()
            ->assertSee('Whole-School Visibility', false);

        // 3. Coordinator sees Management MIS strictly scoped to their assigned wing (Primary sees Class IV, NOT Class X)
        $coordResp = $this->actingAs($primaryCoord)->get('/management-mis?group_by=class')
            ->assertOk()
            ->assertSee('Assigned Wing Scope', false);
        $coordResp->assertDontSee('Class X - A');

        // 4. Teacher cannot access Management MIS
        $this->actingAs($teacher)->get('/management-mis')->assertStatus(403);
    }

    public function test_select_and_do_custom_mis_pivots_custom_columns_and_csv_export(): void
    {
        $director = User::query()->where('email', 'director@excelliaschool.edu.in')->firstOrFail();

        // 1. Test every Group-By / Pivot mode renders cleanly
        foreach (['tasks', 'stage', 'class', 'teacher', 'subject', 'document_type', 'status', 'reviewer', 'escalation'] as $groupMode) {
            $this->actingAs($director)
                ->get("/management-mis?group_by={$groupMode}&chart_type=pie")
                ->assertOk();
        }

        // 2. Custom column selection in Task Register mode + Custom CSV export
        $customCols = ['class_name', 'teacher_name', 'teacher_phone', 'document_type', 'overdue_days'];
        $this->actingAs($director)
            ->get('/management-mis?' . http_build_query([
                'group_by' => 'tasks',
                'columns' => $customCols,
            ]))
            ->assertOk()
            ->assertSee('Teacher Phone');

        $csvResp = $this->actingAs($director)
            ->get('/management-mis/export?' . http_build_query([
                'group_by' => 'tasks',
                'columns' => $customCols,
            ]))
            ->assertOk();

        $csvContent = $csvResp->streamedContent();
        $this->assertStringContainsString('Class,"Teacher Name","Teacher Phone","Document Type","Overdue Days"', $csvContent);
        $this->assertStringNotContainsString('Emp Code', $csvContent);

        // 3. Grouped CSV export works for Teacher-wise completion threshold (< 100%)
        $groupedCsv = $this->actingAs($director)
            ->get('/management-mis/export?group_by=teacher&completion_op=lt&completion_val=100')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Summary by Teacher', $groupedCsv);
        $this->assertStringContainsString('Completion %', $groupedCsv);
    }

    public function test_mentioned_query_natural_language_mis_saved_presets_and_api(): void
    {
        $director = User::query()->where('email', 'director@excelliaschool.edu.in')->firstOrFail();

        // 1. Plain-English "Mentioned Query": Class + Subject + Status
        $this->actingAs($director)
            ->get('/management-mis?' . http_build_query([
                'mentioned_query' => 'Class X Mathematics by teacher pie chart',
            ]))
            ->assertOk()
            ->assertSee('Class: Class X')
            ->assertSee('Subject: Mathematics')
            ->assertSee('Grouped By: Summary by Teacher')
            ->assertSee('Chart: Pie Chart')
            ->assertSee('Ananya Rao');

        // 2. Plain-English "Mentioned Query": Completion threshold + Late filter
        $this->actingAs($director)
            ->get('/management-mis?' . http_build_query([
                'mentioned_query' => 'Teachers with completion below 90% doughnut chart',
            ]))
            ->assertOk()
            ->assertSee('Completion < 90%')
            ->assertSee('Chart: Doughnut Chart');

        // 3. Save & Delete Custom MIS Preset
        $this->actingAs($director)
            ->post('/management-mis/presets', [
                'preset_name' => 'Monday Board Review',
                'query_payload' => 'group_by=stage&chart_type=bar',
            ])
            ->assertRedirect();

        $page = $this->actingAs($director)->get('/management-mis')->assertOk();
        $page->assertSee('Monday Board Review');

        $presets = \App\Models\SystemSetting::getValue('management_mis_saved_presets', []);
        $this->assertCount(1, $presets);

        $this->actingAs($director)
            ->delete('/management-mis/presets/' . $presets[0]['id'])
            ->assertRedirect();

        $this->assertCount(0, \App\Models\SystemSetting::getValue('management_mis_saved_presets', []));

        // 4. JSON API endpoint /api/reports/management-mis supports Mentioned Query too
        $this->actingAs($director)
            ->getJson('/api/reports/management-mis?mentioned_query=Primary+wing+overdue+by+class')
            ->assertOk()
            ->assertJsonPath('data.custom_mis.group_by', 'class')
            ->assertJsonPath('data.custom_mis.params.stage', 'PRIMARY')
            ->assertJsonPath('data.custom_mis.params.status', 'OVERDUE');
    }
}
