<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SelectionBatchTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Batches', 'slug' => 'batches.manage', 'module' => 'enrollment']);
        $role->permissions()->sync([$perm->id]);
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true]);
        $this->token = JWTAuth::fromUser($user);
    }

    public function test_list_batches(): void
    {
        SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/selection-batches');
        $response->assertStatus(200);
    }

    public function test_create_batch(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/selection-batches', ['name' => 'Batch 2026', 'year' => 2026]);
        $response->assertStatus(201);
    }

    public function test_show_batch(): void
    {
        $batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson("/api/v1/selection-batches/{$batch->id}");
        $response->assertStatus(200);
    }

    public function test_delete_batch(): void
    {
        $batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->deleteJson("/api/v1/selection-batches/{$batch->id}");
        $response->assertStatus(200);
    }
}
