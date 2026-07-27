<?php

namespace App\Jobs;

use App\Exports\StudentsPdfExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ExportStudentsPdfJob — handles async PDF report generation for large datasets.
 *
 * When the requested export exceeds the sync_threshold, the ExportController
 * dispatches this job. The generated PDF is stored on disk for later download.
 */
class ExportStudentsPdfJob implements ShouldQueue
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
        $filename = $this->exportId . '.pdf';
        $fullPath = $path . '/' . $filename;

        Log::info('Starting async PDF export', [
            'export_id' => $this->exportId,
            'filters'   => $this->filters,
        ]);

        try {
            $export = new StudentsPdfExport($this->filters);
            $pdfBytes = $export->generate();

            Storage::disk($disk)->put($fullPath, $pdfBytes);

            Log::info('Async PDF export completed', [
                'export_id' => $this->exportId,
                'path'      => $fullPath,
            ]);
        } catch (\Throwable $e) {
            Log::error('Async PDF export failed', [
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
        Log::error('ExportStudentsPdfJob failed permanently', [
            'export_id' => $this->exportId,
            'error'     => $exception->getMessage(),
        ]);
    }
}
