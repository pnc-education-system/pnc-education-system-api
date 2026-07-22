<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentIdCardTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $admin;
    private SelectionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        // Create role and assign the "students.view" permission (required by the route middleware)
        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create([
            'name'   => 'View Students',
            'slug'   => 'students.view',
            'module' => 'students',
        ]);
        $role->permissions()->sync([$perm->id]);

        // Create an active admin user
        $this->admin = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $role->id,
            'is_active' => true,
        ]);

        $this->token = JWTAuth::fromUser($this->admin);

        // Create a selection batch with a known year
        $this->batch = SelectionBatch::create([
            'name' => 'Batch 2026',
            'year' => 2026,
        ]);
    }

    /**
     * Mock the QrCode facade using Mockery's overload prefix.
     *
     * The simple-qrcode package requires the PHP GD extension for PNG output,
     * which may not be available in the test environment. The 'overload:' prefix
     * creates a runtime class definition so the facade can be mocked without
     * needing the package to be installed in vendor/.
     */
    private function mockQrCode(): void
    {
        $qrMock = \Mockery::mock('overload:SimpleSoftwareIO\QrCode\Facades\QrCode');
        $qrMock->shouldReceive('format')
            ->with('png')
            ->andReturnSelf();
        $qrMock->shouldReceive('size')
            ->with(200)
            ->andReturnSelf();
        $qrMock->shouldReceive('margin')
            ->with(2)
            ->andReturnSelf();
        $qrMock->shouldReceive('generate')
            ->andReturn('fake-qr-png-bytes');
    }

    // ------------------------------------------------------------------
    //  Happy path — 200 Success
    // ------------------------------------------------------------------

    /** @test */
    public function it_returns_id_card_data_for_a_student_with_photo_and_batch(): void
    {
        Storage::fake('public');
        $this->mockQrCode();

        // Arrange: create a student with a fake photo file (manual creation avoids GD dependency)
        Storage::disk('public')->put('students/photos/test.jpg', 'fake-image-content');
        $photoPath = 'students/photos/test.jpg';

        $student = Student::create([
            'student_id_no'      => 'PNC2026-001',
            'full_name'          => 'John Doe',
            'gender'             => 'Male',
            'dob'                => '2000-01-01',
            'high_school'        => 'Test High School',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status'  => 'Enrolled',
            'intake_year'        => 2026,
            'photo_path'         => $photoPath,
            'created_by'         => $this->admin->id,
        ]);

        // Act: call the endpoint
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/students/{$student->id}/id-card");

        // Assert: 200 with correct data structure and values
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'student_code',
                    'full_name',
                    'photo',
                    'batch',
                    'qr_code',
                    'template',
                ],
            ])
            ->assertJsonPath('data.student_code', 'PNC2026-001')
            ->assertJsonPath('data.full_name', 'John Doe')
            ->assertJsonPath('data.batch', '2026')
            ->assertJsonPath('data.template', 'default');

        // Photo URL should point to the stored file
        $this->assertNotNull($response->json('data.photo'));
        $this->assertStringContainsString($photoPath, $response->json('data.photo'));

        // QR code URL should reference this student
        $this->assertNotNull($response->json('data.qr_code'));
        $this->assertStringContainsString('qr/PNC2026-001.png', $response->json('data.qr_code'));
    }

    /** @test */
    public function it_returns_id_card_data_when_student_has_no_photo(): void
    {
        Storage::fake('public');
        $this->mockQrCode();

        // Arrange: create a student without a photo
        $student = Student::create([
            'student_id_no'      => 'PNC2026-002',
            'full_name'          => 'Jane Doe',
            'gender'             => 'Female',
            'dob'                => '2001-05-15',
            'high_school'        => 'Test High School',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status'  => 'Pending',
            'intake_year'        => 2026,
            'photo_path'         => null,
            'created_by'         => $this->admin->id,
        ]);

        // Act
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/students/{$student->id}/id-card");

        // Assert: photo is null, other fields still present
        $response->assertStatus(200)
            ->assertJsonPath('data.student_code', 'PNC2026-002')
            ->assertJsonPath('data.full_name', 'Jane Doe')
            ->assertJsonPath('data.photo', null)
            ->assertJsonPath('data.template', 'default');
    }

    // ------------------------------------------------------------------
    //  404 — Student not found
    // ------------------------------------------------------------------

    /** @test */
    public function it_returns_404_when_student_does_not_exist(): void
    {
        // Act: request a non-existent student ID
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/v1/students/99999/id-card');

        // Assert: ApiErrorEnvelopeMiddleware transforms to {"error": {...}} format
        $response->assertStatus(404)
            ->assertJson([
                'error' => [
                    'code'    => 404,
                    'message' => 'Student not found',
                ],
            ]);
    }

    // ------------------------------------------------------------------
    //  401 — Authentication required
    // ------------------------------------------------------------------

    /** @test */
    public function it_requires_authentication(): void
    {
        // Act: no auth header sent
        $response = $this->getJson('/api/v1/students/99999/id-card');

        // Assert: JWT middleware blocks with 401
        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  403 — Missing permission
    // ------------------------------------------------------------------

    /** @test */
    public function it_requires_students_view_permission(): void
    {
        // Arrange: create a user WITHOUT the "students.view" permission
        $viewerRole = Role::create(['name' => 'Viewer', 'slug' => 'viewer']);
        $viewer = User::create([
            'name'      => 'Viewer',
            'email'     => 'viewer@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $viewerRole->id,
            'is_active' => true,
        ]);
        $viewerToken = JWTAuth::fromUser($viewer);

        // Act: user without students.view permission
        $response = $this->withHeader('Authorization', "Bearer {$viewerToken}")
            ->getJson('/api/v1/students/1/id-card');

        // Assert: Permission middleware blocks with 403
        $response->assertStatus(403);
    }
}
