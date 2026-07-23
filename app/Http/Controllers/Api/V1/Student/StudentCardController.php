<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Resources\StudentProfileResource;
use App\Services\Student\StudentCardService;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StudentCardController extends Controller
{
    use ApiResponse;

    protected StudentCardService $studentCardService;

    public function __construct(StudentCardService $studentCardService)
    {
        $this->studentCardService = $studentCardService;
    }

    public function resolveQr(string $qr_token)
    {
        $student = $this->studentCardService->findStudentByQrToken($qr_token);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired QR token.',
            ], 404);
        }

        $student->load(['cards', 'selectionBatch']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    public function resolveByStudentId(string $student_id_no)
    {
        $student = $this->studentCardService->findStudentByStudentIdNo($student_id_no);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found with the given ID.',
            ], 404);
        }

        $student->load(['cards', 'selectionBatch']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    public function verify(string $qrToken)
    {
        $student = $this->studentCardService->findStudentByQrToken($qrToken);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or unknown QR token.',
            ], 404);
        }

        $student->load(['cards', 'enrollmentStatusHistories.changedBy', 'selectionBatch', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile and history retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    public function verifyById(int $studentId)
    {
        $student = $this->studentCardService->findStudentById($studentId);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
            ], 404);
        }

        $student->load(['cards', 'selectionBatch', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    /**
     * Generate A4 PDF Student ID Card.
     */
    public function generateCard(int $id): JsonResponse|Response
    {
        $student = Student::find($id);

        if (!$student) {
            return $this->error('Student not found.', 404);
        }

        $student->load(['cards', 'selectionBatch']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    public function resolveByStudentId(string $student_id_no)
    {
        $student = $this->studentCardService->findStudentByStudentIdNo($student_id_no);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found with the given ID.',
            ], 404);
        }

        $student->load(['cards', 'selectionBatch']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    public function verify(string $qrToken)
    {
        $student = $this->studentCardService->findStudentByQrToken($qrToken);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or unknown QR token.',
            ], 404);
        }

        $student->load(['cards', 'enrollmentStatusHistories.changedBy', 'selectionBatch', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Student profile and history retrieved successfully.',
            'data' => new StudentProfileResource($student),
        ]);
    }

    public function verifyById(int $studentId)
    {
        $student = $this->studentCardService->findStudentById($studentId);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found.',
            ], 404);
        }

        $student->load(['cards', 'enrollmentStatusHistories.changedBy', 'selectionBatch', 'creator']);

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
