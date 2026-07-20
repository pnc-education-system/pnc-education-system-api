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

class StudentCardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected SelectionBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Admin', 'slug' => 'administrator']);
        $perm = Permission::create(['name' => 'View Students', 'slug' => 'students.view', 'module' => 'students']);
        $role->permissions()->sync([$perm->id]);

        $this->user = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@test.com',
            'password'  => Hash::make('password'),
            'role_id'   => $role->id,
            'is_active' => true,
        ]);

        $this->token = JWTAuth::fromUser($this->user);
        $this->batch = SelectionBatch::create(['name' => 'Batch 2026', 'year' => 2026]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_blocks_card_generation_when_student_has_no_photo()
    {
        $student = Student::create([
            'student_id_no'      => 'STU-2026-001',
            'full_name'          => 'John Doe',
            'gender'             => 'Male',
            'dob'                => '2000-01-01',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status'  => 'Enrolled',
            'intake_year'        => 2026,
            'photo_path'         => null,
            'created_by'         => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson("/api/v1/students/{$student->id}/card");

        $response->assertStatus(422)
                 ->assertJson([
                     'error' => [
                         'code'    => 422,
                         'message' => 'Cannot generate ID card: Student photo is missing (BR-5 Guard).',
                     ],
                 ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_generates_a4_pdf_card_when_student_has_photo()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.jpg', 200, 200);
        $photoPath = $file->store('students/photos', 'public');

        $student = Student::create([
            'student_id_no'      => 'STU-2026-002',
            'full_name'          => 'Jane Doe',
            'gender'             => 'Female',
            'dob'                => '2001-05-15',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status'  => 'Enrolled',
            'intake_year'        => 2026,
            'photo_path'         => $photoPath,
            'created_by'         => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
                         ->postJson("/api/v1/students/{$student->id}/card");

        $response->assertStatus(200)
                 ->assertHeader('content-type', 'application/pdf');
    }
}
