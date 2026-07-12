<?php

namespace App\Services;

use App\Models\ImportError;
use App\Models\ImportLog;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentImportService
{
    private ImportValidationService $validationService;

    public function __construct(ImportValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    public function import(array $rows, string $fileName, int $userId): array
    {
        $validationResult = $this->validationService->validate($rows);
        $importLog = $this->createImportLog($fileName, $userId, $validationResult['summary']);

        $this->logValidationErrors($importLog->id, $validationResult['invalidRows']);
        $importedCount = $this->importValidRowsIfAny($validationResult['validRows'], $userId);

        $importLog->update(['success_rows' => $importedCount]);

        return $this->buildImportResult($validationResult, $importedCount, $importLog->id);
    }

    private function createImportLog(string $fileName, int $userId, array $summary): ImportLog
    {
        return ImportLog::create([
            'file_name' => $fileName,
            'imported_by' => $userId,
            'total_rows' => $summary['total'],
            'success_rows' => 0,
            'failed_rows' => $summary['invalid'],
        ]);
    }

    private function importValidRowsIfAny(array $validRows, int $userId): int
    {
        return empty($validRows) ? 0 : $this->importValidRows($validRows, $userId);
    }

    private function buildImportResult(array $validationResult, int $importedCount, int $importLogId): array
    {
        return [
            'validation' => $validationResult,
            'import' => [
                'imported' => $importedCount,
                'failed' => $validationResult['summary']['invalid'],
                'import_log_id' => $importLogId,
            ],
        ];
    }

    private function importValidRows(array $validRows, int $userId): int
    {
        $importedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($validRows as $row) {
                if ($this->importSingleRow($row, $userId)) {
                    $importedCount++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student import transaction failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $importedCount;
    }

    private function importSingleRow(array $row, int $userId): bool
    {
        try {
            Student::create($this->buildStudentData($row, $userId));
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to import student row', [
                'row' => $row,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function buildStudentData(array $row, int $userId): array
    {
        return [
            'student_id_no' => $row['student_id_no'],
            'full_name' => $row['full_name'],
            'gender' => $row['gender'],
            'dob' => $row['dob'],
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'province' => $row['province'] ?? null,
            'high_school' => $row['high_school'] ?? null,
            'selection_batch_id' => $row['selection_batch_id'],
            'enrollment_status' => $row['enrollment_status'],
            'intake_year' => $row['intake_year'],
            'created_by' => $userId,
        ];
    }

    private function logValidationErrors(int $importLogId, array $invalidRows): void
    {
        $errorRecords = $this->buildErrorRecords($importLogId, $invalidRows);

        if (!empty($errorRecords)) {
            ImportError::insert($errorRecords);
        }
    }

    private function buildErrorRecords(int $importLogId, array $invalidRows): array
    {
        $timestamp = now();

        return array_map(fn($row) => [
            'import_log_id' => $importLogId,
            'row_number' => $row['row'],
            'error_message' => $this->formatErrorMessage($row['errors']),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $invalidRows);
    }

    private function formatErrorMessage(array $errors): string
    {
        $messages = [];

        foreach ($errors as $field => $fieldErrors) {
            $messages[] = $field . ': ' . implode(', ', $fieldErrors);
        }

        return implode('; ', $messages);
    }
}
