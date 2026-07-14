<?php

namespace App\Services\Student;

use App\Models\SelectionBatch;
use App\Models\Student;
use App\Rules\Student\StudentIdFormat;
use Illuminate\Support\Facades\Validator;

class ImportValidationService
{
    private array $existingStudentIds = [];
    private array $existingBatchIds = [];
    private array $duplicateStudentIds = [];

    public function validate(array $rows): array
    {
        $this->loadExistingData();
        $this->detectFileDuplicates($rows);

        return $this->processRows($rows);
    }

    private function processRows(array $rows): array
    {
        $validRows = [];
        $invalidRows = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;
            $validation = $this->validateRow($row, $rowNumber);

            if ($validation['isValid']) {
                $validRows[] = $row;
            } else {
                $invalidRows[] = $this->buildInvalidRow($rowNumber, $row, $validation['errors']);
            }
        }

        return [
            'validRows' => $validRows,
            'invalidRows' => $invalidRows,
            'summary' => $this->buildSummary(count($rows), count($validRows), count($invalidRows)),
        ];
    }

    private function buildInvalidRow(int $rowNumber, array $row, array $errors): array
    {
        return [
            'row' => $rowNumber,
            'data' => $row,
            'errors' => $errors,
        ];
    }

    private function buildSummary(int $total, int $valid, int $invalid): array
    {
        return [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }

    private function loadExistingData(): void
    {
        $this->existingStudentIds = Student::pluck('student_id_no')->toArray();
        $this->existingBatchIds = SelectionBatch::pluck('id')->toArray();
    }

    private function detectFileDuplicates(array $rows): void
    {
        $studentIdCounts = array_count_values(
            array_filter(array_column($rows, 'student_id_no'))
        );

        $this->duplicateStudentIds = array_keys(
            array_filter($studentIdCounts, fn($count) => $count > 1)
        );
    }

    private function validateRow(array $row, int $rowNumber): array
    {
        $errors = $this->collectErrors($row);

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
        ];
    }

    private function collectErrors(array $row): array
    {
        $errors = [];

        $this->checkFileDuplicate($row, $errors);
        $this->applyFieldValidation($row, $errors);
        $this->validateStudentIdUniqueness($row, $errors);
        $this->validateBatchExistence($row, $errors);

        return $errors;
    }

    private function checkFileDuplicate(array $row, array &$errors): void
    {
        $studentId = $row['student_id_no'] ?? null;

        if ($studentId && in_array($studentId, $this->duplicateStudentIds, true)) {
            $errors['student_id_no'][] = 'Duplicate Student ID found in uploaded file.';
        }
    }

    private function applyFieldValidation(array $row, array &$errors): void
    {
        $validator = Validator::make($row, $this->getValidationRules(), $this->getCustomMessages());

        if ($validator->fails()) {
            $errors = array_merge_recursive($errors, $validator->errors()->toArray());
        }
    }

    private function getValidationRules(): array
    {
        return [
            'student_id_no' => ['required', 'string', 'max:50', new StudentIdFormat()],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female'],
            'dob' => ['required', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'high_school' => ['nullable', 'string', 'max:255'],
            'selection_batch_id' => ['required', 'integer'],
            'enrollment_status' => ['required', 'in:Pending,Enrolled,Rejected,Graduated,Dropped'],
            'intake_year' => ['required', 'integer', 'digits:4'],
        ];
    }

    private function getCustomMessages(): array
    {
        return [
            'student_id_no.required' => 'Student ID is required.',
            'student_id_no.max' => 'Student ID must not exceed 50 characters.',
            'full_name.required' => 'Full name is required.',
            'full_name.max' => 'Full name must not exceed 255 characters.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be either Male or Female.',
            'dob.required' => 'Date of birth is required.',
            'dob.date' => 'Date of birth must be a valid date.',
            'dob.before' => 'Date of birth cannot be in the future.',
            'phone.regex' => 'Phone number format is invalid.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email must not exceed 255 characters.',
            'selection_batch_id.required' => 'Selection batch is required.',
            'selection_batch_id.integer' => 'Selection batch ID must be an integer.',
            'enrollment_status.required' => 'Enrollment status is required.',
            'enrollment_status.max' => 'Enrollment status must not exceed 50 characters.',
            'intake_year.required' => 'Intake year is required.',
            'intake_year.integer' => 'Intake year must be an integer.',
            'intake_year.digits' => 'Intake year must be a 4-digit year.',
        ];
    }

    private function validateStudentIdUniqueness(array $row, array &$errors): void
    {
        $studentId = $row['student_id_no'] ?? null;

        if ($studentId && in_array($studentId, $this->existingStudentIds, true)) {
            $errors['student_id_no'][] = 'Student ID already exists.';
        }
    }

    private function validateBatchExistence(array $row, array &$errors): void
    {
        $batchId = $row['selection_batch_id'] ?? null;

        if ($batchId && !in_array($batchId, $this->existingBatchIds, true)) {
            $errors['selection_batch_id'][] = 'Selected batch does not exist.';
        }
    }
}
