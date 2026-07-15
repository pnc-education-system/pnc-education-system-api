<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'Import', 'slug' => 'students.import', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);

        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('password'), 'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->adminToken = JWTAuth::fromUser($user);
        SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);
    }

    public function test_chunked_import_of_500_rows(): void
    {
        $rows = [];
        for ($i = 1; $i <= 500; $i++) {
            $rows[] = [
                'student_id_no' => 'ST' . str_pad((string)$i, 5, '0', STR_PAD_LEFT),
                'full_name' => "Student $i",
                'gender' => $i % 2 === 0 ? 'Male' : 'Female',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'intake_year' => 2025,
            ];
        }

        // Create a pending import log to commit against
        $log = ImportLog::create([
            'file_name'     => 'bulk-test.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 500,
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Pending',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/imports/{$log->id}/commit", [
                'rows' => $rows,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 500);
    }
}
