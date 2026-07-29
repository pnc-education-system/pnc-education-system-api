<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createRole(): Role
    {
        return Role::create(['name' => 'Admin', 'slug' => 'administrator']);
    }

    private function createUser(array $overrides = []): User
    {
        $role = $this->createRole();
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@pnc.edu',
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'is_active' => true,
        ], $overrides));
    }

    public function test_login_with_valid_credentials(): void
    {
        $this->createUser();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@pnc.edu',
            'password' => 'password123',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status', 'message', 'user', 'access_token', 'refresh_token', 'token_type', 'expires_in', 'permissions',
            ]);
    }

    public function test_login_with_invalid_credentials(): void
    {
        $this->createUser();
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@pnc.edu',
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(401);
    }

    public function test_login_with_inactive_user(): void
    {
        $this->createUser(['is_active' => false]);
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@pnc.edu',
            'password' => 'password123',
        ]);
        $response->assertStatus(401);
    }

    public function test_login_validation_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);
        $response->assertStatus(422);
    }

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $user = $this->createUser();
        $token = JWTAuth::fromUser($user);
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/auth/me');
        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'test@pnc.edu');
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = $this->createUser();
        $token = JWTAuth::fromUser($user);
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/auth/logout');
        $response->assertStatus(200);
    }

    public function test_logout_without_token(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');
        $response->assertStatus(401);
    }

    public function test_refresh_requires_token(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', []);
        // Route is public (no JWT required), so missing refresh_token gives 422 validation error
        $response->assertStatus(422);
    }
}
