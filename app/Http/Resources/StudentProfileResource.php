<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $batch = $this->whenLoaded('selectionBatch');
        
        return [
            'id' => $this->id,
            'student_id_no' => $this->student_id_no,
            'full_name' => $this->full_name,
            'gender' => $this->gender,
            'dob' => $this->dob,
            'phone' => $this->phone,
            'email' => $this->email,
            'province' => $this->province,
            'high_school' => $this->high_school,
            'photo_path' => $this->photo_path,
            'enrollment_status' => $this->enrollment_status,
            'intake_year' => $this->intake_year,
            'selection_batch_name' => $batch?->name,
            'qr_token' => $this->whenLoaded('cards', fn() => $this->cards->first()?->qr_token),
        ];
    }
}
