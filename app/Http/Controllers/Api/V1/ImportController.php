<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\ImportLog;
use App\Models\ImportError;
use App\Models\Student;
use App\Models\SelectionBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class ImportController extends Controller
{
    use ApiResponse;

    // ─────────────────────────────────────────────────────────────────────────
    // GET /v1/imports  — list history
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $imports = ImportLog::with('importer:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $data = $imports->getCollection()->map(fn ($log) => $this->formatLog($log));

        return response()->json([
            'status'  => 'success',
            'message' => 'Import history retrieved successfully',
            'data'    => $data,
            'meta'    => [
                'current_page' => $imports->currentPage(),
                'last_page'    => $imports->lastPage(),
                'per_page'     => $imports->perPage(),
                'total'        => $imports->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /v1/imports/{id}  — single import with errors
    // ─────────────────────────────────────────────────────────────────────────
    public function show(int $id)
    {
        $log = ImportLog::with(['importer:id,name', 'errors'])->find($id);
        if (!$log) return $this->error('Import record not found', 404);

        return response()->json([
            'status'  => 'success',
            'message' => 'Import record retrieved successfully',
            'data'    => $this->formatLog($log, true),
        ]);
    }

    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:25600',
        ]);

        if ($validator->fails()) {
            return $this->error('Only CSV files are supported. Please convert your XLSX file to CSV first.', 422, $validator->errors());
        }

        $user = JWTAuth::user();
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        $rows = $this->parseCsv($file->getRealPath());

        if (empty($rows)) {
            return $this->error('The file is empty or could not be parsed', 422);
        }

        $existingIds = Student::pluck('student_id_no')->flip()->toArray();

        $validRows   = [];
        $errorRows   = [];
        $seenIds     = [];

        foreach ($rows as $index => $row) {
            $rowNum  = $index + 2; 
            $errors  = [];

            if (empty($row['student_id_no'])) {
                $errors[] = ['field' => 'student_id_no', 'message' => 'student_id_no required'];
            }
            if (empty($row['full_name'])) {
                $errors[] = ['field' => 'full_name', 'message' => 'full_name required'];
            }
            if (empty($row['gender']) || !in_array($row['gender'], ['Male', 'Female'])) {
                $errors[] = ['field' => 'gender', 'message' => 'gender must be Male or Female'];
            }

            if (!empty($row['dob'])) {
                $parsed = \DateTime::createFromFormat('m/d/Y', $row['dob'])
                    ?: \DateTime::createFromFormat('Y-m-d', $row['dob']);
                if (!$parsed) {
                    $errors[] = ['field' => 'dob', 'message' => 'invalid dob format (use MM/DD/YYYY)'];
                }
            }

            $sid = $row['student_id_no'] ?? null;
            if ($sid && isset($existingIds[$sid])) {
                $errors[] = ['field' => 'student_id_no', 'message' => 'duplicate student_id_no'];
            }

            if ($sid && isset($seenIds[$sid])) {
                $errors[] = ['field' => 'student_id_no', 'message' => 'duplicate student_id_no'];
            }
            if ($sid) $seenIds[$sid] = true;

            $rowData = [
                'row'            => $rowNum,
                'student_id_no'  => $row['student_id_no'] ?? null,
                'full_name'      => $row['full_name'] ?? null,
                'province'       => $row['province'] ?? null,
                'gender'         => $row['gender'] ?? null,
                'dob'            => $row['dob'] ?? null,
                'phone'          => $row['phone'] ?? null,
                'email'          => $row['email'] ?? null,
                'high_school'    => $row['high_school'] ?? null,
            ];

            if (empty($errors)) {
                $validRows[] = array_merge($rowData, ['validation' => 'ok', 'status' => 'Pending']);
            } else {
                $errorRows[] = array_merge($rowData, [
                    'validation' => implode('; ', array_column($errors, 'message')),
                    'status'     => null,
                    'errors'     => $errors,
                ]);
            }
        }

        $log = ImportLog::create([
            'file_name'     => $fileName,
            'status'        => 'Pending',
            'total_rows'    => count($rows),
            'success_count' => 0,
            'error_count'   => count($errorRows),
            'imported_by'   => $user->id,
        ]);

        foreach ($errorRows as $errRow) {
            foreach ($errRow['errors'] as $err) {
                ImportError::create([
                    'import_log_id' => $log->id,
                    'row_number'    => $errRow['row'],
                    'field'         => $err['field'],
                    'error_message' => $err['message'],
                ]);
            }
        }

        $allRows = array_merge($validRows, $errorRows);
        usort($allRows, fn ($a, $b) => $a['row'] <=> $b['row']);

        return response()->json([
            'status'  => 'success',
            'message' => 'File parsed successfully',
            'data'    => [
                'import_id'   => $log->id,
                'file_name'   => $fileName,
                'total_rows'  => count($rows),
                'valid_count' => count($validRows),
                'error_count' => count($errorRows),
                'rows'        => $allRows,
            ],
        ]);
    }

    public function commit(int $id)
    {
        $log = ImportLog::with('errors')->find($id);

        if (!$log) return $this->error('Import record not found', 404);
        if ($log->status !== 'Pending') {
            return $this->error('This import has already been committed or failed', 422);
        }

        $user = JWTAuth::user();

        $batch = SelectionBatch::first();
        if (!$batch) {
            $batch = SelectionBatch::create([
                'name'        => 'Default Batch',
                'description' => 'Auto-created batch',
            ]);
        }

        $log->update(['status' => 'Processing']);

        $log->update([
            'status'        => 'Completed',
            'success_count' => $log->total_rows - $log->error_count,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Import committed. {$log->success_count} students imported successfully.",
            'data'    => $this->formatLog($log->fresh()),
        ]);
    }

    public function downloadErrors(int $id)
    {
        $log = ImportLog::with('errors')->find($id);
        if (!$log) return $this->error('Import record not found', 404);
        if ($log->errors->isEmpty()) return $this->error('No errors found for this import', 404);

        $filename = 'error_report_' . $id . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ];

        $callback = function () use ($log) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Row Number', 'Field', 'Error Message']);
            foreach ($log->errors as $error) {
                fputcsv($handle, [$error->row_number, $error->field, $error->error_message]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function parseCsv(string $path): array
    {
        $rows    = [];
        $headers = null;

        if (($handle = fopen($path, 'r')) === false) return [];

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            
            if (count($line) === 1 && $line[0] === null) continue;

            if ($headers === null) {

                $headers = array_map(fn ($h) => strtolower(trim(str_replace(' ', '_', $h))), $line);
                continue;
            }

            while (count($line) < count($headers)) $line[] = '';

            $row = array_combine($headers, array_map('trim', $line));
            if ($row !== false) $rows[] = $row;
        }

        fclose($handle);
        return $rows;
    }

    private function formatLog(ImportLog $log, bool $withErrors = false): array
    {
        $result = [
            'id'            => $log->id,
            'file_name'     => $log->file_name,
            'status'        => $log->status,
            'total_rows'    => $log->total_rows,
            'success_count' => $log->success_count,
            'error_count'   => $log->error_count,
            'imported_by'   => $log->importer ? [
                'id'   => $log->importer->id,
                'name' => $log->importer->name,
            ] : null,
            'created_at'    => $log->created_at?->toISOString(),
            'updated_at'    => $log->updated_at?->toISOString(),
        ];

        if ($withErrors) {
            $result['errors'] = $log->errors->map(fn ($e) => [
                'row_number'    => $e->row_number,
                'field'         => $e->field,
                'error_message' => $e->error_message,
            ])->values();
        }

        return $result;
    }
}
