<?php

namespace App\Services\Student;

use App\Models\Student;
use App\Repositories\StudentRepository;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * IdCardService
 *
 * Contains the business logic for assembling a student's digital ID card.
 * Responsibilities:
 *   - Fetch the student (via the repository)
 *   - Generate / retrieve the QR code image for the student
 *   - Build the data payload that the resource will consume
 */
class IdCardService
{
    /**
     * The storage disk used for QR code images.
     */
    private string $qrDisk = 'public';

    /**
     * The sub-directory within the disk where QR code PNGs are stored.
     */
    private string $qrDirectory = 'qr';

    public function __construct(
        protected StudentRepository $repository,
    ) {}

    /**
     * Retrieve the ID card data for a given student.
     *
     * Returns an associative array ready to be consumed by IdCardResource:
     *   - student_code    => e.g. "ST0001"
     *   - full_name       => e.g. "John Doe"
     *   - photo           => public URL of the student's photo
     *   - batch           => batch year (falls back to batch name)
     *   - qr_code         => public URL of the QR code image
     *   - template        => always "default" (MVP constraint)
     *
     * @param  int  $studentId
     * @return array|null  Returns null when the student does not exist.
     */
    public function getCardData(int $studentId): ?array
    {
        $student = $this->repository->findById($studentId);

        if (!$student) {
            return null;
        }

        return [
            'student_code' => $student->student_id_no,
            'full_name'    => $student->full_name,
            'photo'        => $this->resolvePhotoUrl($student),
            'batch'        => $this->resolveBatchLabel($student),
            'qr_code'      => $this->resolveQrCodeUrl($student),
            'template'     => 'default',
        ];
    }

    // ------------------------------------------------------------------
    //  Private helpers
    // ------------------------------------------------------------------

    /**
     * Return the public URL of the student's photo, or null if no photo exists.
     */
    private function resolvePhotoUrl(Student $student): ?string
    {
        if (empty($student->photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($student->photo_path);
    }

    /**
     * Return the label for the student's batch.
     *
     * Prefers the selection batch's "year" field; falls back to its "name".
     */
    private function resolveBatchLabel(Student $student): string
    {
        $batch = $student->selectionBatch;

        if (!$batch) {
            return 'N/A';
        }

        return (string) ($batch->year ?? $batch->name);
    }

    /**
     * Return the public URL of the student's QR code image.
     *
     * If a QR code image already exists on disk, its URL is returned directly.
     * Otherwise, a new PNG QR code is generated and persisted before returning.
     */
    private function resolveQrCodeUrl(Student $student): string
    {
        $filename = $student->student_id_no . '.png';
        $relativePath = $this->qrDirectory . '/' . $filename;

        // Generate the QR code image if it does not already exist
        if (!Storage::disk($this->qrDisk)->exists($relativePath)) {
            $this->generateQrCodeImage($student->student_id_no, $relativePath);
        }

        return Storage::disk($this->qrDisk)->url($relativePath);
    }

    /**
     * Generate a QR code PNG containing the given data and store it on disk.
     */
    private function generateQrCodeImage(string $data, string $relativePath): void
    {
        // Ensure the QR code directory exists
        $directory = dirname($relativePath);
        if (!Storage::disk($this->qrDisk)->exists($directory)) {
            Storage::disk($this->qrDisk)->makeDirectory($directory);
        }

        // simple-qrcode outputs raw PNG bytes when format is 'png'
        $pngBytes = QrCode::format('png')
            ->size(200)
            ->margin(2)
            ->generate($data);

        Storage::disk($this->qrDisk)->put($relativePath, $pngBytes);
    }
}
