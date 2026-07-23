<?php

namespace App\Jobs;

use App\Services\Card\CardService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateBatchCards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $selectionBatchId,
        public int $templateId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CardService $cardService): void
    {
        Log::info('Starting batch card generation', [
            'selection_batch_id' => $this->selectionBatchId,
            'template_id'        => $this->templateId,
        ]);

        $result = $cardService->generateBatchPdf(
            $this->selectionBatchId,
            $this->templateId,
        );

        if (!$result['success']) {
            Log::error('Batch card generation failed', [
                'selection_batch_id' => $this->selectionBatchId,
                'error'              => $result['error'] ?? 'Unknown error',
            ]);

            $this->fail(new \RuntimeException($result['error'] ?? 'Unknown error'));
            return;
        }

        Log::info('Batch card generation completed', [
            'selection_batch_id' => $this->selectionBatchId,
            'pdf_path'           => $result['pdf_path'],
            'page_count'         => $result['page_count'],
            'card_count'         => $result['card_count'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateBatchCards job failed', [
            'selection_batch_id' => $this->selectionBatchId,
            'template_id'        => $this->templateId,
            'error'              => $exception->getMessage(),
        ]);
    }
}
