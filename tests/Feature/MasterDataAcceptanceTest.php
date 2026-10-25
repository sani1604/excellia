<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\DocumentTask;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeachingAllocation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function loginApi(string $email, string $password = 'Excellia@2026'): string
    {
        $res = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertOk();

        return $res->json('token');
    }

    public function test_admin_creates_and_deactivates_users_and_flags_teacher_allocations(): void
    {
        $token = $this->loginApi('admin@excelliaschool.edu.in');
        $teacherRole = Role::query()->where('key', Role::TEACHER)->firstOrFail();

        $createRes = $this->withToken($token)->postJson('/api/users', [
            'employee_code' => 'EXC-TCH-999',
            'full_name' => 'Vikram Malhotra',
            'email' => '  VIKRAM.MALHOTRA@ExcelliaSchool.edu.in ',
            'primary_role_id' => $teacherRole->id,
            'password' => 'Teacher@2026',
        ])->assertCreated();

        $this->assertSame('vikram.malhotra@excelliaschool.edu.in', $createRes->json('data.email'));

        // Deactivate an active teacher who has allocations & open tasks
        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $deactRes = $this->withToken($token)
            ->postJson("/api/users/{$ananya->id}/deactivate", ['reason' => 'Sabbatical leave'])
            ->assertOk();

        $this->assertGreaterThan(0, $deactRes->json('flagged_allocations'));
        $this->assertTrue($ananya->fresh()->needs_reassignment_review);
        $this->assertTrue(
            TeachingAllocation::query()
                ->where('teacher_user_id', $ananya->id)
                ->where('needs_reassignment', true)
                ->exists()
        );

        // Inactive user cannot log in
        $this->postJson('/api/auth/login', [
            'email' => 'ananya.rao@excelliaschool.edu.in',
            'password' => 'Excellia@2026',
        ])->assertStatus(403);
    }

    public function test_classes_pg_to_xii_and_sections_and_single_current_academic_year(): void
    {
        $token = $this->loginApi('admin@excelliaschool.edu.in');

        // Verify PG, Nursery, KG and Class I - Class XII (15 classes)
        $classesRes = $this->withToken($token)->getJson('/api/classes')->assertOk();
        $names = collect($classesRes->json('data'))->pluck('name')->all();
        $this->assertContains('PG', $names);
        $this->assertContains('Nursery', $names);
        $this->assertContains('KG', $names);
        $this->assertContains('Class I', $names);
        $this->assertContains('Class XII', $names);
        $this->assertCount(15, $names);

        $year2026 = AcademicYear::query()->where('name', '2026-27')->firstOrFail();
        $classX = SchoolClass::query()->where('code', 'C10')->firstOrFail();

        // Add Section C for 2026-27 Class X
        $this->withToken($token)->postJson('/api/sections', [
            'academic_year_id' => $year2026->id,
            'class_id' => $classX->id,
            'name' => 'C',
            'display_order' => 3,
        ])->assertCreated();

        // Duplicate section (2026-27, Class X, C) rejected
        $this->withToken($token)->postJson('/api/sections', [
            'academic_year_id' => $year2026->id,
            'class_id' => $classX->id,
            'name' => 'c',
        ])->assertStatus(422);

        // Switching current academic year keeps only one current year and preserves past deadlines
        $sampleTask = DocumentTask::query()->firstOrFail();
        $originalDue = $sampleTask->due_at->toIso8601String();

        $this->withToken($token)->postJson('/api/academic-years', [
            'name' => '2028-29',
            'start_date' => '2028-04-01',
            'end_date' => '2029-03-31',
            'is_current' => true,
            'status' => 'ACTIVE',
        ])->assertCreated();

        $this->assertSame(1, AcademicYear::query()->where('is_current', true)->count());
        $this->assertSame('2028-29', AcademicYear::current()->name);
        $this->assertSame($originalDue, $sampleTask->fresh()->due_at->toIso8601String());
    }

    public function test_teaching_allocation_rules_independent_class_teacher_multi_teacher_and_invalid_rejections(): void
    {
        $token = $this->loginApi('admin@excelliaschool.edu.in');
        $year = AcademicYear::current();
        $classIX = SchoolClass::query()->where('code', 'C9')->firstOrFail();
        $classX = SchoolClass::query()->where('code', 'C10')->firstOrFail();
        $secIXA = Section::query()->where('academic_year_id', $year->id)->where('class_id', $classIX->id)->where('name', 'A')->firstOrFail();
        $secXA = Section::query()->where('academic_year_id', $year->id)->where('class_id', $classX->id)->where('name', 'A')->firstOrFail();
        $math = Subject::query()->where('code', 'MAT')->firstOrFail();
        $csc = Subject::query()->where('code', 'CSC')->firstOrFail();

        $ananya = User::query()->where('email', 'ananya.rao@excelliaschool.edu.in')->firstOrFail();
        $priya = User::query()->where('email', 'priya.menon@excelliaschool.edu.in')->firstOrFail();

        // 1. Reject mismatched section & class
        $this->withToken($token)->postJson('/api/teaching-allocations', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secXA->id, // belongs to Class X, not IX!
            'subject_id' => $math->id,
            'teacher_user_id' => $ananya->id,
        ])->assertStatus(422);

        // 2. Independent Class Teacher assignment (nullable subject_id when is_class_teacher = true)
        $this->withToken($token)->postJson('/api/teaching-allocations', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secIXA->id,
            'subject_id' => null,
            'teacher_user_id' => $ananya->id,
            'is_class_teacher' => true,
        ])->assertCreated();

        // Reject second primary class teacher in same section when co-class-teachers disabled
        $this->withToken($token)->postJson('/api/teaching-allocations', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secIXA->id,
            'subject_id' => $math->id,
            'teacher_user_id' => $priya->id,
            'is_class_teacher' => true,
        ])->assertStatus(422);

        // 3. Multiple teachers CAN teach the same subject in the same class/section when approved
        $this->withToken($token)->postJson('/api/teaching-allocations', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secIXA->id,
            'subject_id' => $math->id,
            'teacher_user_id' => $ananya->id,
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/teaching-allocations', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secIXA->id,
            'subject_id' => $math->id,
            'teacher_user_id' => $priya->id,
        ])->assertCreated();

        // Exact duplicate for same teacher/subject/section is rejected
        $this->withToken($token)->postJson('/api/teaching-allocations', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secIXA->id,
            'subject_id' => $math->id,
            'teacher_user_id' => $ananya->id,
        ])->assertStatus(422);

        // 4. Reject Drive folder mapping for a subject NOT taught in Class IX-A (CSC has no allocation in IX-A)
        $this->withToken($token)->postJson('/api/drive-folder-mappings', [
            'academic_year_id' => $year->id,
            'class_id' => $classIX->id,
            'section_id' => $secIXA->id,
            'subject_id' => $csc->id,
            'drive_folder_id' => '1ValidDriveFolderId12345',
            'drive_folder_name' => '13 – Class IX / Section A / Computer Science',
        ])->assertStatus(422);

        // 5. Reject executable extensions in Document Type Master
        $this->withToken($token)->postJson('/api/document-types', [
            'code' => 'BAD_TYPE',
            'name' => 'Bad Type',
            'folder_code' => '09',
            'folder_name' => '09 – Bad',
            'allowed_extensions' => ['pdf', 'exe'],
            'max_file_size_mb' => 10,
            'sensitivity_level' => 'STANDARD',
        ])->assertStatus(422);
    }
}
