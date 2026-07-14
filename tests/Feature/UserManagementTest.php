<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'admin']);
        $role->permissions()->sync([$perm->id]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->adminToken = JWTAuth::fromUser($this->admin);
    }

    public function test_list_users(): void
    {
        User::create(['name' => 'Staff', 'email' => 'staff@test.com', 'password' => Hash::make('password'), 'role_id' => 1, 'is_active' => true]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/users');
        $response->assertStatus(200);
    }

    public function test_create_user(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'New User', 'email' => 'new@test.com',
                'password' => 'password123', 'role_id' => 1,
            ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'new@test.com');
    }

    public function test_create_user_validates_email_uniqueness(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'First', 'email' => 'dup@test.com', 'password' => 'password123',
            ])->assertStatus(201);

        $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Second', 'email' => 'dup@test.com', 'password' => 'password123',
            ])->assertStatus(422);
    }

    public function test_update_user(): void
    {
        $user = User::create(['name' => 'Old', 'email' => 'old@test.com', 'password' => Hash::make('password'), 'role_id' => 1, 'is_active' => true]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/users/{$user->id}", ['name' => 'Updated']);
        $response->assertStatus(200);
    }

    public function test_toggle_user_active_status(): void
    {
        $user = User::create(['name' => 'Togglable', 'email' => 'toggle@test.com', 'password' => Hash::make('password'), 'role_id' => 1, 'is_active' => true]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->patchJson("/api/v1/users/{$user->id}/toggle");
        $response->assertStatus(200);
    }

    public function test_delete_user(): void
    {
        $user = User::create(['name' => 'Deletable', 'email' => 'del@test.com', 'password' => Hash::make('password'), 'role_id' => 1, 'is_active' => true]);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/users/{$user->id}");
        $response->assertStatus(200);
    }
}
