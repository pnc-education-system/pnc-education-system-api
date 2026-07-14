<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function setupRolesAndPermissions(): array
    {
        $userPerm = Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'admin']);
        $rolePerm = Permission::create(['name' => 'Manage Roles', 'slug' => 'roles.manage', 'module' => 'admin']);

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        $staffRole = Role::create(['name' => 'Staff', 'slug' => 'education_staff']);

        $adminRole->permissions()->sync([$userPerm->id, $rolePerm->id]);
        $staffRole->permissions()->sync([$userPerm->id]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role_id' => $adminRole->id, 'is_active' => true,
        ]);
        $staff = User::create([
            'name' => 'Staff', 'email' => 'staff@test.com',
            'password' => Hash::make('password'), 'role_id' => $staffRole->id, 'is_active' => true,
        ]);

        return [
            'adminToken' => JWTAuth::fromUser($admin),
            'staffToken' => JWTAuth::fromUser($staff),
            'admin' => $admin,
            'staff' => $staff,
        ];
    }

    public function test_admin_can_access_users_route(): void
    {
        $data = $this->setupRolesAndPermissions();
        $response = $this->withHeader('Authorization', "Bearer {$data['adminToken']}")
            ->getJson('/api/v1/users');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_roles_route(): void
    {
        $data = $this->setupRolesAndPermissions();
        $response = $this->withHeader('Authorization', "Bearer {$data['adminToken']}")
            ->getJson('/api/v1/roles');
        $response->assertStatus(200);
    }

    public function test_staff_cannot_access_roles_route(): void
    {
        $data = $this->setupRolesAndPermissions();
        $response = $this->withHeader('Authorization', "Bearer {$data['staffToken']}")
            ->getJson('/api/v1/roles');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_blocked(): void
    {
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(401);
    }
}
