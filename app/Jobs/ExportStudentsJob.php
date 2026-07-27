<?php

namespace App\Jobs;

use App\Exports\StudentsExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ExportStudentsJob — handles async Excel export for large datasets.
 *
 * When the requested export exceeds the sync_threshold (5,000 records),
 * the ExportController dispatches this job instead of streaming the file
 * directly. The generated file is stored on disk so the user can download
 * it later via the download endpoint.
 */
class ExportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600; // 10 minutes for very large exports

    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public array  $filters,
        public string $exportId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = config('export.disk', 'local');
        $path = config('export.path', 'exports');
        $filename = $this->exportId . '.xlsx';
        $fullPath = $path . '/' . $filename;

        Log::info('Starting async Excel export', [
            'export_id' => $this->exportId,
            'filters'   => $this->filters,
        ]);

        try {
            $export = new StudentsExport($this->filters);

            // Store to disk using Laravel Excel
            Excel::store($export, $fullPath, $disk);

            Log::info('Async Excel export completed', [
                'export_id' => $this->exportId,
                'path'      => $fullPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('Async Excel export failed', [
                'export_id' => $this->exportId,
                'error'     => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExportStudentsJob failed permanently', [
            'export_id' => $this->exportId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
