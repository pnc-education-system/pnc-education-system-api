<?php

namespace App\Http\Controllers\Api\V1\Record;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Api\V1\Concerns\AuditableLogger;
use App\Http\Requests\StoreRecordAttachmentRequest;
use App\Models\RecordAttachment;
use App\Models\StudentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RecordAttachmentController extends Controller
{
    use ApiResponse, AuditableLogger;

    public function store(StoreRecordAttachmentRequest $request, $recordId): JsonResponse
    {
        $record = StudentRecord::find($recordId);

        if (!$record) {
            return $this->error('Record not found.', 404);
        }

        $user = Auth::user();
        $file = $request->file('file');

        try {
            $path = $file->store(config('records.attachment.storage_path'), 'public');

            $attachment = RecordAttachment::create([
                'student_record_id' => $record->id,
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            $this->logAudit($attachment, 'attachment_uploaded', $request);

            return response()->json([
                'status' => 'success',
                'message' => 'Attachment uploaded successfully.',
                'data' => [
                    'id' => $attachment->id,
                    'file_path' => $attachment->file_path,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'uploaded_by' => $attachment->uploaded_by,
                    'created_at' => $attachment->created_at,
                ],
            ], 201);
        } catch (\Exception $e) {
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return $this->error('Failed to upload attachment: ' . $e->getMessage(), 500);
        }
    }
}
