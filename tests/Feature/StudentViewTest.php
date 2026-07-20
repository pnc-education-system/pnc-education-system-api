<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationCategory;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\Permission;
use App\Models\RecordAttachment;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentViewTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private SelectionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        $this->batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
    }

    public function test_list_students(): void
    {
        Student::create([
            'student_id_no' => 'ST001',
            'full_name' => 'Test',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => 'Pending',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/students');

        $response->assertStatus(200);
    }

    public function test_list_students_requires_permission(): void
    {
        $response = $this->getJson('/api/v1/students');
        $response->assertStatus(401);
    }

    public function test_show_student_with_linked_records_and_evaluations(): void
    {
        // Create a student
        $student = Student::create([
            'student_id_no' => 'ST001',
            'full_name' => 'Test Student',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'phone' => '012345678',
            'email' => 'student@example.com',
            'province' => 'Phnom Penh',
            'high_school' => 'ABC High School',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => Student::STATUS_PENDING,
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        // Create a student record with an attachment
        $record = StudentRecord::create([
            'student_id' => $student->id,
            'category' => 'academic',
            'title' => 'Semester Report',
            'description' => 'First semester grades',
            'record_date' => '2025-06-15',
            'created_by' => $this->user->id,
        ]);

        RecordAttachment::create([
            'student_record_id' => $record->id,
            'file_path' => 'records/report.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->user->id,
        ]);

        // Create an evaluation form with a category and question
        $form = EvaluationForm::create([
            'name' => 'Midterm Evaluation',
            'description' => 'Midterm evaluation form',
            'is_active' => true,
        ]);

        $category = EvaluationCategory::create([
            'evaluation_form_id' => $form->id,
            'name' => 'General',
            'sort_order' => 1,
        ]);

        $question = EvaluationQuestion::create([
            'template_id' => $form->id,
            'category_id' => $category->id,
            'question_text' => 'Overall performance',
            'max_score' => 100,
            'sort_order' => 1,
        ]);

        // Create an evaluation with an answer
        $evaluation = Evaluation::create([
            'student_id' => $student->id,
            'evaluation_form_id' => $form->id,
            'evaluation_period' => '2025-Q1',
            'total_score' => 85.50,
            'status' => 'Draft',
            'submitted_at' => now(),
            'reviewed_by' => $this->user->id,
        ]);

        EvaluationAnswer::create([
            'evaluation_id' => $evaluation->id,
            'question_id' => $question->id,
            'score' => 85.50,
            'comment' => 'Good performance overall',
        ]);

        // Call GET /students/{id}
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/students/{$student->id}");

        // Assert basic response structure
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Student retrieved successfully')
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.student_id_no', 'ST001')
            ->assertJsonPath('data.full_name', 'Test Student')
            ->assertJsonPath('data.selection_batch.id', $this->batch->id);

        // Assert linked records
        $response->assertJsonStructure([
            'data' => [
                'records' => [
                    '*' => [
                        'id',
                        'category',
                        'title',
                        'description',
                        'record_date',
                        'created_by',
                        'created_at',
                        'attachments' => [
                            '*' => [
                                'id',
                                'file_path',
                                'file_type',
                                'file_size',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonCount(1, 'data.records');
        $response->assertJsonPath('data.records.0.title', 'Semester Report');
        $response->assertJsonPath('data.records.0.category', 'academic');
        $response->assertJsonPath('data.records.0.description', 'First semester grades');
        $response->assertJsonPath('data.records.0.record_date', '2025-06-15');
        $response->assertJsonPath('data.records.0.attachments.0.file_path', 'records/report.pdf');
        $response->assertJsonPath('data.records.0.attachments.0.file_type', 'application/pdf');
        $response->assertJsonPath('data.records.0.attachments.0.file_size', 1024);

        // Assert linked evaluations
        $response->assertJsonStructure([
            'data' => [
                'evaluations' => [
                    '*' => [
                        'id',
                        'evaluation_form_id',
                        'evaluation_period',
                        'total_score',
                        'status',
                        'submitted_at',
                        'evaluation_form' => [
                            'id',
                            'name',
                        ],
                        'answers' => [
                            '*' => [
                                'id',
                                'question_id',
                                'score',
                                'comment',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonCount(1, 'data.evaluations');
        $response->assertJsonPath('data.evaluations.0.evaluation_period', '2025-Q1');
        $response->assertJsonPath('data.evaluations.0.total_score', '85.50');
        $response->assertJsonPath('data.evaluations.0.status', 'Draft');
        $response->assertJsonPath('data.evaluations.0.evaluation_form.name', 'Midterm Evaluation');
        $response->assertJsonPath('data.evaluations.0.answers.0.score', '85.50');
        $response->assertJsonPath('data.evaluations.0.answers.0.comment', 'Good performance overall');

        // Assert cards (should be empty array since student has no cards)
        $response->assertJsonCount(0, 'data.cards');

        // Assert enrollment status histories (should be empty array since student hasn't changed status)
        $response->assertJsonCount(0, 'data.enrollment_status_histories');
    }
}
