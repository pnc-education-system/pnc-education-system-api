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

    private function createUserWithRole(array $permissionSlugs = []): User
    {
        $role = Role::create([
            'name' => 'Test Role',
            'slug' => 'test-role',
            'description' => 'Test role for permissions',
        ]);

        // Attach permissions to role
        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], [
                'name' => ucfirst(str_replace('.', ' ', $slug)),
                'module' => 'Test',
            ]);
            $permissionIds[] = $permission->id;
        }
        $role->permissions()->sync($permissionIds);

        return User::create([
            'role_id'   => $role->id,
            'name'      => 'Test User',
            'email'     => 'test@pnc.edu',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 1: User with correct permission can access protected route
    // -------------------------------------------------------------------------
    public function test_user_with_permission_can_access_protected_route(): void
    {
        $user = $this->createUserWithRole(['users.manage']);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/users');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Test 2: User without permission cannot access protected route → 403
    // -------------------------------------------------------------------------
    public function test_user_without_permission_cannot_access_protected_route(): void
    {
        $user = $this->createUserWithRole(['roles.view']); // Wrong permission
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/users');

        $response->assertStatus(403)
            ->assertJson(['error' => ['message' => 'Missing required permission']]);
    }

    // -------------------------------------------------------------------------
    // Test 3: User without role cannot access protected route → 403
    // -------------------------------------------------------------------------
    public function test_user_without_role_cannot_access_protected_route(): void
    {
        $user = User::create([
            'role_id'   => null,
            'name'      => 'Test User',
            'email'     => 'test@pnc.edu',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/users');

        $response->assertStatus(403)
            ->assertJson(['error' => ['message' => 'No role assigned']]);
    }

    // -------------------------------------------------------------------------
    // Test 4: Unauthenticated user cannot access protected route → 401
    // -------------------------------------------------------------------------
    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(401)
            ->assertJson(['error' => ['message' => 'Token not provided']]);
    }

    // -------------------------------------------------------------------------
    // Test 5: User with roles.manage permission can access roles endpoint
    // -------------------------------------------------------------------------
    public function test_user_with_roles_permission_can_access_roles_route(): void
    {
        $user = $this->createUserWithRole(['roles.manage']);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/roles');

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Test 6: User without roles.manage permission cannot access roles endpoint
    // -------------------------------------------------------------------------
    public function test_user_without_roles_permission_cannot_access_roles_route(): void
    {
        $user = $this->createUserWithRole(['users.manage']); // Wrong permission
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/roles');

        $response->assertStatus(403)
            ->assertJson(['error' => ['message' => 'Missing required permission']]);
    }

    // -------------------------------------------------------------------------
    // Test 7: Permission middleware returns required permission in error
    // -------------------------------------------------------------------------
    public function test_permission_middleware_returns_required_permission_in_error(): void
    {
        $user = $this->createUserWithRole(['users.view']);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/users');

        $response->assertStatus(403)
            ->assertJson(['error' => ['message' => 'Missing required permission']]);
    }

    // -------------------------------------------------------------------------
    // Test 8: User with multiple permissions including required one can access
    // -------------------------------------------------------------------------
    public function test_user_with_multiple_permissions_can_access_if_has_required(): void
    {
        $user = $this->createUserWithRole(['users.view', 'users.manage', 'roles.view']);
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/users');

        $response->assertStatus(200);
    }

}
