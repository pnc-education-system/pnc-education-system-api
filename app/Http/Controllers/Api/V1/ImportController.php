<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Imports\StudentsPreviewImport;
use App\Models\ImportLog;
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

    private array $requiredColumns = [
        'student_id_no',
        'full_name',
        'gender',
        'dob',
        'selection_batch_id',
        'intake_year',
    ];

    public function __construct(StudentImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * GET /api/v1/imports
     * Returns paginated import history with counts, author, timestamp, and status.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $status  = $request->query('status');

        $query = ImportLog::with(['importer:id,name,email'])
            ->orderByDesc('created_at');

        // Optional filter by status
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

    /**
     * GET /api/v1/imports/{id}
     * Returns a single import log with its errors.
     */
    public function show(int $id): JsonResponse
    {
        $log = ImportLog::with(['importer:id,name,email', 'errors'])->find($id);

        if (!$log) {
            return $this->error('Import log not found', 404);
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

    public function commitById($importLog, Request $request): JsonResponse
    {
        $log = \App\Models\ImportLog::find($importLog);

        if (!$log) {
            return $this->error('Import log not found', 404);
        }

        $validator = Validator::make($request->all(), [
            'rows'   => 'required|array',
            'rows.*' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $result = $this->importService->commitById(
                $log,
                $request->input('rows'),
                auth()->id()
            );

            return response()->json([
                'status' => 'success',
                'data'   => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Import commit by ID failed', [
                'import_log_id' => $importLog,
                'error'         => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), 400);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx|max:' . config('import.max_file_size_kb', 10240),
        ], [
            'file.required' => 'No file was uploaded. Please attach a .xlsx file to proceed.',
            'file.mimes'    => 'Invalid file format. Only .xlsx (Excel) files are accepted.',
            'file.max'      => 'File size exceeds the maximum allowed size of ' . (config('import.max_file_size_kb', 10240) / 1024) . ' MB.',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first('file'), 422);
        }

        $file = $request->file('file');

        if ($file->getSize() === 0) {
            return $this->error('The uploaded file is empty. Please upload a file containing student data.', 422);
        }

        try {
            $rows = Excel::toArray(new StudentsPreviewImport, $file);
            $data = $rows[0] ?? [];

            if (empty($data)) {
                return $this->error('The uploaded file contains no data rows. Please ensure your spreadsheet has at least one row of student data.', 422);
            }

            $missingColumns = $this->validateHeaders($data[0]);
            if (!empty($missingColumns)) {
                return $this->error(
                    'Missing required columns: ' . implode(', ', $missingColumns) . '. The file must contain: student_id_no, full_name, gender, dob, selection_batch_id, intake_year.',
                    422
                );
            }

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'total_rows' => count($data),
                    'columns'    => array_keys($data[0]),
                    'rows'       => $data,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('File preview failed', [
                'error' => $e->getMessage(),
                'file'  => $request->file('file')?->getClientOriginalName(),
            ]);

            return $this->error('The uploaded file could not be read. Please verify the file is a valid .xlsx format and try again.', 422);
        }
    }

    public function commit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rows'      => 'required|array',
            'rows.*'    => 'array',
            'file_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422);
        }

        try {
            $result = $this->importService->commit(
                $request->input('rows'),
                $request->input('file_name'),
                auth()->id()
            );

            return response()->json([
                'status' => 'success',
                'data'   => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Import commit failed', [
                'file_name' => $request->input('file_name'),
                'error'     => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), 400);
        }
    }

    private function validateHeaders(array $firstRow): array
    {
        $fileColumns = array_keys($firstRow);

        return array_diff($this->requiredColumns, $fileColumns);
    }
}
