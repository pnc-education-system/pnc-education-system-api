<?php

namespace Tests\Feature;

use App\Models\EvaluationCategory;
use App\Models\EvaluationForm;
use App\Models\EvaluationQuestion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\EvaluationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class EvaluationTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'admin']);

        // Create evaluation permissions and assign them to the test role
        $viewPerm = Permission::create([
            'name'   => 'View Evaluations',
            'slug'   => 'evaluation.view',
            'module' => 'evaluation',
        ]);
        $managePerm = Permission::create([
            'name'   => 'Manage Evaluations',
            'slug'   => 'evaluation.manage',
            'module' => 'evaluation',
        ]);
        $role->permissions()->sync([$viewPerm->id, $managePerm->id]);

        $this->user = User::create([
            'name'     => 'Evaluation Admin',
            'email'    => 'eval@example.com',
            'password' => Hash::make('password'),
            'role_id'  => $role->id,
            'is_active' => true,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
    }

    private function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ──────────────────────────────────────────────
    //  Evaluation Templates (EvaluationForm)
    // ──────────────────────────────────────────────

    public function test_can_list_templates_when_empty(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/evaluation-templates');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(0, 'data');
    }

    public function test_can_create_template(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/evaluation-templates', [
                'name'        => 'Semester Evaluation',
                'description' => 'End-of-term self-assessment',
                'is_active'   => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Semester Evaluation')
            ->assertJsonPath('data.description', 'End-of-term self-assessment')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['id', 'name', 'description', 'is_active', 'categories']]);

        $this->assertDatabaseHas('evaluation_forms', [
            'name' => 'Semester Evaluation',
        ]);
    }

    public function test_create_template_validates_required_fields(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/evaluation-templates', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422)
            ->assertJsonStructure(['error' => ['errors' => ['name']]]);
    }

    public function test_can_show_template_with_categories_and_questions(): void
    {
        $template = EvaluationForm::create([
            'name' => 'Midterm Review',
            'description' => 'Midterm self-evaluation',
            'is_active' => true,
        ]);

        $category = $template->categories()->create([
            'name' => 'Communication',
            'sort_order' => 1,
        ]);

        $category->questions()->create([
            'template_id'   => $template->id,
            'question_text' => 'I communicate clearly',
            'score'         => 5,
            'sort_order'    => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/evaluation-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Midterm Review')
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.name', 'Communication')
            ->assertJsonPath('data.categories.0.questions.0.question', 'I communicate clearly')
            ->assertJsonPath('data.categories.0.questions.0.max_score', 5);
    }

    public function test_show_template_returns_404_for_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/evaluation-templates/99999');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Evaluation template not found');
    }

    public function test_can_update_template(): void
    {
        $template = EvaluationForm::create([
            'name' => 'Old Name',
            'description' => 'Old description',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-templates/{$template->id}", [
                'name'      => 'Updated Name',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.is_active', false);

        // description should remain unchanged
        $this->assertDatabaseHas('evaluation_forms', [
            'id'          => $template->id,
            'name'        => 'Updated Name',
            'description' => 'Old description',
        ]);
    }

    public function test_update_template_returns_404_for_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/evaluation-templates/99999', [
                'name' => 'Nowhere',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Evaluation template not found');
    }

    public function test_update_template_returns_400_for_no_changes(): void
    {
        $template = EvaluationForm::create([
            'name' => 'Static Template',
            'description' => 'No change',
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-templates/{$template->id}", []);

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'No changes detected');
    }

    public function test_can_delete_template(): void
    {
        $template = EvaluationForm::create([
            'name' => 'Temp Template',
            'description' => 'To be deleted',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Evaluation template deleted successfully');

        $this->assertDatabaseMissing('evaluation_forms', ['id' => $template->id]);
    }

    public function test_cannot_delete_template_with_evaluations(): void
    {
        $template = EvaluationForm::create([
            'name' => 'Used Template',
        ]);

        // Create a minimal evaluation linked to the template
        $student = \App\Models\Student::factory()->create();
        $template->evaluations()->create([
            'student_id'         => $student->id,
            'evaluation_period'  => '2026-Q1',
            'total_score'        => 0,
            'status'             => 'Draft',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-templates/{$template->id}");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 400);

        $this->assertDatabaseHas('evaluation_forms', ['id' => $template->id]);
    }

    // ──────────────────────────────────────────────
    //  Evaluation Categories
    // ──────────────────────────────────────────────

    public function test_can_create_category_in_template(): void
    {
        $template = EvaluationForm::create([
            'name' => 'Performance Review',
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-templates/{$template->id}/categories", [
                'name'       => 'Teamwork',
                'sort_order' => 2,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'Teamwork')
            ->assertJsonPath('data.sort_order', 2)
            ->assertJsonPath('data.evaluation_form_id', $template->id);

        $this->assertDatabaseHas('evaluation_categories', [
            'evaluation_form_id' => $template->id,
            'name' => 'Teamwork',
        ]);
    }

    public function test_create_category_validates_required_fields(): void
    {
        $template = EvaluationForm::create(['name' => 'Test']);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-templates/{$template->id}/categories", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['errors' => ['name']]]);
    }

    public function test_create_category_returns_404_if_template_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/evaluation-templates/99999/categories', [
                'name' => 'Orphan',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Evaluation template not found');
    }

    public function test_can_update_category(): void
    {
        $template = EvaluationForm::create(['name' => 'With Category']);
        $category = $template->categories()->create([
            'name' => 'Old Category',
            'sort_order' => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-categories/{$category->id}", [
                'name'       => 'Updated Category',
                'sort_order' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Category')
            ->assertJsonPath('data.sort_order', 5);
    }

    public function test_update_category_returns_404_for_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/evaluation-categories/99999', [
                'name' => 'Ghost',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Evaluation category not found');
    }

    public function test_update_category_returns_400_for_no_changes(): void
    {
        $template = EvaluationForm::create(['name' => 'Cat test']);
        $category = $template->categories()->create([
            'name' => 'Fixed',
            'sort_order' => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-categories/{$category->id}", []);

        $response->assertStatus(400)
            ->assertJsonPath('error.message', 'No changes detected');
    }

    public function test_can_delete_category_with_cascade_questions(): void
    {
        $template = EvaluationForm::create(['name' => 'To Delete Category']);
        $category = $template->categories()->create([
            'name' => 'Delete Me',
            'sort_order' => 1,
        ]);
        $question = $category->questions()->create([
            'template_id'   => $template->id,
            'question_text' => 'Am I deleted?',
            'score'         => 5,
            'sort_order'    => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-categories/{$category->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('evaluation_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('evaluation_question', ['id' => $question->id]);
    }

    // ──────────────────────────────────────────────
    //  Evaluation Questions
    // ──────────────────────────────────────────────

    public function test_can_create_question_in_category(): void
    {
        $template = EvaluationForm::create(['name' => 'Q Template']);
        $category = $template->categories()->create([
            'name' => 'Q Category',
            'sort_order' => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-categories/{$category->id}/questions", [
                'question_text' => 'How well do you collaborate?',
                'score'         => 10,
                'sort_order'    => 3,
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.question', 'How well do you collaborate?')
            ->assertJsonPath('data.max_score', 10)
            ->assertJsonPath('data.sort_order', 3);
    }

    public function test_create_question_validates_required_fields(): void
    {
        $template = EvaluationForm::create(['name' => 'Validation']);
        $category = $template->categories()->create(['name' => 'Cat', 'sort_order' => 1]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-categories/{$category->id}/questions", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['error' => ['errors' => ['question_text', 'score']]]);
    }

    public function test_create_question_returns_404_if_category_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/evaluation-categories/99999/questions', [
                'question_text' => 'Orphan question',
                'score'         => 5,
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Evaluation category not found');
    }

    public function test_can_update_question(): void
    {
        $template = EvaluationForm::create(['name' => 'Update Q']);
        $category = $template->categories()->create(['name' => 'Cat', 'sort_order' => 1]);
        $question = $category->questions()->create([
            'template_id'   => $template->id,
            'question_text' => 'Old question',
            'score'         => 3,
            'sort_order'    => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-questions/{$question->id}", [
                'question_text' => 'Updated question',
                'score'         => 8,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.question', 'Updated question')
            ->assertJsonPath('data.max_score', 8)
            ->assertJsonPath('data.sort_order', 1);
    }

    public function test_update_question_returns_404_for_missing(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/evaluation-questions/99999', [
                'question_text' => 'Ghost',
                'score'         => 5,
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.message', 'Evaluation question not found');
    }

    public function test_can_delete_question(): void
    {
        $template = EvaluationForm::create(['name' => 'Delete Q']);
        $category = $template->categories()->create(['name' => 'Cat', 'sort_order' => 1]);
        $question = $category->questions()->create([
            'template_id'   => $template->id,
            'question_text' => 'Delete me',
            'score'         => 5,
            'sort_order'    => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-questions/{$question->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Evaluation question deleted successfully');

        $this->assertDatabaseMissing('evaluation_question', ['id' => $question->id]);
    }

    public function test_cannot_delete_question_with_answers(): void
    {
        $template = EvaluationForm::create(['name' => 'With Answers']);
        $category = $template->categories()->create(['name' => 'Cat', 'sort_order' => 1]);
        $question = $category->questions()->create([
            'template_id'   => $template->id,
            'question_text' => 'Answered question',
            'score'         => 5,
            'sort_order'    => 1,
        ]);

        // Create an evaluation + answer to protect the question
        $student = \App\Models\Student::factory()->create();
        $evaluation = $template->evaluations()->create([
            'student_id'        => $student->id,
            'evaluation_period' => '2026-Q1',
            'total_score'       => 5,
            'status'            => 'Submitted',
        ]);
        $question->answers()->create([
            'evaluation_id' => $evaluation->id,
            'score'         => 5,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-questions/{$question->id}");

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 400);

        $this->assertDatabaseHas('evaluation_question', ['id' => $question->id]);
    }

    // ──────────────────────────────────────────────
    //  Unauthenticated access
    // ──────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_endpoints(): void
    {
        $this->getJson('/api/v1/evaluation-templates')->assertStatus(401);
        $this->postJson('/api/v1/evaluation-templates', [])->assertStatus(401);
        $this->getJson('/api/v1/evaluation-templates/1')->assertStatus(401);
        $this->putJson('/api/v1/evaluation-templates/1', [])->assertStatus(401);
        $this->deleteJson('/api/v1/evaluation-templates/1')->assertStatus(401);
    }

    // ──────────────────────────────────────────────
    //  Full CRUD lifecycle
    // ──────────────────────────────────────────────

    public function test_full_evaluation_crud_lifecycle(): void
    {
        // 1. Create template
        $templateResp = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/evaluation-templates', [
                'name' => 'Lifecycle Test',
                'description' => 'Testing full CRUD',
            ]);
        $templateResp->assertCreated();
        $templateId = $templateResp->json('data.id');

        // 2. Create categories
        $cat1Resp = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-templates/{$templateId}/categories", [
                'name' => 'Leadership',
                'sort_order' => 1,
            ]);
        $cat1Resp->assertCreated();
        $cat1Id = $cat1Resp->json('data.id');

        $cat2Resp = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-templates/{$templateId}/categories", [
                'name' => 'Teamwork',
                'sort_order' => 2,
            ]);
        $cat2Resp->assertCreated();
        $cat2Id = $cat2Resp->json('data.id');

        // 3. Create questions in first category
        $q1Resp = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/evaluation-categories/{$cat1Id}/questions", [
                'question_text' => 'I lead by example',
                'score'         => 5,
            ]);
        $q1Resp->assertCreated();
        $q1Id = $q1Resp->json('data.id');

        // 4. Verify template shows full structure
        $showResp = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/evaluation-templates/{$templateId}");

        $showResp->assertOk()
            ->assertJsonCount(2, 'data.categories')
            // Categories should be ordered by sort_order
            ->assertJsonPath('data.categories.0.sort_order', 1)
            ->assertJsonPath('data.categories.0.name', 'Leadership')
            ->assertJsonPath('data.categories.1.sort_order', 2)
            ->assertJsonPath('data.categories.1.name', 'Teamwork')
            // Questions should be ordered by sort_order
            ->assertJsonPath('data.categories.0.questions.0.question', 'I lead by example')
            ->assertJsonPath('data.categories.0.questions.0.max_score', 5);

        // 5. Update question
        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-questions/{$q1Id}", [
                'score' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.max_score', 10);

        // 6. Update category
        $this->withHeaders($this->authHeaders())
            ->putJson("/api/v1/evaluation-categories/{$cat2Id}", [
                'name' => 'Collaboration',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Collaboration');

        // 7. Delete question
        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-questions/{$q1Id}")
            ->assertOk();

        $this->assertDatabaseMissing('evaluation_question', ['id' => $q1Id]);

        // 8. Delete category (cascades remaining questions)
        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-categories/{$cat2Id}")
            ->assertOk();

        $this->assertDatabaseMissing('evaluation_categories', ['id' => $cat2Id]);

        // 9. Delete template
        $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/v1/evaluation-templates/{$templateId}")
            ->assertOk();

        $this->assertDatabaseMissing('evaluation_forms', ['id' => $templateId]);
    }

    // ──────────────────────────────────────────────
    //  Seeded data integration test
    // ──────────────────────────────────────────────

    public function test_seeded_data_returns_correct_structure(): void
    {
        // Seed the evaluation template with categories and questions
        $this->seed(EvaluationTemplateSeeder::class);

        // Fetch the list of templates
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/evaluation-templates');

        // First, verify the response is a 200
        $response->assertOk();

        // Get and examine the response body
        $body = $response->json();
        $this->assertArrayHasKey('status', $body);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('data', $body);
        $this->assertIsArray($body['data']);
        $this->assertCount(1, $body['data']);

        // Verify template metadata using array access
        $template = $body['data'][0];
        $this->assertEquals('Young Stars Self-Assessment', $template['name']);
        $this->assertEquals('Standard self-evaluation based on the UK Young Stars model.', $template['description']);
        $this->assertTrue($template['is_active']);
        $this->assertArrayHasKey('id', $template);
        $this->assertArrayHasKey('categories', $template);
        $this->assertArrayHasKey('created_at', $template);
        $this->assertArrayHasKey('updated_at', $template);

        $templateId = $template['id'];
        $categories = $template['categories'];

        // Verify 7 categories in correct sort order
        $this->assertCount(7, $categories);
        $expectedCategories = [
            'Communication', 'Teamwork', 'Responsibility',
            'Problem Solving', 'Leadership', 'Learning Mindset',
            'Professional Behavior',
        ];
        foreach ($expectedCategories as $i => $name) {
            $this->assertEquals($name, $categories[$i]['name']);
            $this->assertEquals($i + 1, $categories[$i]['sort_order']);
        }

        // Verify each category has 2 questions with max_score: 5
        foreach ($categories as $catIdx => $category) {
            $questions = $category['questions'];
            $this->assertCount(2, $questions, "Category {$catIdx} should have 2 questions");

            foreach ($questions as $q) {
                $this->assertArrayHasKey('id', $q);
                $this->assertArrayHasKey('question', $q);
                $this->assertArrayHasKey('max_score', $q);
                $this->assertArrayHasKey('sort_order', $q);
                $this->assertIsInt($q['sort_order']);
                $this->assertEquals(5, $q['max_score']);
            }
        }

        // Verify total question count across all categories
        $allQuestions = collect($categories)->flatMap(fn($cat) => $cat['questions']);
        $this->assertCount(14, $allQuestions);

        // Verify first and last question text to confirm ordering
        $this->assertEquals('I express my ideas clearly in meetings and conversations.', $categories[0]['questions'][0]['question']);
        $this->assertEquals('I communicate respectfully with all staff and peers.', $categories[6]['questions'][1]['question']);

        // ── Single template endpoint ──
        $singleResponse = $this->withHeaders($this->authHeaders())
            ->getJson("/api/v1/evaluation-templates/{$templateId}");

        $singleResponse->assertOk();
        $singleBody = $singleResponse->json();
        $this->assertEquals('success', $singleBody['status']);
        $this->assertEquals('Young Stars Self-Assessment', $singleBody['data']['name']);
        $this->assertCount(7, $singleBody['data']['categories']);
        $this->assertEquals('Communication', $singleBody['data']['categories'][0]['name']);
        $this->assertEquals(5, $singleBody['data']['categories'][0]['questions'][0]['max_score']);
    }
}
