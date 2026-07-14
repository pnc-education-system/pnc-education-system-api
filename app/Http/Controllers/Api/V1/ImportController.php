<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\ImportLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    use ApiResponse;

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
}
