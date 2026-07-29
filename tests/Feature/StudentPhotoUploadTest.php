<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private SelectionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $permissions = [
            Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']),
            Permission::create(['name' => 'Edit Students', 'slug' => 'students.edit', 'module' => 'students']),
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
    }

    /** @test
     *  Simulates the frontend flow:
     *  1. Update student details via PUT JSON (no photo)
     *  2. Upload photo via POST /students/{id}/photo
     */
    public function it_updates_student_via_json_then_uploads_photo_separately(): void
    {
        Storage::fake('public');

        $student = $this->createStudent();

        // ── Step 1: Update student via PUT JSON (no photo) ──
        $updateResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/students/{$student->id}", [
                'student_id_no' => 'ST9999',
                'full_name' => 'Two-Step Updated',
                'gender' => 'Female',
                'dob' => '2005-06-15',
                'phone' => '098765432',
                'email' => 'twostep@example.com',
                'province' => 'Phnom Penh',
                'high_school' => 'New High School',
                'selection_batch_id' => $this->batch->id,
                'enrollment_status' => Student::STATUS_ENROLLED,
                'intake_year' => 2026,
                // NOTE: No 'photo' field — photo is uploaded separately
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('message', 'Student updated successfully')
            ->assertJsonPath('data.student_id_no', 'ST9999')
            ->assertJsonPath('data.full_name', 'Two-Step Updated')
            ->assertJsonPath('data.selection_batch_id', $this->batch->id)
            ->assertJsonPath('data.enrollment_status', Student::STATUS_ENROLLED)
            ->assertJsonPath('data.intake_year', 2026);

        // Verify student has no photo yet
        $student->refresh();
        $this->assertNull($student->photo_path);

        // ── Step 2: Upload photo via dedicated endpoint ──
        $photo = UploadedFile::fake()->image('student.jpg', 300, 300);

        $photoResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$student->id}/photo", [
                'photo' => $photo,
            ]);

        $photoResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Student photo uploaded successfully')
            ->assertJsonStructure([
                'data' => ['photo_url'],
            ]);

        // ── Verify final state ──
        $student->refresh();
        $this->assertNotNull($student->photo_path);
        Storage::disk('public')->assertExists($student->photo_path);

        // Verify student details are still correct from step 1
        $this->assertEquals('ST9999', $student->student_id_no);
        $this->assertEquals('Two-Step Updated', $student->full_name);
        $this->assertEquals('Female', $student->gender);

        // Verify photo URL is accessible
        $photoUrl = $photoResponse->json('data.photo_url');
        $this->assertStringContainsString('storage/', $photoUrl);
        $this->assertStringContainsString($student->photo_path, $photoUrl);
    }

    /** @test
     *  Verifies that updating a student with nullable fields set to null works
     *  (simulates frontend sending null/empty optional fields for dob, email, phone, etc.)
     */
    public function it_updates_student_with_nullable_fields_omitted(): void
    {
        Storage::fake('public');

        $student = $this->createStudent();

        // Send only required fields — simulate frontend skipping null optional fields
        $updateResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/v1/students/{$student->id}", [
                'student_id_no' => $student->student_id_no,
                'full_name' => 'Minimal Update',
                'gender' => 'Male',
                'selection_batch_id' => $this->batch->id,
                'enrollment_status' => Student::STATUS_PENDING,
                // dob, phone, email, province, high_school, intake_year, enrolled_at are omitted (like frontend skipping nulls)
            ]);

        $updateResponse->assertStatus(200);

        $student->refresh();
        $this->assertEquals('Minimal Update', $student->full_name);
        // Nullable fields should retain original values since omitted
        $this->assertEquals('student@example.com', $student->email);
        $this->assertEquals('012345678', $student->phone);

        // Now upload photo (should work even with minimal update)
        $photo = UploadedFile::fake()->image('photo.jpg');
        $photoResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$student->id}/photo", [
                'photo' => $photo,
            ]);

        $photoResponse->assertStatus(200);
        $student->refresh();
        $this->assertNotNull($student->photo_path);
    }

    /** @test
     *  Verifies the full create-then-upload flow (simulates frontend create flow)
     */
    public function it_creates_student_then_uploads_photo(): void
    {
        Storage::fake('public');

        // Step 1: Create student via POST JSON (no photo)
        $createResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/students", [
                'student_id_no' => 'ST-NEW-001',
                'full_name' => 'Newly Created',
                'gender' => 'Female',
                'dob' => '2006-03-20',
                'phone' => '011223344',
                'email' => 'new@example.com',
                'province' => 'Kampong Cham',
                'high_school' => 'Create High School',
                'selection_batch_id' => $this->batch->id,
                'enrollment_status' => Student::STATUS_PENDING,
                'intake_year' => 2026,
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.student_id_no', 'ST-NEW-001');

        $studentId = $createResponse->json('data.id');

        // Step 2: Upload photo to created student
        $photo = UploadedFile::fake()->image('new-student.jpg');
        $photoResponse = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$studentId}/photo", [
                'photo' => $photo,
            ]);

        $photoResponse->assertStatus(200);

        $student = Student::find($studentId);
        $this->assertNotNull($student);
        $this->assertNotNull($student->photo_path);
        Storage::disk('public')->assertExists($student->photo_path);
    }

    /** @test
     *  Uploading a photo to a non-existent student returns 404
     */
    public function it_returns_404_when_uploading_photo_to_nonexistent_student(): void
    {
        Storage::fake('public');

        $photo = UploadedFile::fake()->image('ghost.jpg');

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post('/api/v1/students/99999/photo', [
                'photo' => $photo,
            ]);

        $response->assertStatus(404);
    }

    /** @test
     *  Uploading a non-image file returns 422
     */
    public function it_rejects_uploading_non_image_file(): void
    {
        Storage::fake('public');

        $student = $this->createStudent();
        $textFile = UploadedFile::fake()->create('document.txt', 100);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$student->id}/photo", [
                'photo' => $textFile,
            ]);

        $response->assertStatus(422);
    }

    /** @test
     *  Uploading a photo larger than 4MB returns 422
     */
    public function it_rejects_oversized_photo(): void
    {
        Storage::fake('public');

        $student = $this->createStudent();

        // Create a fake image that exceeds the 10MB limit (10240 KB)
        $largePhoto = UploadedFile::fake()->image('large.jpg')->size(15000);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$student->id}/photo", [
                'photo' => $largePhoto,
            ]);

        $response->assertStatus(422);
    }

    /** @test
     *  Uploading a photo replaces the old photo and deletes it from storage
     */
    public function it_replaces_existing_photo_and_deletes_old_one(): void
    {
        Storage::fake('public');

        // Start with a student that has an existing photo
        Storage::disk('public')->put('students/photos/old-photo.jpg', 'old-image-content');
        $student = $this->createStudent(['photo_path' => 'students/photos/old-photo.jpg']);

        // Upload a new photo
        $newPhoto = UploadedFile::fake()->image('new-photo.jpg', 200, 200);
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->post("/api/v1/students/{$student->id}/photo", [
                'photo' => $newPhoto,
            ]);

        $response->assertStatus(200);

        $student->refresh();
        $this->assertNotNull($student->photo_path);
        $this->assertNotEquals('students/photos/old-photo.jpg', $student->photo_path);

        // Old photo should be deleted
        Storage::disk('public')->assertMissing('students/photos/old-photo.jpg');
        // New photo should exist
        Storage::disk('public')->assertExists($student->photo_path);
    }

    /** @test
     *  Requires authentication for both update and photo upload
     */
    public function it_requires_authentication_for_photo_upload(): void
    {
        Storage::fake('public');

        $student = $this->createStudent();
        $photo = UploadedFile::fake()->image('no-auth.jpg');

        $response = $this->post("/api/v1/students/{$student->id}/photo", [
            'photo' => $photo,
        ]);

        $response->assertStatus(401);
    }

    /** @test
     *  Requires students.edit permission for photo upload
     */
    public function it_requires_edit_permission_for_photo_upload(): void
    {
        Storage::fake('public');

        // Create a user without students.edit permission
        // Reuse the view permission already created in setUp
        $viewPerm = Permission::where('slug', 'students.view')->firstOrFail();
        $viewOnlyRole = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $viewOnlyRole->permissions()->sync([$viewPerm->id]);

        $viewer = User::create([
            'name' => 'Viewer',
            'email' => 'viewer@test.com',
            'password' => Hash::make('password'),
            'role_id' => $viewOnlyRole->id,
            'is_active' => true,
        ]);
        $viewerToken = JWTAuth::fromUser($viewer);

        $student = $this->createStudent();
        $photo = UploadedFile::fake()->image('no-perm.jpg');

        $response = $this->withHeader('Authorization', "Bearer {$viewerToken}")
            ->post("/api/v1/students/{$student->id}/photo", [
                'photo' => $photo,
            ]);

        $response->assertStatus(403);
    }

    private function createStudent(array $overrides = []): Student
    {
        return Student::create(array_merge([
            'student_id_no' => 'ST0001',
            'full_name' => 'Original Student',
            'gender' => 'Male',
            'dob' => '2005-01-15',
            'phone' => '012345678',
            'email' => 'student@example.com',
            'province' => 'Phnom Penh',
            'high_school' => 'ABC High School',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => Student::STATUS_PENDING,
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ], $overrides));
    }
}
