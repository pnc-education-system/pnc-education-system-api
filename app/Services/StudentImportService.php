<?php

namespace App\Services;

use App\Models\ImportError;
use App\Models\ImportLog;
use App\Models\SelectionBatch;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class StudentImportService
{
    private const CHUNK_SIZE = 100;

    public function process(ImportLog $importLog): array
    {
        $filePath = storage_path('app/' . $importLog->file_path);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Import file not found: {$filePath}");
        }

        $importLog->update(['status' => 'Processing']);

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $importLog->update(['status' => 'Failed']);
            throw new \RuntimeException('Unable to open import file.');
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $importLog->update(['status' => 'Failed']);
            throw new \RuntimeException('CSV file is empty or has no header row.');
        }

        $headers = array_map('trim', $headers);
        $expectedHeaders = ['student_id_no', 'full_name', 'gender', 'dob', 'phone', 'email', 'province', 'high_school', 'intake_year'];

        $headerMap = $this->mapHeaders($headers, $expectedHeaders);

        $totalImported = 0;
        $rowNumber = 1;

        $activeBatch = SelectionBatch::where('is_active', true)->first();
        if (!$activeBatch) {
            fclose($handle);
            $importLog->update(['status' => 'Failed']);
            throw new \RuntimeException('No active selection batch found. Please create one before importing.');
        }

        DB::beginTransaction();
        try {
            $chunk = [];
            $chunkErrors = [];

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $data = $this->mapRowToData($row, $headerMap);

                if ($this->isRowEmpty($data)) {
                    continue;
                }

                $errors = $this->validateRow($data, $rowNumber);
                if (!empty($errors)) {

                    foreach ($errors as &$error) {
                        $error['import_log_id'] = $importLog->id;
                    }
                    unset($error);
                    $chunkErrors = array_merge($chunkErrors, $errors);
                    continue;
                }

                $data['selection_batch_id'] = $activeBatch->id;
                $data['created_by'] = $importLog->imported_by;
                $data['enrollment_status'] = 'Pending';

                $data['gender'] = ucfirst(strtolower($data['gender']));
                if (!empty($data['intake_year'])) {
                    $data['intake_year'] = (int) $data['intake_year'];
                }

                $chunk[] = $data;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $this->flushChunk($chunk, $chunkErrors, $importLog);
                    $totalImported += count($chunk);
                    $chunk = [];
                    $chunkErrors = [];
                }
            }

            if (!empty($chunk)) {
                $this->flushChunk($chunk, $chunkErrors, $importLog);
                $totalImported += count($chunk);
            }

            if (!empty($chunkErrors)) {
                ImportError::insert($chunkErrors);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $importLog->update([
                'status' => 'Failed',
            ]);
            throw $e;
        }

        fclose($handle);

        $actualErrorCount = ImportError::where('import_log_id', $importLog->id)->count();

        $importLog->update([
            'success_count' => $totalImported,
            'error_count'   => $actualErrorCount,
            'total_rows'    => $rowNumber - 1,
            'status'        => 'Completed',
        ]);

        return [
            'success_count' => $totalImported,
            'error_count'   => $actualErrorCount,
        ];
    }

    private function mapHeaders(array $headers, array $expected): array
    {
        $headerMap = [];
        $lowerHeaders = array_map('strtolower', $headers);

        foreach ($expected as $field) {
            $index = array_search(strtolower($field), $lowerHeaders);
            if ($index !== false) {
                $headerMap[$field] = $index;
            }
        }

        return $headerMap;
    }

    private function mapRowToData(array $row, array $headerMap): array
    {
        $data = [];
        foreach ($headerMap as $field => $index) {
            $data[$field] = isset($row[$index]) ? trim($row[$index]) : '';
        }
        return $data;
    }

    private function isRowEmpty(array $data): bool
    {
        return empty(array_filter($data, fn($val) => $val !== '' && $val !== null));
    }

    private function validateRow(array $data, int $rowNumber): array
    {
        $errors = [];

        $requiredFields = ['student_id_no', 'full_name', 'gender'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field' => $field,
                    'error_message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.',
                ];
            }
        }

        if (!empty($data['student_id_no'])) {
            $exists = Student::where('student_id_no', $data['student_id_no'])->exists();
            if ($exists) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'field' => 'student_id_no',
                    'error_message' => "Student ID '{$data['student_id_no']}' already exists.",
                ];
            }
        }

        if (!empty($data['gender']) && !in_array(ucfirst(strtolower($data['gender'])), ['Male', 'Female'])) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field' => 'gender',
                'error_message' => "Gender must be 'Male' or 'Female'.",
            ];
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field' => 'email',
                'error_message' => "Invalid email format: '{$data['email']}'.",
            ];
        }

        if (!empty($data['dob']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['dob'])) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field' => 'dob',
                'error_message' => "Date of birth must be in YYYY-MM-DD format.",
            ];
        }

        if (!empty($data['intake_year']) && !preg_match('/^\d{4}$/', $data['intake_year'])) {
            $errors[] = [
                'row_number' => $rowNumber,
                'field' => 'intake_year',
                'error_message' => "Intake year must be a 4-digit year.",
            ];
        }

        return $errors;
    }

    private function flushChunk(array $students, array &$errors, ImportLog $importLog): void
    {
        Student::withoutTimestamps(function () use ($students) {
            Student::insert($students);
        });

        if (!empty($errors)) {
            ImportError::insert($errors);
            $errors = [];
        }
    }
}
