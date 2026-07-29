<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\StudentsExport;
use App\Exports\StudentsPdfExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Jobs\ExportStudentsJob;
use App\Jobs\ExportStudentsPdfJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ExportController — handles synchronous and asynchronous exports
 * of student data in Excel (.xlsx) and PDF formats.
 *
 * Performance (R5):
 *   - Indexed queries + chunked reads (FromQuery + WithChunkReading)
 *   - Synchronous for ≤ sync_threshold (5,000 records)
 *   - Queued job fallback for larger datasets
 */
class ExportController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        // Ensure the export storage directory exists on first request
        $disk = config('export.disk', 'local');
        $path = config('export.path', 'exports');

        if (!Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->makeDirectory($path);
        }
    }

    /**
     * Export students as Excel (.xlsx).
     *
     * GET /v1/exports/students?format=xlsx
     *
     * For sets ≤ sync_threshold: streams the file directly (≤10s R5).
     * For larger sets: queues a job and returns a tracking ID.
     */
    public function studentsExcel(Request $request): JsonResponse|BinaryFileResponse
    {
        $filters = $this->extractFilters($request);
        $export  = new StudentsExport($filters);

        // Estimate count using the indexed query (fast, uses COUNT + indices)
        $estimatedCount = $export->query()->count();
        $syncThreshold  = config('export.sync_threshold', 5000);

        if ($estimatedCount > $syncThreshold) {
            // Queue for async processing
            return $this->queueAsyncExport($filters, 'xlsx', $estimatedCount);
        }

        // Synchronous export — streams directly (≤10s for ≤5K records with indices)
        try {
            $filename = 'students_export_' . now()->format('Y-m-d_His') . '.xlsx';

            return Excel::download($export, $filename);
        } catch (\Throwable $e) {
            Log::error('Excel export failed', [
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to generate Excel export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export students as PDF report.
     *
     * GET /v1/exports/students?format=pdf
     *
     * For sets ≤ sync_threshold: renders and streams the PDF directly.
     * For larger sets: queues a job and returns a tracking ID.
     */
    public function studentsPdf(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $filters = $this->extractFilters($request);
        $export  = new StudentsPdfExport($filters);

        $estimatedCount = $export->estimateCount();
        $syncThreshold  = config('export.sync_threshold', 5000);

        if ($estimatedCount > $syncThreshold) {
            return $this->queueAsyncExport($filters, 'pdf', $estimatedCount);
        }

        // Synchronous PDF generation
        try {
            $pdfBytes = $export->generate();

            $filename = 'students_report_' . now()->format('Y-m-d_His') . '.pdf';

            return response($pdfBytes)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            Log::error('PDF export failed', [
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to generate PDF export: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Download an async-generated export file.
     *
     * GET /v1/exports/download/{exportId}
     */
    public function download(string $exportId): JsonResponse|BinaryFileResponse
    {
        $disk = config('export.disk', 'local');
        $path = config('export.path', 'exports');

        $xlsxPath = $path . '/' . $exportId . '.xlsx';
        $pdfPath  = $path . '/' . $exportId . '.pdf';

        if (Storage::disk($disk)->exists($xlsxPath)) {
            return Storage::disk($disk)->download($xlsxPath);
        }

        if (Storage::disk($disk)->exists($pdfPath)) {
            return Storage::disk($disk)->download($pdfPath);
        }

        return $this->error('Export file not found or not yet generated. Please check the export ID.', 404);
    }

    /**
     * Unified export endpoint that detects format from query param.
     *
     * GET /v1/exports/students?format=xlsx|pdf
     */
    public function students(Request $request): JsonResponse|BinaryFileResponse|\Illuminate\Http\Response
    {
        $format = $request->query('format', 'xlsx');

        return match ($format) {
            'pdf'  => $this->studentsPdf($request),
            default => $this->studentsExcel($request),
        };
    }

    /**
     * Queue an async export job and return a tracking response.
     */
    private function queueAsyncExport(array $filters, string $format, int $estimatedCount): JsonResponse
    {
        $exportId = (string) Str::uuid();

        try {
            if ($format === 'pdf') {
                ExportStudentsPdfJob::dispatch($filters, $exportId);
            } else {
                ExportStudentsJob::dispatch($filters, $exportId);
            }

            return response()->json([
                'status'  => 'accepted',
                'message' => "Export queued for {$estimatedCount} records. " .
                             "Use the download endpoint once processing completes.",
                'data'    => [
                    'export_id'       => $exportId,
                    'format'          => $format,
                    'estimated_count' => $estimatedCount,
                    'download_url'    => url('/api/v1/exports/download/' . $exportId),
                    'status_check_url' => url('/api/v1/exports/status/' . $exportId),
                ],
            ], 202);
        } catch (\Throwable $e) {
            Log::error('Failed to queue export job', [
                'filters' => $filters,
                'format'  => $format,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to queue export. Please try again.', 500);
        }
    }

    /**
     * Check the status of an async export.
     *
     * GET /v1/exports/status/{exportId}
     */
    public function status(string $exportId): JsonResponse
    {
        $disk = config('export.disk', 'local');
        $path = config('export.path', 'exports');

        $xlsxExists = Storage::disk($disk)->exists($path . '/' . $exportId . '.xlsx');
        $pdfExists  = Storage::disk($disk)->exists($path . '/' . $exportId . '.pdf');

        if ($xlsxExists || $pdfExists) {
            $format = $xlsxExists ? 'xlsx' : 'pdf';

            return response()->json([
                'status'  => 'completed',
                'message' => 'Export file is ready for download.',
                'data'    => [
                    'export_id'    => $exportId,
                    'format'       => $format,
                    'download_url' => url('/api/v1/exports/download/' . $exportId),
                ],
            ], 200);
        }

        return response()->json([
            'status'  => 'processing',
            'message' => 'Export is still being generated. Please check back shortly.',
            'data'    => [
                'export_id'        => $exportId,
                'status_check_url' => url('/api/v1/exports/status/' . $exportId),
            ],
        ], 200);
    }

    /**
     * Extract common filter parameters from the request.
     */
    private function extractFilters(Request $request): array
    {
        return [
            'batch_id'    => $request->query('batch_id'),
            'status'      => $request->query('status'),
            'province'    => $request->query('province'),
            'intake_year' => $request->query('intake_year'),
            'search'      => $request->query('search'),
        ];
    }
}
