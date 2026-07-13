<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Jobs\ProcessStudentImport;
use App\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentImportController extends Controller
{
    use ApiResponse;

    public function commit(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:20480',
        ]);

        $file = $request->file('file');

        $filePath = $file->store('imports');

        $importLog = ImportLog::create([
            'file_name'     => $file->getClientOriginalName(),
            'file_path'     => $filePath,
            'imported_by'   => auth()->id(),
            'total_rows'    => 0,
            'success_count' => 0,
            'error_count'   => 0,
            'status'        => 'Pending',
        ]);

        ProcessStudentImport::dispatch($importLog);

        return response()->json([
            'status'  => 'success',
            'message' => 'Import file uploaded successfully. Processing has been queued.',
            'data'    => [
                'import_log_id' => $importLog->id,
                'file_name'     => $importLog->file_name,
                'status'        => $importLog->status,
            ],
        ], 202);
    }

    public function status(int $importLogId)
    {
        $importLog = ImportLog::withCount('errors')->find($importLogId);

        if (!$importLog) {
            return $this->error('Import log not found', 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Import status retrieved successfully.',
            'data'    => [
                'id'            => $importLog->id,
                'file_name'     => $importLog->file_name,
                'status'        => $importLog->status,
                'total_rows'    => $importLog->total_rows,
                'success_count' => $importLog->success_count,
                'error_count'   => $importLog->error_count,
                'created_at'    => $importLog->created_at,
                'updated_at'    => $importLog->updated_at,
            ],
        ]);
    }

    public function index(Request $request)
    {
        $importLogs = ImportLog::where('imported_by', auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => 'success',
            'message' => 'Import logs retrieved successfully.',
            'data'    => $importLogs->items(),
            'meta'    => [
                'current_page' => $importLogs->currentPage(),
                'last_page'    => $importLogs->lastPage(),
                'per_page'     => $importLogs->perPage(),
                'total'        => $importLogs->total(),
            ],
        ]);
    }
}
