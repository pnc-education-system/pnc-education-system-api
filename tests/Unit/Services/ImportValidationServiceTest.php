<?php

namespace Tests\Unit\Services;

use App\Models\SelectionBatch;
use App\Models\Student;
use App\Services\ImportValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ImportValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new ImportValidationService();
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $rows = [
            [
                'student_id_no' => '',
                'full_name' => '',
                'gender' => '',
                'dob' => '',
                'selection_batch_id' => '',
                'enrollment_status' => '',
                'intake_year' => '',
            ],
        ];

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('student_id_no', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('full_name', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('gender', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('dob', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('selection_batch_id', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('enrollment_status', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('intake_year', $result['invalidRows'][0]['errors']);
    }

    /** @test */
    public function it_validates_gender_values()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Invalid',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('gender', $result['invalidRows'][0]['errors']);
    }

    /** @test */
    public function it_accepts_valid_gender_values()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
            [
                'student_id_no' => 'ST0002',
                'full_name' => 'Jane Doe',
                'gender' => 'Female',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(2, $result['validRows']);
        $this->assertCount(0, $result['invalidRows']);
    }

    /** @test */
    public function it_validates_date_of_birth_not_in_future()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2050-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('dob', $result['invalidRows'][0]['errors']);
    }

    /** @test */
    public function it_validates_email_format()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'email' => 'invalid-email',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('email', $result['invalidRows'][0]['errors']);
    }

    /** @test */
    public function it_accepts_valid_email()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'email' => 'john@example.com',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['validRows']);
        $this->assertCount(0, $result['invalidRows']);
    }

    /** @test */
    public function it_validates_phone_number_format()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'phone' => 'abc',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('phone', $result['invalidRows'][0]['errors']);
    }

    /** @test */
    public function it_validates_intake_year_format()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 24,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('intake_year', $result['invalidRows'][0]['errors']);
    }

    /** @test */
    public function it_detects_duplicate_student_ids_within_file()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
            [
                'student_id_no' => 'ST0002',
                'full_name' => 'Jane Doe',
                'gender' => 'Female',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'Bob Smith',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(3, $result['invalidRows']);
        $this->assertArrayHasKey('student_id_no', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('student_id_no', $result['invalidRows'][2]['errors']);
    }

    /** @test */
    public function it_detects_duplicate_student_ids_against_database()
    {
        Student::create([
            'student_id_no' => 'ST0001',
            'full_name' => 'Existing Student',
            'gender' => 'Male',
            'dob' => '2000-01-01',
            'selection_batch_id' => 1,
            'enrollment_status' => 'Active',
            'intake_year' => 2024,
            'created_by' => 1,
        ]);

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('student_id_no', $result['invalidRows'][0]['errors']);
        $this->assertContains('Student ID already exists.', $result['invalidRows'][0]['errors']['student_id_no']);
    }

    /** @test */
    public function it_validates_batch_existence()
    {
        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 999,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('selection_batch_id', $result['invalidRows'][0]['errors']);
        $this->assertContains('Selected batch does not exist.', $result['invalidRows'][0]['errors']['selection_batch_id']);
    }

    /** @test */
    public function it_accepts_valid_batch_id()
    {
        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['validRows']);
        $this->assertCount(0, $result['invalidRows']);
    }

    /** @test */
    public function it_returns_correct_summary()
    {
        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
            [
                'student_id_no' => 'ST0002',
                'full_name' => 'Jane Doe',
                'gender' => 'Female',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
            [
                'student_id_no' => '',
                'full_name' => 'Invalid',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        $result = $this->validationService->validate($rows);

        $this->assertEquals(3, $result['summary']['total']);
        $this->assertEquals(2, $result['summary']['valid']);
        $this->assertEquals(1, $result['summary']['invalid']);
    }

    /** @test */
    public function it_handles_optional_fields_gracefully()
    {
        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $rows = [
            [
                'student_id_no' => 'ST0001',
                'full_name' => 'John Doe',
                'gender' => 'Male',
                'dob' => '2000-01-01',
                'phone' => null,
                'email' => null,
                'province' => null,
                'high_school' => null,
                'selection_batch_id' => 1,
                'enrollment_status' => 'Active',
                'intake_year' => 2024,
            ],
        ];

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['validRows']);
        $this->assertCount(0, $result['invalidRows']);
    }

    /** @test */
    public function it_validates_multiple_errors_per_row()
    {
        SelectionBatch::create(['id' => 1, 'name' => 'Batch 1', 'created_by' => 1]);

        $rows = [
            [
                'student_id_no' => '',
                'full_name' => '',
                'gender' => 'Invalid',
                'dob' => '2050-01-01',
                'email' => 'invalid-email',
                'selection_batch_id' => 999,
                'enrollment_status' => 'Active',
                'intake_year' => 24,
            ],
        ];

        $result = $this->validationService->validate($rows);

        $this->assertCount(1, $result['invalidRows']);
        $this->assertArrayHasKey('student_id_no', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('full_name', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('gender', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('dob', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('email', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('selection_batch_id', $result['invalidRows'][0]['errors']);
        $this->assertArrayHasKey('intake_year', $result['invalidRows'][0]['errors']);
    }
}
