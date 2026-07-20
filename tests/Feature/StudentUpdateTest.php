<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private SelectionBatch $batch;
    private SelectionBatch $newBatch;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $permissions = [
            Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']),
            Permission::create(['name' => 'Edit Students', 'slug' => 'students.edit', 'module' => 'students']),
        ];
        $role->permissions()->sync(collect($permissions)->pluck('id'));

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        $this->batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
        $this->newBatch = SelectionBatch::create(['name' => 'Batch 2026', 'year' => 2026]);
    }

    public function test_show_student_returns_editable_fields(): void
    {
        $student = $this->createStudent();

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/students/{$student->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.student_id_no', 'ST0001')
            ->assertJsonPath('data.full_name', 'Original Student')
            ->assertJsonPath('data.selection_batch_id', $this->batch->id)
            ->assertJsonPath('data.enrollment_status', Student::STATUS_PENDING);
    }

    public function test_update_student_modifies_only_selected_student(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent([
            'student_id_no' => 'ST0002',
            'full_name' => 'Other Student',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/students/{$student->id}", [
                'student_id_no' => 'ST9999',
                'full_name' => 'Updated Student',
                'gender' => 'Female',
                'dob' => '2005-01-15',
                'phone' => '012345678',
                'email' => 'updated@example.com',
                'province' => 'Phnom Penh',
                'high_school' => 'ABC High School',
                'selection_batch_id' => $this->newBatch->id,
                'enrollment_status' => Student::STATUS_ENROLLED,
                'intake_year' => 2026,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Student updated successfully')
            ->assertJsonPath('data.student_id_no', 'ST9999')
            ->assertJsonPath('data.full_name', 'Updated Student')
            ->assertJsonPath('data.selection_batch_id', $this->newBatch->id);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'student_id_no' => 'ST9999',
            'full_name' => 'Updated Student',
            'selection_batch_id' => $this->newBatch->id,
            'enrollment_status' => Student::STATUS_ENROLLED,
        ]);

        $this->assertDatabaseHas('students', [
            'id' => $otherStudent->id,
            'student_id_no' => 'ST0002',
            'full_name' => 'Other Student',
        ]);
    }

    public function test_update_rejects_duplicate_student_id(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent(['student_id_no' => 'ST0002']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/students/{$student->id}", [
                'student_id_no' => $otherStudent->student_id_no,
                'full_name' => 'Updated Student',
                'gender' => 'Male',
                'selection_batch_id' => $this->batch->id,
                'enrollment_status' => Student::STATUS_PENDING,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'student_id_no' => 'ST0001',
        ]);
    }

    public function test_update_replaces_student_photo_and_deletes_old_photo(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('students/photos/old.jpg', 'old-photo');
        $student = $this->createStudent(['photo_path' => 'students/photos/old.jpg']);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$student->id}", [
                '_method' => 'PUT',
                'student_id_no' => 'ST0001',
                'full_name' => 'Photo Updated Student',
                'gender' => 'Male',
                'dob' => '2005-01-15',
                'phone' => '012345678',
                'email' => 'photo@example.com',
                'province' => 'Phnom Penh',
                'high_school' => 'ABC High School',
                'selection_batch_id' => $this->batch->id,
                'enrollment_status' => Student::STATUS_PENDING,
                'intake_year' => 2025,
                'photo' => UploadedFile::fake()->image('student.png'),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.full_name', 'Photo Updated Student');

        $student->refresh();

        $this->assertNotNull($student->photo_path);
        $this->assertNotSame('students/photos/old.jpg', $student->photo_path);
        Storage::disk('public')->assertMissing('students/photos/old.jpg');
        Storage::disk('public')->assertExists($student->photo_path);
    }

    private function createStudent(array $overrides = []): Student
    {
        return Student::create(array_merge([
            'student_id_no' => 'ST0001',
            'full_name' => 'Original Student',
            'gender' => 'Male',
            'dob' => '2005-01-15',
            'phone' => '012345678',
            'email' => 'student@example.com',
            'province' => 'Phnom Penh',
            'high_school' => 'ABC High School',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => Student::STATUS_PENDING,
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ], $overrides));
    }
}
