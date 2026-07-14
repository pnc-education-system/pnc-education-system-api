<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ImportHistoryTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Import', 'slug' => 'students.import', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true]);
        $this->token = JWTAuth::fromUser($user);
    }

    public function test_list_imports(): void
    {
        ImportLog::create(['file_name' => 'test.xlsx', 'imported_by' => 1, 'total_rows' => 10, 'success_count' => 8, 'error_count' => 2, 'status' => 'Completed']);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/imports');
        $response->assertStatus(200);
    }

    public function test_show_import(): void
    {
        $log = ImportLog::create(['file_name' => 'test.xlsx', 'imported_by' => 1, 'total_rows' => 10, 'success_count' => 8, 'error_count' => 2, 'status' => 'Completed']);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson("/api/v1/imports/{$log->id}");
        $response->assertStatus(200);
    }

    public function test_show_import_not_found(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")->getJson('/api/v1/imports/999');
        $response->assertStatus(404);
    }
}
