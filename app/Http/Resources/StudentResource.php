<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'intake_year' => $this->intake_year,
            'enrolled_at' => $this->enrolled_at ? $this->enrolled_at->format('Y-m-d') : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
