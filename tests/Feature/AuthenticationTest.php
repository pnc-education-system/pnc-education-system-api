<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $overrides = []): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Admin', 'description' => 'Full access']
        );

        return User::create(array_merge([
            'role_id'   => $role->id,
            'name'      => 'Test User',
            'email'     => 'test@pnc.edu',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Test 1: Login success → 200 with access_token
    // -------------------------------------------------------------------------
    public function test_login_success(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'role',
                'permissions',
            ]);
    }

    // -------------------------------------------------------------------------
    // Test 2: Login with wrong password → 401
    // -------------------------------------------------------------------------
    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    // -------------------------------------------------------------------------
    // Test 3: Login with non-existent email → 401
    // -------------------------------------------------------------------------
    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@pnc.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    // -------------------------------------------------------------------------
    // Test 4: Expired / invalid JWT token → 401
    // -------------------------------------------------------------------------
    public function test_expired_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer this.is.an.invalid.token',
        ])->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Test 5: No token provided to protected route → 401
    // -------------------------------------------------------------------------
    public function test_missing_token_returns_401(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Test 6: Inactive user cannot login → 401
    // -------------------------------------------------------------------------
    public function test_inactive_user_cannot_login(): void
    {
        $this->createUser(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Account is inactive']);
    }

    // -------------------------------------------------------------------------
    // Test 7: Logout with valid token → 200
    // -------------------------------------------------------------------------
    public function test_logout_success(): void
    {
        $user  = $this->createUser();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);
    }
}
