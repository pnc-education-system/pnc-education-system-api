<?php

namespace Tests\Feature;

use App\Models\Evaluation;
use App\Models\EvaluationAnswer;
use App\Models\EvaluationCategory;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
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

class EvaluationReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected SelectionBatch $batch;
    protected EvaluationForm $form;
    protected EvaluationCategory $category;
    protected EvaluationQuestion $question;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        // Create required permissions
        $evalView = Permission::create([
            'name'   => 'View Evaluations',
            'slug'   => 'evaluation.view',
            'module' => 'evaluation',
        ]);
        $role->permissions()->sync([$evalView->id]);

        $this->user = User::create([
            'name'      => 'Report Admin',
            'email'     => 'report@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $role->id,
            'is_active' => true,
        ]);

        $this->token = JWTAuth::fromUser($this->user);

        // Create test data
        $this->batch = SelectionBatch::create([
            'name' => 'Test Batch',
            'year' => 2026,
        ]);

        $this->form = EvaluationForm::create([
            'name'        => 'Test Evaluation',
            'description' => 'Test form for reports',
            'is_active'   => true,
        ]);

        $this->category = $this->form->categories()->create([
            'name'       => 'Communication',
            'sort_order' => 1,
        ]);

        $this->question = $this->category->questions()->create([
            'template_id'   => $this->form->id,
            'question_text' => 'How well do you communicate?',
            'score'         => 10,
            'sort_order'    => 1,
        ]);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function createStudentWithEvaluation(string $studentIdNo, string $name, float $score, string $period = '2026-Q1'): Student
    {
        $student = Student::create([
            'student_id_no'      => $studentIdNo,
            'full_name'          => $name,
            'gender'             => 'Male',
            'dob'                => '2000-01-01',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status'  => 'Enrolled',
            'intake_year'        => 2026,
            'high_school'        => 'Test High School',
            'created_by'         => $this->user->id,
        ]);

        $evaluation = Evaluation::create([
            'student_id'         => $student->id,
            'evaluation_form_id' => $this->form->id,
            'evaluation_period'  => $period,
            'total_score'        => $score,
            'status'             => 'Submitted',
            'submitted_at'       => now(),
        ]);

        EvaluationAnswer::create([
            'evaluation_id' => $evaluation->id,
            'question_id'   => $this->question->id,
            'score'         => $score,
        ]);

        return $student;
    }

    // ──────────────────────────────────────────────
    //  Individual Report
    // ──────────────────────────────────────────────

    public function test_individual_report_returns_pdf_for_valid_student(): void
    {
        $student = $this->createStudentWithEvaluation('STU-001', 'John Doe', 8);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/exports/evaluations/individual/{$student->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // Verify the Content-Disposition header contains 'attachment'
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_individual_report_return_404_for_missing_student(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/individual/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Student not found');
    }

    public function test_individual_report_handles_student_without_evaluations(): void
    {
        $student = Student::create([
            'student_id_no'      => 'STU-NO-EVAL',
            'full_name'          => 'No Eval Student',
            'gender'             => 'Female',
            'dob'                => '2001-01-01',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status'  => 'Pending',
            'intake_year'        => 2026,
            'high_school'        => 'Test High',
            'created_by'         => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/exports/evaluations/individual/{$student->id}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
    }

    // ──────────────────────────────────────────────
    //  Batch Report
    // ──────────────────────────────────────────────

    public function test_batch_report_returns_pdf(): void
    {
        $this->createStudentWithEvaluation('STU-002', 'Jane Doe', 7);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/batch?' . http_build_query([
                'batch_id'           => $this->batch->id,
                'evaluation_form_id' => $this->form->id,
            ]));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_batch_report_filters_by_period(): void
    {
        $this->createStudentWithEvaluation('STU-003', 'Bob Smith', 9, '2026-Q1');
        $this->createStudentWithEvaluation('STU-004', 'Alice Lee', 6, '2026-Q2');

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/batch?' . http_build_query([
                'period' => '2026-Q1',
            ]));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
    }

    public function test_batch_report_handles_empty_results(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/batch?' . http_build_query([
                'batch_id' => 99999,
            ]));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
    }

    // ──────────────────────────────────────────────
    //  Trend Report
    // ──────────────────────────────────────────────

    public function test_trend_report_returns_pdf(): void
    {
        // Create one student with two evaluations across different periods
        $student = $this->createStudentWithEvaluation('STU-005', 'Charlie Brown', 8, '2026-Q1');

        // Add second evaluation for the same student
        Evaluation::create([
            'student_id'         => $student->id,
            'evaluation_form_id' => $this->form->id,
            'evaluation_period'  => '2026-Q2',
            'total_score'        => 9,
            'status'             => 'Submitted',
            'submitted_at'       => now(),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/trend?' . http_build_query([
                'evaluation_form_id' => $this->form->id,
                'batch_id'           => $this->batch->id,
            ]));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    public function test_trend_report_requires_form_id(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/trend');

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'The evaluation_form_id query parameter is required.');
    }

    public function test_trend_report_returns_404_for_invalid_form(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/exports/evaluations/trend?' . http_build_query([
                'evaluation_form_id' => 99999,
            ]));

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Evaluation form not found.');
    }

    // ──────────────────────────────────────────────
    //  Authentication / Authorization
    // ──────────────────────────────────────────────

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/exports/evaluations/individual/1')->assertStatus(401);
        $this->getJson('/api/v1/exports/evaluations/batch')->assertStatus(401);
        $this->getJson('/api/v1/exports/evaluations/trend')->assertStatus(401);
    }

    public function test_endpoints_require_evaluation_view_permission(): void
    {
        // User without evaluation.view permission
        $noPermRole = Role::create(['name' => 'No Perm', 'slug' => 'no-perm']);
        $noPermUser = User::create([
            'name'      => 'No Perm User',
            'email'     => 'noperm@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $noPermRole->id,
            'is_active' => true,
        ]);
        $noPermToken = JWTAuth::fromUser($noPermUser);

        $headers = ['Authorization' => "Bearer {$noPermToken}"];

        $this->withHeaders($headers)
            ->getJson('/api/v1/exports/evaluations/individual/1')
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->getJson('/api/v1/exports/evaluations/batch')
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->getJson('/api/v1/exports/evaluations/trend')
            ->assertStatus(403);
    }
}
