<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $photoBase64 = null;
        if ($this->photo_path && Storage::disk('public')->exists($this->photo_path)) {
            try {
                $photoData = Storage::disk('public')->get($this->photo_path);
                if ($photoData !== null) {
                    $mimeType = Storage::disk('public')->mimeType($this->photo_path) ?: 'image/jpeg';
                    $photoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($photoData);
                }
            } catch (\Exception $e) {
                // Silently fail — photo_base64 will remain null
            }
        }

        return [
            'id' => $this->id,
            'student_id_no' => $this->student_id_no,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'dob' => $this->dob ? $this->dob->format('Y-m-d') : null,
            'phone' => $this->phone,
            'email' => $this->email,
            'province' => $this->province,
            'high_school' => $this->high_school,
            'selection_batch_id' => $this->selection_batch_id,
            'selection_batch' => $this->whenLoaded('selectionBatch', fn() => [
                'id' => $this->selectionBatch->id,
                'name' => $this->selectionBatch->name,
            ]),
            'selection_batch_name' => $this->whenLoaded('selectionBatch', fn() => $this->selectionBatch->name),
            'enrollment_status' => $this->enrollment_status,
            'status' => $this->enrollment_status,
            'photo_path' => $this->photo_path,
            'photo_url' => $this->photo_path ? url('storage/' . $this->photo_path) : null,
            'photo_base64' => $photoBase64,
            'intake_year' => $this->intake_year,
            'enrolled_at' => $this->enrolled_at ? $this->enrolled_at->format('Y-m-d') : null,
            'enrollment_note' => $this->enrollmentStatusHistories()
                ->where('new_status', 'Enrolled')
                ->whereNotNull('note')
                ->latest('created_at')
                ->value('note'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
