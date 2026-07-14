<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $manageRoles = Permission::create(['name' => 'Manage Roles', 'slug' => 'roles.manage', 'module' => 'admin']);
        $manageUsers = Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'admin']);
        $role->permissions()->sync([$manageRoles->id, $manageUsers->id]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->adminToken = JWTAuth::fromUser($user);
    }

    public function test_list_roles(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/roles');
        $response->assertStatus(200);
    }

    public function test_show_role(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/roles/{$role->id}");
        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Editor');
    }

    public function test_update_role_with_permissions(): void
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
        $perm = Permission::create(['name' => 'View', 'slug' => 'students.view', 'module' => 'students']);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/api/v1/roles/{$role->id}", [
                'name' => 'Senior Editor',
                'permissions' => [$perm->slug],
            ]);
        $response->assertStatus(200);
    }

    public function test_create_role(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/roles', ['name' => 'Editor', 'slug' => 'editor']);
        $response->assertStatus(201);
    }

    public function test_delete_role(): void
    {
        $role = Role::create(['name' => 'Temp', 'slug' => 'temp']);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/api/v1/roles/{$role->id}");
        $response->assertStatus(200);
    }

    public function test_list_permissions(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/permissions');
        $response->assertStatus(200);
    }
}
