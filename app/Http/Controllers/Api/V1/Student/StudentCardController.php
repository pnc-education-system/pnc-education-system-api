<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StudentCardController extends Controller
{
    use ApiResponse;

    /**
     * Generate A4 PDF Student ID Card.
     */
    public function generateCard(int $id): JsonResponse|Response
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found.', 404);
        }

        // BR-5 Guard: Student must have a saved photo
        if (empty($student->photo_path) || !Storage::disk('public')->exists($student->photo_path)) {
            return $this->error('Cannot generate ID card: Student photo is missing (BR-5 Guard).', 422);
        }

        // 1. Convert Student Photo to Base64 for DomPDF rendering
        $photoAbsolutePath = Storage::disk('public')->path($student->photo_path);
        $photoMimeType = mime_content_type($photoAbsolutePath) ?: 'image/jpeg';
        $photoBase64 = 'data:' . $photoMimeType . ';base64,' . base64_encode(file_get_contents($photoAbsolutePath));

        // 2. Generate QR Code with student verification URL (matches frontend format)
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
        $verifyUrl = $origin . '/verify/' . urlencode($student->student_id_no) . '?' . $params;
        $qrRaw = QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl);
        $qrCodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrRaw);

        // 3. Render A4 PDF
        $pdf = Pdf::loadView('pdfs.student-card', [
            'student'      => $student,
            'photoBase64'  => $photoBase64,
            'qrCodeBase64' => $qrCodeBase64,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("student-card-{$student->student_id_no}.pdf");
    }
}
