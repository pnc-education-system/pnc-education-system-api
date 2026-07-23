<?php

namespace App\Services\Student;

use App\Models\ImportError;
use App\Models\ImportLog;
use App\Models\Student;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentImportService
{
    private ImportValidationService $validationService;
    private StudentIdGenerator $idGenerator;

    public function __construct(ImportValidationService $validationService, StudentIdGenerator $idGenerator)
    {
        $this->validationService = $validationService;
        $this->idGenerator = $idGenerator;
    }

    public function import(array $rows, string $fileName, int $userId): array
    {
        $validationResult = $this->validationService->validate($rows);
        $importLog = $this->createImportLog($fileName, $userId, $validationResult['summary']);

        $this->logValidationErrors($importLog->id, $validationResult['invalidRows']);
        $importedCount = $this->importValidRowsIfAny($validationResult['validRows'], $userId);

        $importLog->update([
            'success_count' => $importedCount,
            'error_count' => $validationResult['summary']['invalid'],
            'status' => 'Completed',
        ]);

        return $this->buildImportResult($validationResult, $importedCount, $importLog->id);
    }

    private function createImportLog(string $fileName, int $userId, array $summary): ImportLog
    {
        return ImportLog::create([
            'file_name' => $fileName,
            'imported_by' => $userId,
            'total_rows' => $summary['total'],
            'success_count' => 0,
            'error_count' => $summary['invalid'],
            'status' => 'Processing',
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
            'student_id_no' => $this->getOrGenerateStudentId($row),
            'full_name' => $row['full_name'],
            'gender' => $row['gender'],
            'dob' => $row['dob'],
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'province' => $row['province'] ?? null,
            'high_school' => $row['high_school'] ?? null,
            'selection_batch_id' => $row['selection_batch_id'],
            'enrollment_status' => $row['enrollment_status'] ?? 'Pending',
            'intake_year' => $row['intake_year'],
            'created_by' => $userId,
            'photo_path' => null,
        ];
    }

    private function getOrGenerateStudentId(array $row): string
    {
        if (!empty($row['student_id_no'])) {
            return $row['student_id_no'];
        }
        
        $intakeYear = $row['intake_year'] ?? date('Y');
        return $this->idGenerator->generateNextId($intakeYear);
    }

    private function generateMissingIds(array $rows, ?int $batchYear = null): array
    {
        $year = $batchYear ?? $rows[0]['intake_year'] ?? date('Y');
        
        $ids = $this->idGenerator->generateMultipleIds(count($rows), $year);
        $idIndex = 0;

        return array_map(function ($row) use (&$idIndex, $ids) {
            if (empty($row['student_id_no'])) {
                $row['student_id_no'] = $ids[$idIndex++];
            }
            return $row;
        }, $rows);
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
        $records = [];

        foreach ($invalidRows as $row) {
            $errorMessage = $this->formatErrorMessage($row['errors']);
            $fields = array_keys($row['errors']);

            foreach ($fields as $field) {
                $records[] = [
                    'import_log_id' => $importLogId,
                    'row_number' => $row['row'],
                    'field' => $field,
                    'error_message' => $errorMessage,
                ];
            }
        }

        return $records;
    }

    private function formatErrorMessage(array $errors): string
    {
        $messages = [];

        foreach ($errors as $field => $fieldErrors) {
            $messages[] = $field . ': ' . implode(', ', $fieldErrors);
        }

        return implode('; ', $messages);
    }

    public function commitById(ImportLog $importLog, array $rows, int $userId, ?int $selectionBatchId = null): array
    {
        $validationErrorCount = $importLog->error_count;

        $importLog->update([
            'status' => 'Processing',
        ]);

        $batchYear = null;
        if ($selectionBatchId) {
            $batch = \App\Models\SelectionBatch::find($selectionBatchId);
            $batchYear = $batch?->year;
        }
        $rows = $this->generateMissingIds($rows, $batchYear);

        $chunkSize  = 100;
        $chunks     = array_chunk($rows, $chunkSize);
        $totalSuccess = 0;
        $totalFailed  = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                DB::transaction(function () use ($chunk, &$totalSuccess, $userId, $selectionBatchId) {
                    $insertData = [];
                    foreach ($chunk as $row) {
                        $insertData[] = [
                            'student_id_no'      => $row['student_id_no'],
                            'full_name'          => $row['full_name'],
                            'gender'             => $row['gender'],
                            'dob'                => $row['dob'],
                            'phone'              => $row['phone'] ?? null,
                            'email'              => $row['email'] ?? null,
                            'province'           => $row['province'] ?? null,
                            'high_school'        => $row['high_school'] ?? null,
                            'selection_batch_id' => $selectionBatchId ?? $row['selection_batch_id'] ?? null,
                            'enrollment_status'  => $row['enrollment_status'] ?? 'Pending',
                            'intake_year'        => $row['intake_year'],
                            'created_by'         => $userId,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ];
                    }
                    Student::insert($insertData);
                    $totalSuccess += count($insertData);
                });
            } catch (\Exception $e) {
                $totalFailed += count($chunk);

                $errorRecords = [];
                $startRow = ($chunkIndex * $chunkSize) + 1;
                foreach ($chunk as $offset => $row) {
                    $studentId = $row['student_id_no'] ?? 'N/A';
                    $errorRecords[] = [
                        'import_log_id' => $importLog->id,
                        'row_number'    => $startRow + $offset,
                        'field'         => 'system',
                        'error_message' => 'Failed to import student "' . $studentId . '": ' . $e->getMessage(),
                    ];
                }
                ImportError::insert($errorRecords);

                Log::error('Import chunk failed', [
                    'import_log_id' => $importLog->id,
                    'chunk'         => $chunkIndex + 1,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $importLog->update([
            'success_count' => $totalSuccess,
            'error_count'   => $validationErrorCount + $totalFailed,
            'status'        => 'Completed',
        ]);

        return [
            'import_log_id' => $importLog->id,
            'total_rows'    => $importLog->fresh()->total_rows,
            'imported'      => $totalSuccess,
            'failed'        => $validationErrorCount + $totalFailed,
            'chunks'        => count($chunks),
        ];
    }

    public function commit(array $rows, string $fileName, int $userId): array
    {
        $importLog = ImportLog::create([
            'file_name'     => $fileName,
            'imported_by'   => $userId,
            'total_rows'    => count($rows),
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Processing',
        ]);

        $rows = $this->generateMissingIds($rows);

        $chunkSize = 100;
        $chunks = array_chunk($rows, $chunkSize);

        $totalSuccess = 0;
        $totalFailed = 0;

        foreach($chunks as $chunkIndex => $chunk){
            try{
                DB::transaction(function () use ($chunk, &$totalSuccess, $userId) {
                    $insertData = [];
                    foreach ($chunk as $row) {
                        $insertData[] = [
                            'student_id_no'      => $row['student_id_no'],
                            'full_name'          => $row['full_name'],
                            'gender'             => $row['gender'],
                            'dob'                => $row['dob'],
                            'phone'              => $row['phone'] ?? null,
                            'email'              => $row['email'] ?? null,
                            'province'           => $row['province'] ?? null,
                            'high_school'        => $row['high_school'] ?? null,
                            'selection_batch_id' => $row['selection_batch_id'] ?? null,
                            'enrollment_status'  => $row['enrollment_status'] ?? 'Pending',
                            'intake_year'        => $row['intake_year'],
                            'created_by'         => $userId,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ];
                    }
                    Student::insert($insertData);
                    $totalSuccess += count($insertData);
                });
            }catch(\Exception $e){
                $totalFailed += count($chunk);
                $errorRecords = [];
                $startRow = ($chunkIndex * $chunkSize) + 1;
                foreach ($chunk as $offset => $row) {
                    $studentId = $row['student_id_no'] ?? 'N/A';
                    $errorRecords[] = [
                        'import_log_id' => $importLog->id,
                        'row_number'    => $startRow + $offset,
                        'field'         => 'system',
                        'error_message' => 'Failed to import student "' . $studentId . '": ' . $e->getMessage(),
                    ];
                }
                ImportError::insert($errorRecords);

                Log::error('Import chunk failed', [
                    'import_log_id' => $importLog->id,
                    'chunk'         => $chunkIndex + 1,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
        $importLog->update([
            'success_count' =>$totalSuccess,
            'error_count'=>$totalFailed,
            'status' => 'Completed',
        ]);
        return[
            'import_log_id'=>$importLog->id,
            'total_rows'=>count($rows),
            'imported'=>$totalSuccess,
            'failed'=>$totalFailed,
            'chunks'=>count($chunks)
        ];
    }
}
