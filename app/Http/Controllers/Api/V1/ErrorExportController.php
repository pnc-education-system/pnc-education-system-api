<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\ImportError;
use App\Models\ImportLog;
use Illuminate\Http\Request;

class ErrorExportController extends Controller
{
    use ApiResponse;

    public function exportErrors(Request $request, $importLogId)
    {
        $importLog = ImportLog::find($importLogId);

        if (!$importLog) {
            return $this->error('Import log not found', 404);
        }

        $errors = ImportError::where('import_log_id', $importLogId)
            ->orderBy('row_number')
            ->get();

        if ($errors->isEmpty()) {
            return $this->error('No errors found for this import', 404);
        }

        $fileName = "import_errors_{$importLogId}_" . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        $callback = function () use ($errors) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Row Number', 'Field', 'Error Message']);
            foreach ($errors as $error) {
                fputcsv($file, [$error->row_number, $error->field, $error->error_message]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getErrors(Request $request, $importLogId)
    {
        $importLog = ImportLog::find($importLogId);

        if (!$importLog) {
            return $this->error('Import log not found', 404);
        }

        $errors = ImportError::where('import_log_id', $importLogId)
            ->orderBy('row_number')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Errors retrieved successfully',
            'data' => $errors,
            'total' => $errors->count()
        ], 200);
    }
}
