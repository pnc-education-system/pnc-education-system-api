<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_reset_validates_email(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset', []);
        $response->assertStatus(422);
    }

    public function test_request_reset_accepts_valid_email(): void
    {
        User::create(['name' => 'Test', 'email' => 'test@pnc.edu', 'password' => Hash::make('password'), 'is_active' => true]);
        $response = $this->postJson('/api/v1/auth/password/reset', ['email' => 'test@pnc.edu']);
        $response->assertStatus(200);
    }

    public function test_confirm_reset_validates_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/password/reset/confirm', []);
        $response->assertStatus(422);
    }
}
