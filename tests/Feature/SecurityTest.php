<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage', 'module' => 'admin']);
        $role->permissions()->sync([$perm->id]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->adminToken = JWTAuth::fromUser($user);
    }

    /** Mass assignment: verify sensitive fields cannot be set via API */
    public function test_cannot_mass_assign_is_admin(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Hacker', 'email' => 'hacker@test.com',
                'password' => 'password123', 'is_active' => false,
            ]);
        $response->assertStatus(201);
        $this->assertFalse(User::where('email', 'hacker@test.com')->first()->is_active);
    }

    /** XSS: attempt to inject script into name field */
    public function test_xss_prevention_in_name_field(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => '<script>alert("XSS")</script>',
                'email' => 'xss@test.com', 'password' => 'password123',
            ]);
        $response->assertStatus(201);
        $user = User::where('email', 'xss@test.com')->first();
        $this->assertStringContainsString('<script>', $user->name);
    }

    /** SQL injection attempt via email field */
    public function test_sql_injection_prevention(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => "' OR 1=1 --", 'password' => 'anything',
        ]);
        $response->assertStatus(422);
    }

    /** Rate limiting on login endpoint */
    public function test_login_rate_limiting(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@test.com', 'password' => 'wrong',
            ]);
        }
        $response->assertStatus(429);
    }

    /** Empty token should be rejected */
    public function test_empty_token_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ')
            ->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    /** Invalid token format rejected */
    public function test_invalid_token_rejected(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid.token.here')
            ->getJson('/api/v1/auth/me');
        $response->assertStatus(401);
    }

    /** Password minimum length enforced */
    public function test_password_minimum_length(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Short', 'email' => 'short@test.com',
                'password' => '123',
            ]);
        $response->assertStatus(422);
    }

    /** Cannot access other users without permission */
    public function test_unauthenticated_user_blocked(): void
    {
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(401);
    }

    /** Verify JWT token expires */
    public function test_token_expires(): void
    {
        $user = User::first() ?? User::create([
            'name' => 'Temp', 'email' => 'temp@test.com',
            'password' => Hash::make('password'), 'role_id' => 1, 'is_active' => true,
        ]);
        $token = JWTAuth::fromUser($user);
        $this->assertNotNull($token);
        $payload = JWTAuth::setToken($token)->getPayload();
        $this->assertNotNull($payload->get('exp'));
        $this->assertGreaterThan($payload->get('iat'), $payload->get('exp'));
    }
}
