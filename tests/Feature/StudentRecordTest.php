<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentRecordTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;
    private string $managerToken;
    private string $viewerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $recordsView = Permission::create(['name' => 'View Records', 'slug' => 'records.view', 'module' => 'records']);
        $recordsManage = Permission::create(['name' => 'Manage Records', 'slug' => 'records.manage', 'module' => 'records']);

        $managerRole = Role::create(['name' => 'Records Manager', 'slug' => 'records_manager']);
        $viewerRole = Role::create(['name' => 'Records Viewer', 'slug' => 'records_viewer']);

        $managerRole->permissions()->sync([$recordsView->id, $recordsManage->id]);
        $viewerRole->permissions()->sync([$recordsView->id]);

        $manager = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
            'role_id' => $managerRole->id,
            'is_active' => true,
        ]);

        $viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => Hash::make('password'),
            'role_id' => $viewerRole->id,
            'is_active' => true,
        ]);

        $batch = SelectionBatch::create(['name' => 'Batch 2026', 'year' => 2026]);

        $this->student = Student::create([
            'student_id_no' => 'PNC2026001',
            'full_name' => 'Test Student',
            'gender' => 'Female',
            'dob' => '2008-01-15',
            'phone' => '012345678',
            'email' => 'student@example.com',
            'province' => 'Phnom Penh',
            'high_school' => 'Test High School',
            'selection_batch_id' => $batch->id,
            'enrollment_status' => 'Pending',
            'intake_year' => 2026,
            'created_by' => $manager->id,
        ]);

        $this->managerToken = JWTAuth::fromUser($manager);
        $this->viewerToken = JWTAuth::fromUser($viewer);
    }

    public function test_manager_can_create_record_with_defined_category(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->postJson("/api/v1/students/{$this->student->id}/records", [
                'category' => StudentRecord::CATEGORY_NOTE,
                'title' => 'Mentor note',
                'description' => 'Student is progressing well.',
                'record_date' => '2026-07-23',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', StudentRecord::CATEGORY_NOTE)
            ->assertJsonPath('data.title', 'Mentor note');

        $this->assertDatabaseHas('student_records', [
            'student_id' => $this->student->id,
            'category' => StudentRecord::CATEGORY_NOTE,
            'title' => 'Mentor note',
        ]);
    }

    public function test_record_category_must_be_defined_enum_value(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->postJson("/api/v1/students/{$this->student->id}/records", [
                'category' => 'discipline',
                'title' => 'Invalid category',
                'description' => 'This should fail.',
                'record_date' => '2026-07-23',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422)
            ->assertJsonPath('error.message', 'The selected category is invalid.');
    }

    public function test_viewer_can_get_one_record_by_id(): void
    {
        $record = StudentRecord::create([
            'student_id' => $this->student->id,
            'category' => StudentRecord::CATEGORY_ACHIEVEMENT,
            'title' => 'Competition result',
            'description' => 'Placed second.',
            'record_date' => '2026-07-23',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
            ->getJson("/api/v1/students/{$this->student->id}/records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.category', StudentRecord::CATEGORY_ACHIEVEMENT);
    }

    public function test_missing_record_returns_friendly_error(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
            ->getJson("/api/v1/students/{$this->student->id}/records/999999")
            ->assertNotFound()
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Student record not found');
    }

    public function test_viewer_cannot_create_update_or_delete_records(): void
    {
        $record = StudentRecord::create([
            'student_id' => $this->student->id,
            'category' => StudentRecord::CATEGORY_GENERAL,
            'title' => 'Existing record',
            'description' => 'Original description.',
            'record_date' => '2026-07-23',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
            ->postJson("/api/v1/students/{$this->student->id}/records", [
                'category' => StudentRecord::CATEGORY_NOTE,
                'title' => 'Forbidden create',
                'description' => 'Nope.',
                'record_date' => '2026-07-23',
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
            ->putJson("/api/v1/students/{$this->student->id}/records/{$record->id}", [
                'category' => StudentRecord::CATEGORY_NOTE,
                'title' => 'Forbidden update',
                'description' => 'Nope.',
                'record_date' => '2026-07-23',
            ])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$this->viewerToken}")
            ->deleteJson("/api/v1/students/{$this->student->id}/records/{$record->id}")
            ->assertForbidden();
    }

    public function test_manager_can_update_and_delete_record(): void
    {
        $record = StudentRecord::create([
            'student_id' => $this->student->id,
            'category' => StudentRecord::CATEGORY_GENERAL,
            'title' => 'Existing record',
            'description' => 'Original description.',
            'record_date' => '2026-07-23',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->putJson("/api/v1/students/{$this->student->id}/records/{$record->id}", [
                'category' => StudentRecord::CATEGORY_INCIDENT,
                'title' => 'Updated record',
                'description' => 'Updated description.',
                'record_date' => '2026-07-24',
            ])
            ->assertOk()
            ->assertJsonPath('data.category', StudentRecord::CATEGORY_INCIDENT);

        $this->withHeader('Authorization', "Bearer {$this->managerToken}")
            ->deleteJson("/api/v1/students/{$this->student->id}/records/{$record->id}")
            ->assertOk();

        $this->assertDatabaseMissing('student_records', ['id' => $record->id]);
    }
}
