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

    public function batchReprint(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $studentIds = $request->input('student_ids');
        $results = [];

        foreach ($studentIds as $studentId) {
            try {
                $card = StudentCard::where('student_id', $studentId)->latest()->first();

                if (!$card) {
                    $results[] = [
                        'student_id' => $studentId,
                        'status'     => 'failed',
                        'error'      => 'No card found for this student',
                    ];
                    continue;
                }

                $card = $this->cardService->reprint($card->id);

                $results[] = [
                    'student_id' => $studentId,
                    'status'     => 'success',
                    'card_url'   => $card->pdf_path ? url("storage/{$card->pdf_path}") : null,
                    'qr_data'    => $card->qr_token,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'student_id' => $studentId,
                    'status'     => 'failed',
                    'error'      => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Reprint processed for ' . count($studentIds) . ' student(s).',
            'data'   => [
                'results' => $results,
            ],
        ], 200);
    }

    public function generate(Request $request, $studentId)
    {
        try {
            $student = \App\Models\Student::find($studentId);

            if (!$student) {
                return $this->error('Student not found.', 404);
            }

            // BR-5 Guard: Student must have a saved photo
            if (empty($student->photo_path) || !Storage::disk('public')->exists($student->photo_path)) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Cannot generate ID card: Student photo is missing (BR-5 Guard).',
                    'data'    => [
                        'student_id' => $studentId,
                        'status'     => 'failed',
                        'error'      => 'Student photo is missing',
                    ],
                ], 422);
            }

            // Get default template
            $defaultTemplate = CardTemplate::where('is_default', true)->first();
            if (!$defaultTemplate) {
                $defaultTemplate = CardTemplate::first();
            }

            if (!$defaultTemplate) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'No card template found. Please create a template first.',
                    'data'    => [
                        'student_id' => $studentId,
                        'status'     => 'failed',
                        'error'      => 'No card template found',
                    ],
                ], 422);
            }

            // Validate PDF upload from frontend
            $request->validate([
                'pdf' => 'required|file|mimes:pdf|max:10240', // Max 10MB
            ]);

            // Generate or update the student card record
            $card = StudentCard::updateOrCreate(
                ['student_id' => $studentId],
                [
                    'template_id' => $defaultTemplate->id,
                    'card_number' => 'CARD-' . str_pad($studentId, 6, '0', STR_PAD_LEFT),
                    'qr_token' => \Illuminate\Support\Str::random(32),
                    'printed_count' => 0,
                ]
            );

            // Store PDF from frontend
            $pdfFile = $request->file('pdf');
            $filename = "student-card-{$student->student_id_no}-{$card->id}.pdf";
            $path = "cards/{$filename}";
            Storage::disk('public')->putFileAs('cards', $pdfFile, $filename);

            // Update card with PDF path
            $card->update(['pdf_path' => $path]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Card generated successfully',
                'data'    => [
                    'student_id' => $studentId,
                    'card_url'   => url("storage/{$path}"),
                    'qr_data'    => $card->qr_token,
                    'status'     => 'success',
                ],
            ], 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate card', [
                'student_id' => $studentId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status'  => 'failed',
                'message' => 'Failed to generate card: ' . $e->getMessage(),
                'data'    => [
                    'student_id' => $studentId,
                    'status'     => 'failed',
                    'error'      => $e->getMessage(),
                ],
            ], 500);
        }
    }

    private function generatePdfForStudent($student, $card): string
    {
        // Get the template
        $template = $card->cardTemplate;
        if (!$template) {
            $template = CardTemplate::where('is_default', true)->first();
        }

        if (!$template) {
            return $this->error('No card template found. Please create a template first.', 422);
        }

        // Convert Student Photo to Base64 for DomPDF rendering
        $photoAbsolutePath = Storage::disk('public')->path($student->photo_path);
        $photoMimeType = mime_content_type($photoAbsolutePath) ?: 'image/jpeg';
        $photoBase64 = 'data:' . $photoMimeType . ';base64,' . base64_encode(file_get_contents($photoAbsolutePath));

        // Generate QR Code with student verification URL (matches frontend format)
        $verifyUrl = $this->buildVerifyUrl($student);
        $qrRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl);
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrRaw);

        // Render A4 PDF using dynamic template based on layout_json
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.student-card-template', [
            'student'      => $student,
            'photoBase64'  => $photoBase64,
            'qrCodeBase64' => $qrCodeBase64,
            'template'     => $template,
        ])->setPaper('a4', 'portrait');

        // Save PDF to storage
        $filename = "student-card-{$student->student_id_no}-{$card->id}.pdf";
        $path = "cards/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    public function download($studentId)
    {
        try {
            $card = StudentCard::where('student_id', $studentId)->latest()->first();

            if (!$card || !$card->pdf_path) {
                return $this->error('No generated PDF found for this student. Please generate the card first.', 404);
            }

            $disk = config('cards.disk', 'public');

            if (!Storage::disk($disk)->exists($card->pdf_path)) {
                return $this->error('PDF file not found on storage.', 404);
            }

            $student = $card->student;
            $studentIdNo = $student ? $student->student_id_no : "student_{$studentId}";
            $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentIdNo);
            $filename = "ID_Card_{$sanitized}.pdf";

            $fileContents = Storage::disk($disk)->get($card->pdf_path);

            return response($fileContents)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Content-Length', strlen($fileContents));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download student PDF', [
                'student_id' => $studentId,
                'error'      => $e->getMessage(),
            ]);

            return $this->error('Failed to download PDF: ' . $e->getMessage(), 500);
        }
    }

    public function batchDownload(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'integer|exists:students,id',
            'layout' => 'nullable|string|in:classic,modern,premium',
        ]);

        $studentIds = $request->input('student_ids');
        $layout = $request->input('layout', 'classic');

        try {
            $students = \App\Models\Student::whereIn('id', $studentIds)->get();

            if ($students->isEmpty()) {
                return $this->error('No students found.', 404);
            }

            // Check if all students have photos (BR-5 Guard)
            foreach ($students as $student) {
                if (empty($student->photo_path) || !Storage::disk('public')->exists($student->photo_path)) {
                    return response()->json([
                        'status'  => 'failed',
                        'message' => "Cannot generate ID card for {$student->full_name}: Student photo is missing (BR-5 Guard).",
                        'data'    => [
                            'student_id' => $student->id,
                            'status'     => 'failed',
                            'error'      => 'Student photo is missing',
                        ],
                    ], 422);
                }
            }

            // Use DomPDF to generate batch PDF with all cards on one page
            // Prepare card data for all students
            $cardsData = [];
            foreach ($students as $student) {
                $photoAbsolutePath = Storage::disk('public')->path($student->photo_path);
                $photoMimeType = mime_content_type($photoAbsolutePath) ?: 'image/jpeg';
                $photoBase64 = 'data:' . $photoMimeType . ';base64,' . base64_encode(file_get_contents($photoAbsolutePath));

                // Generate QR Code with student verification URL (matches frontend format)
                $verifyUrl = $this->buildVerifyUrl($student);
                $qrRaw = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl);
                $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrRaw);

                $cardsData[] = [
                    'student' => $student,
                    'photoBase64' => $photoBase64,
                    'qrCodeBase64' => $qrCodeBase64,
                ];
            }

            // Render A4 PDF with all cards
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.batch-student-cards', [
                'cards' => $cardsData,
                'layout' => $layout,
            ])->setPaper('a4', 'portrait');

            // Return PDF as download
            $filename = "ID_Cards_Batch_" . date('Y-m-d_His') . ".pdf";
            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to download batch PDF', [
                'student_ids' => $studentIds,
                'error'       => $e->getMessage(),
            ]);

            return $this->error('Failed to download batch PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Build a verification URL for the QR code that matches the frontend format.
     */
    private function buildVerifyUrl($student): string
    {
        $origin = rtrim(config('app.url'), '/');
        $params = http_build_query([
            'name'   => $student->full_name ?? '',
            'gender' => $student->gender ?? '',
            'batch'  => $student->selection_batch_name ?? '',
            'year'   => $student->intake_year ?? '',
            'status' => $student->enrollment_status ?? '',
            'dob'    => $student->dob ? $student->dob->format('Y-m-d') : '',
            'province' => $student->province ?? '',
        ]);
        return $origin . '/verify/' . urlencode($student->student_id_no) . '?' . $params;
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
