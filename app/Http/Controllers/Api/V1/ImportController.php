<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Imports\StudentsPreviewImport;
use App\Services\StudentImportService;
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
