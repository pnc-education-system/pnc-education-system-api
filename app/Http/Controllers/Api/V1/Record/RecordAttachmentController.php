<?php

namespace App\Http\Controllers\Api\V1\Record;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Http\Requests\StoreRecordAttachmentRequest;
use App\Models\RecordAttachment;
use App\Models\Student;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecordAttachmentController extends Controller
{
    use ApiResponse, AuditableLogger;

    /**
     * List all attachments for a student (across all their records).
     */
    public function indexByStudent(Student $student): JsonResponse
    {
        $attachments = RecordAttachment::where(function ($q) use ($student) {
            // Attachments directly linked to this student
            $q->where('student_id', $student->id)
            // Or attachments linked to this student's records
            ->orWhereHas('studentRecord', function ($sub) use ($student) {
                $sub->where('student_id', $student->id);
            });
        })
        ->with('studentRecord')
        ->orderByDesc('created_at')
        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $attachments->map(fn($a) => $a->toFrontendArray()),
        ]);
    }

    /**
     * Upload an attachment for a student (optionally linked to a specific record).
     */
    public function storeForStudent(StoreRecordAttachmentRequest $request, Student $student): JsonResponse
    {
        $recordId = $request->input('record_id');

        // If record_id is provided, verify it belongs to this student
        if ($recordId) {
            $record = StudentRecord::find($recordId);
            if (!$record || $record->student_id !== $student->id) {
                return $this->error('Record not found for this student.', 404);
            }
        }

        $user = Auth::user();
        $file = $request->file('file');

        try {
            // Use client's original extension so browser can recognize the file type
            $extension = $file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $storedName = Str::random(40) . ($extension ? '.' . $extension : '');
            $path = $file->storeAs(config('records.attachment.storage_path', 'attachments'), $storedName, 'public');

            $attachment = RecordAttachment::create([
                'student_record_id' => $recordId,
                'student_id' => $student->id,
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            // Reload to get the studentRecord relationship
            $attachment->load('studentRecord');

            $this->logAudit($attachment, 'attachment_uploaded', $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Attachment uploaded successfully.',
                'data' => $attachment->toFrontendArray(),
            ], 201);
        } catch (\Throwable $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return $this->error('Failed to upload attachment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload an attachment linked to a specific record (legacy endpoint).
     */
    public function store(StoreRecordAttachmentRequest $request, $recordId): JsonResponse
    {
        $record = StudentRecord::find($recordId);

        if (!$record) {
            return $this->error('Record not found.', 404);
        }

        $user = Auth::user();
        $file = $request->file('file');

        try {
            // Use client's original extension so browser can recognize the file type
            $extension = $file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $storedName = Str::random(40) . ($extension ? '.' . $extension : '');
            $path = $file->storeAs(config('records.attachment.storage_path', 'attachments'), $storedName, 'public');

            $attachment = RecordAttachment::create([
                'student_record_id' => $record->id,
                'student_id' => $record->student_id,
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            $attachment->load('studentRecord');
            $this->logAudit($attachment, 'attachment_uploaded', $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Attachment uploaded successfully.',
                'data' => $attachment->toFrontendArray(),
            ], 201);
        } catch (\Throwable $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return $this->error('Failed to upload attachment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an attachment.
     */
    public function destroy(Request $request, Student $student, int $attachmentId): JsonResponse
    {
        $attachment = RecordAttachment::find($attachmentId);

        if (!$attachment) {
            return $this->error('Attachment not found.', 404);
        }

        // Verify the attachment belongs to a record owned by this student
        // (orphan attachments with null student_record_id are treated as valid)
        if ($attachment->student_record_id) {
            $record = StudentRecord::find($attachment->student_record_id);
            if (!$record || $record->student_id !== $student->id) {
                return $this->error('Attachment not found for this student.', 404);
            }
        }

        // Delete the physical file
        Storage::disk('public')->delete($attachment->file_path);

        $oldValues = $attachment->toArray();
        $attachment->delete();

        $this->logAudit($attachment, 'attachment_deleted', $request, $oldValues);

        return response()->json([
            'status' => 'success',
            'message' => 'Attachment deleted successfully.',
        ]);
    }
}
