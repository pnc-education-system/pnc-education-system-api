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

    public function validate(array $rows): array
    {
        $this->loadExistingData();

        $dedupResult = $this->deduplicateRows($rows);
        $validationResult = $this->validateRows($dedupResult['uniqueRows']);

        $allInvalid = array_merge($dedupResult['removedDuplicates'], $validationResult['invalidRows']);

        return [
            'validRows'   => $validationResult['validRows'],
            'invalidRows' => $allInvalid,
            'summary'     => $this->buildSummary(count($rows), count($validationResult['validRows']), count($allInvalid)),
        ];
    }

    private function validateRows(array $rowsWithMeta): array
    {
        $validRows = [];
        $invalidRows = [];

        foreach ($rowsWithMeta as $entry) {
            $row = $entry['row'];
            $rowNumber = $entry['rowNumber'];
            $validation = $this->validateRow($row, $rowNumber);

            if ($validation['isValid']) {
                $validRows[] = $row;
            } else {
                $invalidRows[] = $this->buildInvalidRow($rowNumber, $row, $validation['errors']);
            }
        }

        return compact('validRows', 'invalidRows');
    }

    private function deduplicateRows(array $rows): array
    {
        $best = [];
        $removed = [];

        foreach ($rows as $index => $row) {
            $studentId = $row['student_id_no'] ?? null;
            $rowNumber = $index + 1;

            if (!$studentId) {
                $best[] = ['row' => $row, 'rowNumber' => $rowNumber, 'score' => $this->rowCompleteness($row)];
                continue;
            }

            if (!isset($best[$studentId])) {
                $best[$studentId] = ['row' => $row, 'rowNumber' => $rowNumber, 'score' => $this->rowCompleteness($row)];
                continue;
            }

            $existing = &$best[$studentId];
            $currentScore = $this->rowCompleteness($row);

            if ($currentScore > $existing['score']) {
                $removed[] = $this->buildInvalidRow(
                    $existing['rowNumber'], $existing['row'],
                    ['student_id_no' => ['Duplicate removed. A more complete entry for this Student ID was found later in the file and kept instead.']]
                );
                $existing = ['row' => $row, 'rowNumber' => $rowNumber, 'score' => $currentScore];
            } else {
                $removed[] = $this->buildInvalidRow(
                    $rowNumber, $row,
                    ['student_id_no' => ['Duplicate removed. A more complete entry for this Student ID already exists earlier in the file.']]
                );
            }
        }

        return [
            'uniqueRows' => array_values($best),
            'removedDuplicates' => $removed,
        ];
    }

    private function rowCompleteness(array $row): int
    {
        $count = 0;
        foreach ($row as $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $count++;
            }
        }
        return $count;
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

        $this->applyFieldValidation($row, $errors);
        $this->validateStudentIdUniqueness($row, $errors);
        $this->validateBatchExistence($row, $errors);

        return $errors;
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
            'student_id_no' => ['nullable', 'string', 'max:50', new StudentIdFormat()],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'in:Male,Female,Other'],
            'dob' => ['required', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'high_school' => ['nullable', 'string', 'max:255'],
            'selection_batch_id' => ['required', 'integer'],
            'enrollment_status' => ['sometimes', 'in:Pending,Enrolled,Rejected,Graduated,Dropped'],
            'intake_year' => ['required', 'integer', 'digits:4'],
        ];
    }

    private function getCustomMessages(): array
    {
        return [
            'student_id_no.max' => 'Student ID must not exceed 50 characters.',
            'full_name.required' => 'Full name is required.',
            'full_name.max' => 'Full name must not exceed 255 characters.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be Male, Female, or Other.',
            'dob.required' => 'Date of birth is required.',
            'dob.date' => 'Date of birth must be a valid date.',
            'dob.before' => 'Date of birth cannot be in the future.',
            'phone.regex' => 'Phone number format is invalid.',
            'high_school.required' => 'High school is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.max' => 'Email must not exceed 255 characters.',
            'province.required' => 'Province is required.',
            'selection_batch_id.required' => 'Selection batch is required.',
            'selection_batch_id.integer' => 'Selection batch ID must be an integer.',
            'enrollment_status.in' => 'Enrollment status must be one of: Pending, Enrolled, Rejected, Graduated, Dropped.',
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

        if ($batchId && !in_array((int)$batchId, array_map('intval', $this->existingBatchIds), true)) {
            $errors['selection_batch_id'][] = 'Selected batch does not exist.';
        }
    }
}
