<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Str;
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

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'user',
                'permissions',
            ]);
    }

    // -------------------------------------------------------------------------
    // Test 2: Login with wrong password → 401
    // -------------------------------------------------------------------------
    public function test_login_fails_with_wrong_password(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => ['message' => 'Invalid credentials']]);
    }

    // -------------------------------------------------------------------------
    // Test 3: Login with non-existent email → 401
    // -------------------------------------------------------------------------
    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@pnc.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => ['message' => 'Invalid credentials']]);
    }

    // -------------------------------------------------------------------------
    // Test 4: Expired / invalid JWT token → 401
    // -------------------------------------------------------------------------
    public function test_expired_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer this.is.an.invalid.token',
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Test 5: No token provided to protected route → 401
    // -------------------------------------------------------------------------
    public function test_missing_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Test 6: Inactive user cannot login → 401
    // -------------------------------------------------------------------------
    public function test_inactive_user_cannot_login(): void
    {
        $this->createUser(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => ['message' => 'Account is inactive']]);
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
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Successfully logged out']);
    }

    // -------------------------------------------------------------------------
    // Test 8: Refresh token with valid refresh token → 200
    // -------------------------------------------------------------------------
    public function test_refresh_token_success(): void
    {
        $user = $this->createUser();

        // First login to get refresh token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@pnc.edu',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('refresh_token');
        $accessToken = $loginResponse->json('access_token');

        // Use refresh token to get new access token (requires JWT auth)
        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);
    }

    // -------------------------------------------------------------------------
    // Test 9: Refresh token with invalid refresh token → 401
    // -------------------------------------------------------------------------
    public function test_refresh_token_fails_with_invalid_token(): void
    {
        $user  = $this->createUser();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid.refresh.token',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => ['message' => 'Invalid or expired refresh token']]);
    }

    // -------------------------------------------------------------------------
    // Test 10: Get authenticated user data → 200
    // -------------------------------------------------------------------------
    public function test_me_endpoint_returns_user_data(): void
    {
        $user  = $this->createUser();
        $token = JWTAuth::fromUser($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user',
                'permissions',
            ])
            ->assertJson([
                'user' => [
                    'email' => 'test@pnc.edu',
                ],
            ]);
    }

    // -------------------------------------------------------------------------
    // Test 11: Get user data without token → 401
    // -------------------------------------------------------------------------
    public function test_me_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Test 12: Password reset request with valid email
    // -------------------------------------------------------------------------
    public function test_password_reset_request_with_valid_email(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'test@pnc.edu',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If the email exists, a reset token has been sent'])
            ->assertJsonStructure(['reset_token']);
    }

    // -------------------------------------------------------------------------
    // Test 13: Password reset request with invalid email
    // -------------------------------------------------------------------------
    public function test_password_reset_request_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'nonexistent@pnc.edu',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If the email exists, a reset token has been sent']);
    }

    // -------------------------------------------------------------------------
    // Test 14: Password reset confirmation with valid token
    // -------------------------------------------------------------------------
    public function test_password_reset_confirmation_with_valid_token(): void
    {
        $user = $this->createUser();

        // Request reset token
        $resetResponse = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'test@pnc.edu',
        ]);
        $resetToken = $resetResponse->json('reset_token');

        // Confirm reset with new password
        $confirmResponse = $this->postJson('/api/v1/auth/password/reset/confirm', [
            'email' => 'test@pnc.edu',
            'reset_token' => $resetToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $confirmResponse->assertStatus(200)
            ->assertJson(['message' => 'Password reset successfully']);

        // Verify new password works
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@pnc.edu',
            'password' => 'newpassword123',
        ]);

        $loginResponse->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Test 15: Password reset confirmation with invalid token
    // -------------------------------------------------------------------------
    public function test_password_reset_confirmation_with_invalid_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/password/reset/confirm', [
            'email' => 'test@pnc.edu',
            'reset_token' => 'invalid.token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => ['message' => 'Invalid or expired reset token']]);
    }

    // -------------------------------------------------------------------------
    // Test 16: Password reset with password confirmation mismatch
    // -------------------------------------------------------------------------
    public function test_password_reset_with_password_confirmation_mismatch(): void
    {
        $user = $this->createUser();

        $resetResponse = $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'test@pnc.edu',
        ]);
        $resetToken = $resetResponse->json('reset_token');

        $response = $this->postJson('/api/v1/auth/password/reset/confirm', [
            'email' => 'test@pnc.edu',
            'reset_token' => $resetToken,
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Test 17: Login validation requires email
    // -------------------------------------------------------------------------
    public function test_login_validation_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Test 18: Login validation requires password
    // -------------------------------------------------------------------------
    public function test_login_validation_requires_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@pnc.edu',
        ]);

        $response->assertStatus(422);
    }
}
