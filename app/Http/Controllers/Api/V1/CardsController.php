<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Resources\StudentCardResource;
use App\Http\Resources\StudentResource;
use App\Jobs\GenerateBatchCards;
use App\Models\CardTemplate;
use App\Models\SelectionBatch;
use App\Models\StudentCard;
use App\Services\StudentCardService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CardsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StudentCardService $cardService,
    ) {}

    public function templates()
    {
        $templates = CardTemplate::query()
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Card templates retrieved successfully',
            'data' => $templates,
        ], 200);
    }

    public function studentsByBatch(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|integer|exists:selection_batches,id',
        ]);

        $batchId = $request->input('batch_id');
        $filter = $request->input('filter', 'all');

        $batch = SelectionBatch::find($batchId);

        if (!$batch) {
            return $this->error('Selection batch not found', 404);
        }

        $query = $batch->students();

        if ($filter === 'enrolled') {
            $query->where('enrollment_status', 'Enrolled');
        } elseif ($filter === 'with_photo') {
            $query->whereNotNull('photo_path');
        }

        $students = $query->orderBy('student_id_no', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Batch students retrieved successfully',
            'data' => StudentResource::collection($students),
        ], 200);
    }

    public function batch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'selection_batch_id' => 'required|integer|exists:selection_batches,id',
            'template_id'        => 'required|integer|exists:card_templates,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $selectionBatchId = (int) $request->input('selection_batch_id');
        $templateId       = (int) $request->input('template_id');

        try {
            // Dispatch the queued job
            $jobId = Str::uuid()->toString();
            $job = new GenerateBatchCards($selectionBatchId, $templateId);
            dispatch($job);

            return response()->json([
                'status'  => 'success',
                'message' => 'Batch card generation has been queued.',
                'data'    => [
                    'selection_batch_id' => $selectionBatchId,
                    'template_id'        => $templateId,
                    'job_id'             => $jobId,
                ],
            ], 202);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to dispatch batch card job', [
                'selection_batch_id' => $selectionBatchId,
                'template_id'        => $templateId,
                'error'              => $e->getMessage(),
                'trace'              => $e->getTraceAsString(),
            ]);

            return $this->error('Failed to queue batch card generation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk reprint cards for given student IDs.
     * Creates a card if one doesn't exist yet, then increments printed_count.
     *
     * POST /api/v1/cards/reprint
     */
    public function bulkReprint(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $studentIds = $request->input('student_ids');
        $results = [];

        foreach ($studentIds as $studentId) {
            try {
                $card = $this->cardService->reprint((int) $studentId);
                $results[] = [
                    'student_id' => (int) $studentId,
                    'status'     => 'success',
                    'card_url'   => $card->pdf_path ? asset('storage/' . $card->pdf_path) : null,
                    'qr_data'    => $card->qr_token,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'student_id' => (int) $studentId,
                    'status'     => 'failed',
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Reprint processed',
            'data'    => [
                'results' => $results,
            ],
        ], 200);
    }

    /**
     * Increment the printed_count of the given student card.
     *
     * POST /api/v1/student-cards/{id}/reprint
     */
    public function reprint(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $card = $this->cardService->reprint($id);

            return response()->json([
                'status'  => 'success',
                'message' => 'Card reprinted successfully',
                'data'    => new StudentCardResource($card),
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->error('Student card not found', 404);
        }
    }

    public function downloadByBatch($batchId)
    {
        try {
            $batch = SelectionBatch::find($batchId);

            if (!$batch) {
                return $this->error('Selection batch not found', 404);
            }
            $latestCard = StudentCard::whereHas('student', function ($q) use ($batchId) {
                $q->where('selection_batch_id', $batchId);
            })->whereNotNull('pdf_path')->latest()->first();

            if (!$latestCard || !$latestCard->pdf_path) {
                return $this->error('No generated PDF found for this batch. Please run card generation first.', 404);
            }

            $disk = config('cards.disk', 'public');

            if (!Storage::disk($disk)->exists($latestCard->pdf_path)) {
                return $this->error('PDF file not found on storage.', 404);
            }

            $batchName = $batch->name;
            $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $batchName);
            $filename = "cards_{$sanitized}.pdf";

            return Storage::disk($disk)->download($latestCard->pdf_path, $filename);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download batch PDF', [
                'batch_id' => $batchId,
                'error'    => $e->getMessage(),
            ]);

            return $this->error('Failed to download PDF: ' . $e->getMessage(), 500);
        }
    }
}
