<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class RecordAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private SelectionBatch $batch;
    private Student $student;
    private StudentRecord $record;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $permissions = [
            Permission::create(['name' => 'View Records', 'slug' => 'records.view', 'module' => 'records']),
            Permission::create(['name' => 'Manage Records', 'slug' => 'records.manage', 'module' => 'records']),
        ];
        $role->permissions()->sync(collect($permissions)->pluck('id'));

        $this->user = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        $this->batch = SelectionBatch::create(['name' => 'Batch 2025', 'year' => 2025]);

        $this->student = Student::create([
            'student_id_no' => 'ST0001',
            'full_name' => 'Test Student',
            'gender' => 'Male',
            'dob' => '2005-06-15',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => Student::STATUS_PENDING,
            'created_by' => $this->user->id,
        ]);

        $this->record = StudentRecord::create([
            'student_id' => $this->student->id,
            'category' => 'academic',
            'title' => 'Midterm Report',
            'description' => 'First semester grades',
            'record_date' => '2026-07-15',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_upload_image_attachment_successfully(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('transcript.png');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Attachment uploaded successfully.')
            ->assertJsonStructure([
                'status', 'message', 'data' => ['id', 'file_path', 'file_type', 'file_size', 'uploaded_by', 'created_at'],
            ]);

        $this->assertDatabaseHas('record_attachments', [
            'student_record_id' => $this->record->id,
            'file_type' => 'image/png',
            'uploaded_by' => $this->user->id,
        ]);

        Storage::disk('public')->assertExists($response->json('data.file_path'));
    }

    public function test_upload_pdf_attachment_successfully(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('record_attachments', [
            'student_record_id' => $this->record->id,
            'file_type' => 'application/pdf',
        ]);

        Storage::disk('public')->assertExists($response->json('data.file_path'));
    }

    public function test_upload_docx_attachment_successfully(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('report.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('record_attachments', [
            'student_record_id' => $this->record->id,
            'file_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        Storage::disk('public')->assertExists($response->json('data.file_path'));
    }

    public function test_reject_unsupported_file_type_with_clear_message(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422);

        $this->assertStringContainsString(
            'Unsupported file type',
            $response->json('error.message')
        );
    }

    public function test_reject_oversized_file_with_clear_message(): void
    {
        Storage::fake('public');

        $maxKb = (int) config('records.attachment.max_size_kb', 10240);
        $file = UploadedFile::fake()->create('large.pdf', $maxKb * 2, 'application/pdf');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422);

        $this->assertStringContainsString(
            'exceeds the maximum allowed size',
            $response->json('error.message')
        );
    }

    public function test_return_404_when_record_not_found(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/records/99999/attachments', [
                'file' => $file,
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 404)
            ->assertJsonPath('error.message', 'Record not found.');
    }

    public function test_require_authentication(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->postJson("/api/v1/records/{$this->record->id}/attachments", [
            'file' => $file,
        ]);

        $response->assertStatus(401);
    }

    public function test_require_records_manage_permission(): void
    {
        Storage::fake('public');

        $role = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'records.view'],
            ['name' => 'View Records', 'module' => 'records']
        );
        $role->permissions()->sync([$viewPerm->id]);

        $viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@test.com',
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $viewerToken = JWTAuth::fromUser($viewer);

        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->withHeader('Authorization', "Bearer {$viewerToken}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }

    public function test_reject_when_no_file_provided(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/records/{$this->record->id}/attachments", []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 422);

        $this->assertStringContainsString(
            'No file was provided',
            $response->json('error.message')
        );
    }
}
