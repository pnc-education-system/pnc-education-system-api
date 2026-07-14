<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Import Students', 'slug' => 'students.import', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->adminToken = JWTAuth::fromUser($user);

        SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
    }

    public function test_preview_rejects_non_xlsx(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->post('/api/v1/imports/preview', ['file' => $file]);
        $response->assertStatus(422);
    }

    public function test_preview_requires_file(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->post('/api/v1/imports/preview');
        $response->assertStatus(422);
    }

    public function test_preview_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('test.xlsx', 100);
        $response = $this->post('/api/v1/imports/preview', ['file' => $file]);
        $response->assertStatus(401);
    }

    public function test_commit_requires_file_name(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/imports/commit', ['rows' => []]);
        $response->assertStatus(422);
    }

    public function test_commit_requires_rows(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/imports/commit', ['file_name' => 'test.xlsx']);
        $response->assertStatus(422);
    }

    public function test_commit_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/imports/commit', [
            'file_name' => 'test.xlsx',
            'rows' => [['student_id_no' => 'ST001', 'full_name' => 'Test']],
        ]);
        $response->assertStatus(401);
    }

    public function test_commit_accepts_valid_data(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/imports/commit', [
                'file_name' => 'test.xlsx',
                'rows' => [[
                    'student_id_no'      => 'ST001',
                    'full_name'          => 'Test Student',
                    'gender'             => 'Male',
                    'dob'                => '2000-01-01',
                    'selection_batch_id' => 1,
                    'enrollment_status'  => 'Pending',
                    'intake_year'        => 2025,
                ]],
            ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 1);
    }
}
