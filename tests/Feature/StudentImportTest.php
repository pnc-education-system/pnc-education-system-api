<?php

namespace Tests\Feature;

use App\Models\ImportLog;
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

    // ── Upload tests ──

    public function test_upload_rejects_non_xlsx(): void
    {
        $file = UploadedFile::fake()->create('test.txt', 100);
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->post('/api/v1/imports', ['file' => $file]);
        $response->assertStatus(422);
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->post('/api/v1/imports');
        $response->assertStatus(422);
    }

    public function test_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('test.xlsx', 100);
        $response = $this->post('/api/v1/imports', ['file' => $file]);
        $response->assertStatus(401);
    }

    // ── Commit tests ──

    public function test_commit_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/imports/1/commit', [
            'rows' => [['student_id_no' => 'ST001', 'full_name' => 'Test']],
        ]);
        $response->assertStatus(401);
    }

    public function test_commit_returns_404_for_non_existent_import(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/api/v1/imports/999/commit', [
                'rows' => [['student_id_no' => 'ST001', 'full_name' => 'Test']],
            ]);
        $response->assertStatus(404);
    }

    public function test_commit_requires_rows(): void
    {
        $log = ImportLog::create([
            'file_name'     => 'test.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 1,
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/imports/{$log->id}/commit");
        $response->assertStatus(422);
    }

    public function test_commit_accepts_valid_data(): void
    {
        $log = ImportLog::create([
            'file_name'     => 'test.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 1,
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/imports/{$log->id}/commit", [
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

    public function test_commit_rejects_already_processed_import(): void
    {
        $log = ImportLog::create([
            'file_name'     => 'test.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 1,
            'success_count' => 1,
            'error_count'   => 0,
            'status'        => 'Completed',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/imports/{$log->id}/commit", [
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
        $response->assertStatus(422);
    }

    // ── Errors tests ──

    public function test_errors_returns_errors_for_import(): void
    {
        $log = ImportLog::create([
            'file_name'     => 'test.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 2,
            'success_count' => 1,
            'error_count'   => 1,
            'status'        => 'Completed',
        ]);

        $log->errors()->create([
            'row_number'    => 2,
            'field'         => 'email',
            'error_message' => 'email: Invalid email address.',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/api/v1/imports/{$log->id}/errors");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_errors', 1)
            ->assertJsonPath('data.errors.0.field', 'email');
    }

    public function test_errors_returns_404_for_non_existent_import(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/api/v1/imports/999/errors');
        $response->assertStatus(404);
    }

    public function test_errors_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/imports/1/errors');
        $response->assertStatus(401);
    }
}
