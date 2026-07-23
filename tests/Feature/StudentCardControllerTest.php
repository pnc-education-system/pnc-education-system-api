<?php

namespace Tests\Feature;

use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCardControllerTest extends TestCase
{
    use RefreshDatabase;

    private SelectionBatch $batch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->batch = SelectionBatch::factory()->create([
            'name' => 'Batch 2025 - Spring',
            'year' => 2025,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_returns_student_profile_when_student_id_no_exists(): void
    {
        // Arrange
        $student = Student::factory()->create([
            'student_id_no' => 'STU-2025-0001',
            'full_name' => 'Sok Chan',
            'gender' => 'Male',
            'dob' => '2000-05-15',
            'province' => 'Phnom Penh',
            'high_school' => 'High School A',
            'phone' => '012345678',
            'email' => 'sok.chan@example.com',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => 'Enrolled',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
            'is_confirmed' => true,
        ]);

        // Act
        $response = $this->getJson('/api/v1/student-cards/student/STU-2025-0001');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'student_id_no',
                    'full_name',
                    'gender',
                    'dob',
                    'phone',
                    'email',
                    'province',
                    'high_school',
                    'photo_path',
                    'enrollment_status',
                    'intake_year',
                    'selection_batch_name',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Student profile retrieved successfully.',
                'data' => [
                    'student_id_no' => 'STU-2025-0001',
                    'full_name' => 'Sok Chan',
                    'gender' => 'Male',
                    'enrollment_status' => 'Enrolled',
                    'selection_batch_name' => 'Batch 2025 - Spring',
                ],
            ]);
    }

    /** @test */
    public function it_returns_404_when_student_id_no_does_not_exist(): void
    {
        // Act
        $response = $this->getJson('/api/v1/student-cards/student/NONEXISTENT-001');

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Student not found with the given ID.',
            ]);
    }

    /** @test */
    public function it_includes_contact_and_education_details_in_response(): void
    {
        // Arrange
        Student::factory()->create([
            'student_id_no' => 'STU-2025-0002',
            'full_name' => 'Chea Rithy',
            'email' => 'chea.rithy@example.com',
            'phone' => '098765432',
            'high_school' => 'Bak Touk High School',
            'selection_batch_id' => $this->batch->id,
            'created_by' => $this->user->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/student-cards/student/STU-2025-0002');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'student_id_no' => 'STU-2025-0002',
                    'full_name' => 'Chea Rithy',
                    'email' => 'chea.rithy@example.com',
                    'phone' => '098765432',
                    'high_school' => 'Bak Touk High School',
                ],
            ]);
    }

    /** @test */
    public function it_can_find_student_with_special_characters_in_id(): void
    {
        // Arrange
        Student::factory()->create([
            'student_id_no' => 'PNC-2026-1234',
            'full_name' => 'Special Case',
            'selection_batch_id' => $this->batch->id,
            'created_by' => $this->user->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/student-cards/student/PNC-2026-1234');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.student_id_no', 'PNC-2026-1234');
        $response->assertJsonPath('data.full_name', 'Special Case');
    }

    /** @test */
    public function it_returns_404_for_empty_student_id(): void
    {
        // Act
        $response = $this->getJson('/api/v1/student-cards/student/');

        // Assert
        $response->assertStatus(404);
    }

    // ──── resolveQr endpoint tests ────

    /** @test */
    public function it_returns_student_profile_when_qr_token_exists(): void
    {
        // Arrange
        $student = Student::factory()->create([
            'student_id_no' => 'STU-2025-QR01',
            'full_name' => 'QR Student One',
            'gender' => 'Female',
            'selection_batch_id' => $this->batch->id,
            'enrollment_status' => 'Enrolled',
            'intake_year' => 2025,
            'created_by' => $this->user->id,
        ]);

        $card = StudentCard::factory()->create([
            'student_id' => $student->id,
        ]);

        $qrToken = $card->qr_token;
        $this->assertNotNull($qrToken, 'QR token should be auto-generated');

        // Act
        $response = $this->getJson('/api/v1/student-cards/qr/' . urlencode($qrToken));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'student_id_no',
                    'full_name',
                    'gender',
                    'enrollment_status',
                    'intake_year',
                    'selection_batch_name',
                    'qr_token',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Student profile retrieved successfully.',
                'data' => [
                    'student_id_no' => 'STU-2025-QR01',
                    'full_name' => 'QR Student One',
                    'gender' => 'Female',
                    'enrollment_status' => 'Enrolled',
                    'selection_batch_name' => 'Batch 2025 - Spring',
                ],
            ]);

        // Verify the same qr_token is echoed back in the response
        $response->assertJsonPath('data.qr_token', $qrToken);
    }

    /** @test */
    public function it_returns_404_when_qr_token_does_not_exist(): void
    {
        // Act — use a valid UUID format that doesn't exist in the database
        $fakeToken = '00000000-0000-0000-0000-000000000000';
        $response = $this->getJson('/api/v1/student-cards/qr/' . $fakeToken);

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired QR token.',
            ]);
    }

    /** @test */
    public function it_returns_404_for_malformed_qr_token(): void
    {
        // Act
        $response = $this->getJson('/api/v1/student-cards/qr/not-a-valid-uuid');

        // Assert
        $response->assertStatus(404);
    }

    /** @test */
    public function it_resolves_qr_token_and_returns_contact_info(): void
    {
        // Arrange
        $student = Student::factory()->create([
            'student_id_no' => 'STU-2025-QR02',
            'full_name' => 'QR Student Two',
            'email' => 'qr.two@example.com',
            'phone' => '011223344',
            'province' => 'Siem Reap',
            'selection_batch_id' => $this->batch->id,
            'created_by' => $this->user->id,
        ]);

        $card = StudentCard::factory()->create([
            'student_id' => $student->id,
        ]);

        // Act
        $response = $this->getJson('/api/v1/student-cards/qr/' . urlencode($card->qr_token));

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'full_name' => 'QR Student Two',
                    'email' => 'qr.two@example.com',
                    'phone' => '011223344',
                    'province' => 'Siem Reap',
                ],
            ]);
    }

}

