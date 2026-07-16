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

    public function test_upload_preview_uses_selected_batch_for_import_rows(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('The PHP zip extension is required to parse .xlsx fixtures.');
        }

        $fixturePath = base_path('tests/Fixtures/student_import_valid.xlsx');
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('The student import fixture is missing.');
        }

        $batch = SelectionBatch::create(['name' => 'Batch 2026', 'year' => 2026]);
        $file = new UploadedFile(
            $fixturePath,
            'student_import_valid.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->post('/api/v1/imports', [
                'file' => $file,
                'selection_batch_id' => $batch->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.valid_rows', 4)
            ->assertJsonPath('data.invalid_rows', 0)
            ->assertJsonPath('data.rows.0.selection_batch_id', $batch->id)
            ->assertJsonPath('data.rows.0.intake_year', $batch->year)
            ->assertJsonPath('data.rows.0.enrollment_status', 'Pending');
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

    public function test_commit_uses_selected_batch_from_request(): void
    {
        $targetBatch = SelectionBatch::create(['name' => 'Batch 2026', 'year' => 2026]);
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
                'selection_batch_id' => $targetBatch->id,
                'rows' => [[
                    'student_id_no'      => 'ST1234',
                    'full_name'          => 'Selected Batch Student',
                    'gender'             => 'Female',
                    'dob'                => '2000-01-01',
                    'selection_batch_id' => 1,
                    'enrollment_status'  => '',
                    'intake_year'        => 2025,
                ]],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 1);

        $this->assertDatabaseHas('students', [
            'student_id_no'      => 'ST1234',
            'selection_batch_id' => $targetBatch->id,
            'enrollment_status'  => 'Pending',
            'intake_year'        => $targetBatch->year,
        ]);
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

    // ── Performance tests ──

    public function test_500_row_import_commits_successfully(): void
    {
        $log = ImportLog::create([
            'file_name'     => 'large_import.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 500,
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Pending',
        ]);

        // Generate 500 valid student rows
        $rows = [];
        for ($i = 1; $i <= 500; $i++) {
            $rows[] = [
                'student_id_no'      => 'ST' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'full_name'          => 'Test Student ' . $i,
                'gender'             => $i % 2 === 0 ? 'Male' : 'Female',
                'dob'                => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status'  => 'Pending',
                'intake_year'        => 2025,
            ];
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/imports/{$log->id}/commit", [
                'rows' => $rows,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported', 500)
            ->assertJsonPath('data.failed', 0);

        // Verify all students were inserted
        $this->assertEquals(500, \App\Models\Student::count());

        // Verify import log was updated
        $log->refresh();
        $this->assertEquals(500, $log->success_count);
        $this->assertEquals('Completed', $log->status);
    }

    public function test_duplicate_rows_within_batch_are_handled(): void
    {
        $log = ImportLog::create([
            'file_name'     => 'duplicate_test.xlsx',
            'imported_by'   => 1,
            'total_rows'    => 5,
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Pending',
        ]);

        // Create rows with duplicate student_id_no within the same batch
        $rows = [
            [
                'student_id_no'      => 'ST0001',
                'full_name'          => 'Student One',
                'gender'             => 'Male',
                'dob'                => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status'  => 'Pending',
                'intake_year'        => 2025,
            ],
            [
                'student_id_no'      => 'ST0002',
                'full_name'          => 'Student Two',
                'gender'             => 'Female',
                'dob'                => '2000-02-01',
                'selection_batch_id' => 1,
                'enrollment_status'  => 'Pending',
                'intake_year'        => 2025,
            ],
            [
                'student_id_no'      => 'ST0001', // Duplicate within batch
                'full_name'          => 'Student One Duplicate',
                'gender'             => 'Male',
                'dob'                => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status'  => 'Pending',
                'intake_year'        => 2025,
            ],
            [
                'student_id_no'      => 'ST0003',
                'full_name'          => 'Student Three',
                'gender'             => 'Male',
                'dob'                => '2000-03-01',
                'selection_batch_id' => 1,
                'enrollment_status'  => 'Pending',
                'intake_year'        => 2025,
            ],
            [
                'student_id_no'      => 'ST0002', // Duplicate within batch
                'full_name'          => 'Student Two Duplicate',
                'gender'             => 'Female',
                'dob'                => '2000-02-01',
                'selection_batch_id' => 1,
                'enrollment_status'  => 'Pending',
                'intake_year'        => 2025,
            ],
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/api/v1/imports/{$log->id}/commit", [
                'rows' => $rows,
            ]);

        // The commit should succeed (duplicates within batch are handled by DB)
        $response->assertStatus(200);

        $insertedCount = \App\Models\Student::count();
        $this->assertGreaterThanOrEqual(0, $insertedCount);
        $this->assertLessThanOrEqual(3, $insertedCount);

        // Verify import log reflects the result
        $log->refresh();
        $this->assertEquals('Completed', $log->status);
    }
}
