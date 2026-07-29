<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\StudentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $permissions = [
            Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']),
            Permission::create(['name' => 'View Records', 'slug' => 'records.view', 'module' => 'records']),
            Permission::create(['name' => 'View Evaluations', 'slug' => 'evaluation.view', 'module' => 'evaluation']),
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
    }

    /** @test */
    public function it_returns_dashboard_aggregates()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/aggregates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'demographics' => [
                        'total_students',
                        'by_enrollment_status',
                        'by_gender',
                        'by_province',
                        'by_intake_year',
                    ],
                    'evaluation_stats' => [
                        'total_evaluations',
                        'average_score',
                        'by_status',
                        'by_period',
                        'students_with_evaluations',
                    ],
                    'record_summary' => [
                        'total_records',
                        'by_category',
                        'recent_records_30_days',
                        'students_with_records',
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_returns_correct_demographics_data()
    {
        // Create test students
        $batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);

        Student::create([
            'student_id_no' => 'STU001',
            'full_name' => 'Student 1',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'phone' => '012345678',
            'email' => 'student1@test.com',
            'province' => 'Phnom Penh',
            'high_school' => 'Test High School',
            'selection_batch_id' => $batch->id,
            'enrollment_status' => 'Enrolled',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        Student::create([
            'student_id_no' => 'STU002',
            'full_name' => 'Student 2',
            'gender' => 'Female',
            'dob' => '2000-01-01',
            'phone' => '012345679',
            'email' => 'student2@test.com',
            'province' => 'Siem Reap',
            'high_school' => 'Test High School',
            'selection_batch_id' => $batch->id,
            'enrollment_status' => 'Pending',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/aggregates');

        $data = $response->json('data.demographics');

        $this->assertEquals(2, $data['total_students']);
        $this->assertArrayHasKey('Enrolled', $data['by_enrollment_status']);
        $this->assertArrayHasKey('Pending', $data['by_enrollment_status']);
        $this->assertArrayHasKey('Male', $data['by_gender']);
        $this->assertArrayHasKey('Female', $data['by_gender']);
        $this->assertArrayHasKey('Phnom Penh', $data['by_province']);
        $this->assertArrayHasKey('Siem Reap', $data['by_province']);
        $this->assertArrayHasKey('2025', $data['by_intake_year']);
    }

    /** @test */
    public function it_returns_correct_evaluation_stats()
    {
        $batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
        $student = Student::create([
            'student_id_no' => 'STU001',
            'full_name' => 'Student 1',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'phone' => '012345678',
            'email' => 'student1@test.com',
            'province' => 'Phnom Penh',
            'high_school' => 'Test High School',
            'selection_batch_id' => $batch->id,
            'enrollment_status' => 'Enrolled',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        $form = EvaluationForm::create([
            'name' => 'Test Form',
            'description' => 'Test',
            'is_active' => true,
        ]);

        Evaluation::create([
            'student_id' => $student->id,
            'evaluation_form_id' => $form->id,
            'evaluation_period' => 'Q1 2025',
            'total_score' => 85.00,
            'status' => 'Approved',
            'submitted_at' => now(),
            'reviewed_by' => $this->user->id,
        ]);

        Evaluation::create([
            'student_id' => $student->id,
            'evaluation_form_id' => $form->id,
            'evaluation_period' => 'Q2 2025',
            'total_score' => 90.00,
            'status' => 'Submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/aggregates');

        $data = $response->json('data.evaluation_stats');

        $this->assertEquals(2, $data['total_evaluations']);
        $this->assertEquals(87.5, $data['average_score']);
        $this->assertArrayHasKey('Approved', $data['by_status']);
        $this->assertArrayHasKey('Submitted', $data['by_status']);
        $this->assertArrayHasKey('Q1 2025', $data['by_period']);
        $this->assertArrayHasKey('Q2 2025', $data['by_period']);
        $this->assertEquals(1, $data['students_with_evaluations']);
    }

    /** @test */
    public function it_returns_correct_record_summary()
    {
        $batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
        $student = Student::create([
            'student_id_no' => 'STU001',
            'full_name' => 'Student 1',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'phone' => '012345678',
            'email' => 'student1@test.com',
            'province' => 'Phnom Penh',
            'high_school' => 'Test High School',
            'selection_batch_id' => $batch->id,
            'enrollment_status' => 'Enrolled',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        StudentRecord::create([
            'student_id' => $student->id,
            'category' => 'achievement',
            'title' => 'Test Achievement',
            'description' => 'Test description',
            'record_date' => now(),
            'created_by' => $this->user->id,
        ]);

        StudentRecord::create([
            'student_id' => $student->id,
            'category' => 'incident',
            'title' => 'Test Incident',
            'description' => 'Test description',
            'record_date' => now(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/aggregates');

        $data = $response->json('data.record_summary');

        $this->assertEquals(2, $data['total_records']);
        $this->assertArrayHasKey('achievement', $data['by_category']);
        $this->assertArrayHasKey('incident', $data['by_category']);
        $this->assertEquals(2, $data['recent_records_30_days']);
        $this->assertEquals(1, $data['students_with_records']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->getJson('/api/v1/dashboard/aggregates');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_proper_permissions()
    {
        $role = Role::create(['name' => 'User', 'slug' => 'user']);
        $user = User::create([
            'name' => 'User',
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard/aggregates');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_handles_empty_data_gracefully()
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/dashboard/aggregates');

        $data = $response->json('data');

        $this->assertEquals(0, $data['demographics']['total_students']);
        $this->assertEquals(0, $data['evaluation_stats']['total_evaluations']);
        $this->assertEquals(0, $data['evaluation_stats']['average_score']);
        $this->assertEquals(0, $data['record_summary']['total_records']);
    }
}
