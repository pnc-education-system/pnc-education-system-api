<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Imports\StudentsPreviewImport;
use App\Models\ImportError;
use App\Models\ImportLog;
use App\Models\SelectionBatch;
use App\Services\Student\ImportValidationService;
use App\Services\Student\StudentIdGenerator;
use App\Services\Student\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    use ApiResponse;

    private StudentImportService $importService;

    private ImportValidationService $validationService;

    private array $systemColumns = [
        'student_id_no',
        'full_name',
        'gender',
        'dob',
        'phone',
        'email',
        'province',
        'high_school',
        'selection_batch_id',
        'enrollment_status',
        'intake_year',
    ];

    private StudentIdGenerator $idGenerator;

    public function __construct(
        StudentImportService $importService,
        ImportValidationService $validationService,
        StudentIdGenerator $idGenerator
    ) {
        $this->importService = $importService;
        $this->validationService = $validationService;
        $this->idGenerator = $idGenerator;
    }

    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx|max:' . config('import.max_file_size_kb', 10240),
            'selection_batch_id' => 'required|integer|exists:selection_batches,id',
        ], [
            'file.required' => 'No file was uploaded. Please attach a .xlsx file to proceed.',
            'file.mimes'    => 'Invalid file format. Only .xlsx (Excel) files are accepted.',
            'file.max'      => 'File size exceeds the maximum allowed size of ' . (config('import.max_file_size_kb', 10240) / 1024) . ' MB.',
            'selection_batch_id.required' => 'Selection batch is required.',
            'selection_batch_id.exists'   => 'Selected batch does not exist.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        $file = $request->file('file');

        if ($file->getSize() === 0) {
            return $this->error('The uploaded file is empty. Please upload a file containing student data.', 422);
        }

        if (!class_exists(\ZipArchive::class)) {
            return $this->error('Excel uploads require the PHP zip extension. Enable extension=zip in php.ini and restart PHP/Laragon.', 500);
        }

        $selectionBatch = $this->resolveSelectionBatch($request);

        try {
            $rows = Excel::toArray(new StudentsPreviewImport, $file);
            $data = $this->normalizeRows($rows[0] ?? [], $selectionBatch);

            if (empty($data)) {
                return $this->error('The uploaded file contains no data rows. Please ensure your spreadsheet has at least one row of student data.', 422);
            }

            // Validate first (IDs are empty since CSV doesn't include student_id_no column)
            $validationResult = $this->validationService->validate($data);

            // Then generate IDs ONLY for valid rows — no gaps in the sequence
            $validCount = $validationResult['summary']['valid'];
            if ($validCount > 0) {
                $batchYear = $selectionBatch?->year ?? (int)($data[0]['intake_year'] ?? date('Y'));
                $newIds = $this->idGenerator->generateMultipleIds($validCount, $batchYear);

                // Build a set of invalid row numbers for quick lookup
                $invalidRows = [];
                foreach ($validationResult['invalidRows'] as $invalidRow) {
                    $invalidRows[] = $invalidRow['row'];
                }

                // Assign IDs to valid rows only
                $idIndex = 0;
                foreach ($data as $index => &$row) {
                    if (!in_array($index + 1, $invalidRows)) {
                        $row['student_id_no'] = $newIds[$idIndex];
                        $validationResult['validRows'][$idIndex]['student_id_no'] = $newIds[$idIndex];
                        $idIndex++;
                    }
                }
                unset($row);
            }

            $importLog = ImportLog::create([
                'file_name'          => $file->getClientOriginalName(),
                'file_path'          => $file->getRealPath(),
                'selection_batch_id' => $request->input('selection_batch_id'),
                'imported_by'        => auth()->id(),
                'total_rows'         => $validationResult['summary']['total'],
                'success_count'      => 0,
                'error_count'        => $validationResult['summary']['invalid'],
                'status'             => 'Pending',
            ]);
            if (!empty($validationResult['invalidRows'])) {
                $errorRecords = [];
                foreach ($validationResult['invalidRows'] as $invalidRow) {
                    foreach ($invalidRow['errors'] as $field => $fieldErrors) {
                        $errorRecords[] = [
                            'import_log_id' => $importLog->id,
                            'row_number'    => $invalidRow['row'],
                            'field'         => $field,
                            'error_message' => implode(', ', $fieldErrors),
                        ];
                    }
                }

                try {
                    ImportError::insert($errorRecords);
                    Log::info('Import errors stored successfully', [
                        'import_log_id' => $importLog->id,
                        'error_count' => count($errorRecords),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to store import errors', [
                        'import_log_id' => $importLog->id,
                        'error' => $e->getMessage(),
                        'error_records' => $errorRecords,
                    ]);
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'File uploaded and validated successfully',
                'data'    => [
                    'import_log_id'  => $importLog->id,
                    'file_name'      => $file->getClientOriginalName(),
                    'total_rows'     => $validationResult['summary']['total'],
                    'valid_rows'     => $validationResult['summary']['valid'],
                    'invalid_rows'   => $validationResult['summary']['invalid'],
                    'validation'     => $validationResult,
                    'rows'           => $data,
                    'selection_batch' => $selectionBatch ? [
                        'id'   => $selectionBatch->id,
                        'name' => $selectionBatch->name,
                        'year' => $selectionBatch->year,
                    ] : null,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('File upload and validation failed', [
                'error' => $e->getMessage(),
                'file'  => $request->file('file')?->getClientOriginalName(),
            ]);

            return $this->error('The uploaded file could not be processed. Please verify the file is a valid .xlsx format and try again.', 422);
        }
    }
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $status  = $request->query('status');

        $query = ImportLog::with(['importer:id,name,email', 'batch:id,name,year'])
            ->orderByDesc('created_at');

        if ($status && in_array($status, ['Pending', 'Processing', 'Completed', 'Failed'])) {
            $query->where('status', $status);
        }

        $imports = $query->paginate($perPage);

        $data = $imports->map(function (ImportLog $log) {
            return [
                'id'            => $log->id,
                'file_name'     => $log->file_name,
                'status'        => $log->status,
                'total_rows'    => $log->total_rows,
                'success_count' => $log->success_count,
                'error_count'   => $log->error_count,
                'selection_batch' => $log->batch ? [
                    'id'   => $log->batch->id,
                    'name' => $log->batch->name,
                    'year' => $log->batch->year,
                ] : null,
                'imported_by'   => $log->importer ? [
                    'id'    => $log->importer->id,
                    'name'  => $log->importer->name,
                    'email' => $log->importer->email,
                ] : null,
                'created_at'    => $log->created_at?->toIso8601String(),
                'updated_at'    => $log->updated_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Import history retrieved successfully',
            'data'    => $data,
            'meta'    => [
                'current_page' => $imports->currentPage(),
                'per_page'     => $imports->perPage(),
                'total'        => $imports->total(),
                'last_page'    => $imports->lastPage(),
            ],
        ], 200);
    }
    public function show(int $import): JsonResponse
    {
        $log = ImportLog::with(['importer:id,name,email', 'errors'])->find($import);

        if (!$log) {
            return $this->error('The import record you are looking for could not be found. It may have been deleted or the ID is invalid.', 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Import log retrieved successfully',
            'data'    => [
                'id'            => $log->id,
                'file_name'     => $log->file_name,
                'status'        => $log->status,
                'total_rows'    => $log->total_rows,
                'success_count' => $log->success_count,
                'error_count'   => $log->error_count,
                'imported_by'   => $log->importer ? [
                    'id'    => $log->importer->id,
                    'name'  => $log->importer->name,
                    'email' => $log->importer->email,
                ] : null,
                'errors'        => $log->errors->map(fn ($e) => [
                    'row_number'    => $e->row_number,
                    'field'         => $e->field,
                    'error_message' => $e->error_message,
                ]),
                'created_at'    => $log->created_at?->toIso8601String(),
                'updated_at'    => $log->updated_at?->toIso8601String(),
            ],
        ], 200);
    }
    public function commit(int $import, Request $request): JsonResponse
    {
        $log = ImportLog::find($import);

        if (!$log) {
            return $this->error('The import record you are looking for could not be found. It may have been deleted or the ID is invalid.', 404);
        }

        if ($log->status !== 'Pending') {
            $statusLabel = $log->status;
            return $this->error('This file has already been imported (Status: ' . $statusLabel . '). Each file can only be processed once. Please upload a new file to import additional students.', 422);
        }

        $validator = Validator::make($request->all(), [
            'rows'   => 'required|array',
            'rows.*' => 'array',
            'selection_batch_id' => 'sometimes|integer|exists:selection_batches,id',
        ], [
            'rows.required' => 'No student data was provided to import. Please try uploading the file again.',
            'rows.array'    => 'Invalid student data format. Please try uploading the file again.',
            'selection_batch_id.exists' => 'The selected batch does not exist. Please choose a valid batch.',
            'selection_batch_id.integer' => 'The batch ID must be a valid number.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $selectionBatch = $this->resolveSelectionBatch($request);
            $rows = $this->normalizeRows($request->input('rows'), $selectionBatch);

            $result = $this->importService->commitById(
                $log,
                $request->input('rows'),
                auth()->id(),
                $request->input('selection_batch_id')
            );

            return response()->json([
                'status' => 'success',
                'data'   => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Import commit failed', [
                'import_log_id' => $import,
                'error'         => $e->getMessage(),
            ]);

            $friendlyMessage = 'An error occurred while saving the imported students. ';
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                $friendlyMessage .= 'Some student IDs already exist in the database. Please remove duplicates and try again.';
            } elseif (str_contains($e->getMessage(), 'column')) {
                $friendlyMessage .= 'The data format is invalid. Please check your file and try again.';
            } else {
                $friendlyMessage .= 'Please try again or contact support if the issue persists.';
            }

            return $this->error($friendlyMessage, 400);
        }
    }
    public function errors(int $import): JsonResponse
    {
        $log = ImportLog::with('errors')->find($import);

        if (!$log) {
            return $this->error('The import record you are looking for could not be found. It may have been deleted or the ID is invalid.', 404);
        }

        $errors = $log->errors->map(fn (ImportError $e) => [
            'id'            => $e->id,
            'row_number'    => $e->row_number,
            'field'         => $e->field,
            'error_message' => $e->error_message,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Import errors retrieved successfully',
            'data'    => [
                'import_log_id' => $log->id,
                'file_name'     => $log->file_name,
                'total_errors'  => $errors->count(),
                'errors'        => $errors,
            ],
        ], 200);
    }

    private function resolveSelectionBatch(Request $request): ?SelectionBatch
    {
        $batchId = $request->input('selection_batch_id');
        if (!$batchId) {
            return null;
        }
        return SelectionBatch::find($batchId);
    }

    private function normalizeRows(array $rows, ?SelectionBatch $selectionBatch): array
    {
        return array_map(function ($row) use ($selectionBatch) {
            if (is_array($row)) {
                // Ensure selection_batch_id is included in each row
                if ($selectionBatch && !isset($row['selection_batch_id'])) {
                    $row['selection_batch_id'] = $selectionBatch->id;
                }
                return $row;
            }
            return $row;
        }, $rows);
    }



}
