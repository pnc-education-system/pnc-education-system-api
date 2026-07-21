<?php

namespace App\Services\Card;

use App\Models\CardTemplate;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CardService
{
    public function __construct(
        protected CardPdfGenerator $pdfGenerator
    ) {}

    /**
     * Generate a batch PDF for all enrolled students in a selection batch.
     *
     * @param int $selectionBatchId
     * @param int $templateId
     * @return array{success: bool, pdf_path?: string, page_count?: int, card_count?: int, error?: string}
     */
    public function generateBatchPdf(int $selectionBatchId, int $templateId): array
    {
        $batch = SelectionBatch::find($selectionBatchId);
        if (!$batch) {
            return ['success' => false, 'error' => 'Selection batch not found'];
        }

        $template = CardTemplate::find($templateId);
        if (!$template) {
            return ['success' => false, 'error' => 'Card template not found'];
        }

        // Load enrolled students with photos
        $students = $batch->students()
            ->where('enrollment_status', Student::STATUS_ENROLLED)
            ->whereNotNull('photo_path')
            ->orderBy('student_id_no', 'asc')
            ->get();

        if ($students->isEmpty()) {
            return ['success' => false, 'error' => 'No enrolled students with photos found in this batch'];
        }

        $cardsPerPage = config('cards.per_page', 8);
        $allCards = [];

        foreach ($students as $student) {
            $allCards[] = [
                'name'        => $student->full_name,
                'student_id'  => $student->student_id_no,
                'photo_path'  => $student->photo_path,
                'batch_name'  => $batch->name,
                'gender'      => $student->gender,
                'dob'         => $student->dob?->format('Y-m-d'),
                'province'    => $student->province,
            ];
        }

        // Group into pages of 8
        $pages = array_chunk($allCards, $cardsPerPage);

        try {
            $pdfBytes = $this->pdfGenerator->generate($pages);

            // Save PDF to storage
            $disk = config('cards.disk', 'public');
            $path = config('cards.path', 'cards');
            $filename = 'batch_' . $selectionBatchId . '_' . Str::random(8) . '.pdf';
            $fullPath = $path . '/' . $filename;

            Storage::disk($disk)->put($fullPath, $pdfBytes);

            // Create StudentCard records for each student
            $this->createStudentCards($students, $templateId, $fullPath);

            return [
                'success'    => true,
                'pdf_path'   => $fullPath,
                'page_count' => count($pages),
                'card_count' => count($allCards),
            ];
        } catch (\Exception $e) {
            Log::error('Card PDF generation failed', [
                'batch_id'   => $selectionBatchId,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error'   => 'PDF generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create StudentCard records after successful PDF generation.
     */
    protected function createStudentCards($students, int $templateId, string $pdfPath): void
    {
        $now = now();
        $records = [];

        foreach ($students as $student) {
            $records[] = [
                'student_id'   => $student->id,
                'template_id'  => $templateId,
                'card_number'  => $this->generateCardNumber($student),
                'qr_token'     => Str::random(config('cards.qr_token_length', 32)),
                'issued_at'    => $now,
                'printed_count' => 1,
                'pdf_path'     => $pdfPath,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        // Batch insert for performance
        foreach (array_chunk($records, 100) as $chunk) {
            StudentCard::insert($chunk);
        }
    }

    /**
     * Generate a unique card number for a student.
     */
    protected function generateCardNumber($student): string
    {
        $prefix = 'STU';
        $year = date('y');
        $random = strtoupper(Str::random(6));
        return "{$prefix}{$year}-{$random}";
    }
}
