<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentViewTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true]);
        $this->token = JWTAuth::fromUser($user);
        $batch = SelectionBatch::create(['name' => 'Batch', 'year' => 2025]);
    }

    public function test_list_students(): void
    {
        Student::create(['student_id_no' => 'ST001', 'full_name' => 'Test', 'gender' => 'Male', 'dob' => '2000-01-01', 'selection_batch_id' => 1, 'enrollment_status' => 'Pending', 'intake_year' => 2025, 'created_by' => 1]);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/students');
        $response->assertStatus(200);
    }

    public function test_list_students_requires_permission(): void
    {
        $response = $this->getJson('/api/v1/students');
        $response->assertStatus(401);
    }
}
