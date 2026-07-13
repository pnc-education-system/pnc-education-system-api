<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Services\StudentImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessStudentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 1;

    public function __construct(
        public ImportLog $importLog
    ) {

    }

    public function handle(StudentImportService $importService): void
    {
        $importService->process($this->importLog);
    }

    public function failed(\Throwable $exception): void
    {
        $this->importLog->update([
            'status' => 'Failed',
        ]);

        $this->importLog->errors()->create([
            'row_number' => 0,
            'field' => 'system',
            'error_message' => 'Import failed: ' . $exception->getMessage(),
        ]);
    }
}
