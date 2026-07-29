<?php

namespace Tests\Feature;

use App\Models\CardTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentCardReprintTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Generate ID Cards', 'slug' => 'cards.generate', 'module' => 'cards']);
        $role->permissions()->sync([$perm->id]);

        $this->admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id'  => $role->id,
            'is_active' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->admin);

        SelectionBatch::create([
            'name' => 'Batch 2025',
            'year' => 2025,
        ]);
    }

    /** @test */
    public function reprint_increments_printed_count_from_zero_to_one(): void
    {
        $card = $this->createCard(['printed_count' => 0]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/student-cards/{$card->id}/reprint");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Card reprinted successfully')
            ->assertJsonPath('data.printed_count', 1);
    }

    /** @test */
    public function reprint_twice_increases_count_by_two(): void
    {
        $card = $this->createCard(['printed_count' => 0]);

        // First reprint -> 1
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/student-cards/{$card->id}/reprint")
            ->assertJsonPath('data.printed_count', 1);

        // Second reprint -> 2
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/student-cards/{$card->id}/reprint")
            ->assertJsonPath('data.printed_count', 2);
    }

    /** @test */
    public function reprint_returns_404_for_non_existent_card(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/student-cards/99999/reprint');

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Student card not found');
    }

    /** @test */
    public function reprint_requires_authentication(): void
    {
        $card = $this->createCard(['printed_count' => 0]);

        $response = $this->postJson("/api/v1/student-cards/{$card->id}/reprint");

        $response->assertStatus(401);
    }

    /** @test */
    public function reprint_requires_cards_generate_permission(): void
    {
        // Create a user without cards.generate permission
        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $user = User::create([
            'name'     => 'Viewer',
            'email'    => 'viewer@test.com',
            'password' => Hash::make('password'),
            'role_id'  => $role->id,
            'is_active' => true,
        ]);
        $viewerToken = JWTAuth::fromUser($user);

        $card = $this->createCard(['printed_count' => 0]);

        $response = $this->withHeader('Authorization', "Bearer {$viewerToken}")
            ->postJson("/api/v1/student-cards/{$card->id}/reprint");

        $response->assertStatus(403);
    }

    /** @test */
    public function reprint_does_not_modify_other_card_fields(): void
    {
        $card = $this->createCard([
            'printed_count' => 0,
            'card_number'   => 'CARD-ORIGINAL',
            'qr_token'      => 'qr-original-token',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/student-cards/{$card->id}/reprint")
            ->assertJsonPath('data.printed_count', 1);

        $card->refresh();

        $this->assertEquals('CARD-ORIGINAL', $card->card_number);
        $this->assertEquals('qr-original-token', $card->qr_token);
        $this->assertEquals(1, $card->printed_count);
    }

    // ── Helpers ──

    private function createCard(array $overrides = []): StudentCard
    {
        $template = CardTemplate::create([
            'name'        => 'Default Template',
            'layout_json' => '{}',
            'is_default'  => true,
        ]);

        $batch = SelectionBatch::first();

        $student = Student::create([
            'student_id_no'      => 'ST-TEST-001',
            'full_name'          => 'Test Student',
            'gender'             => 'Male',
            'dob'                => '2000-01-01',
            'high_school'        => 'Test High School',
            'selection_batch_id' => $batch->id,
            'enrollment_status'  => 'Pending',
            'intake_year'        => 2025,
            'created_by'         => $this->admin->id,
        ]);

        return StudentCard::create(array_merge([
            'student_id'     => $student->id,
            'template_id'    => $template->id,
            'card_number'    => 'CARD-' . uniqid(),
            'qr_token'       => 'QR-' . uniqid(),
            'printed_count'  => 0,
            'issued_at'      => now(),
        ], $overrides));
    }
}
