<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'card_number' => $this->card_number,
            'qr_token' => $this->qr_token,
            'issued_date' => $this->issued_date ? $this->issued_date->format('Y-m-d') : null,
            'expired_date' => $this->expired_date ? $this->expired_date->format('Y-m-d') : null,
            'template_id' => $this->template_id,
            'printed_count' => $this->printed_count,
            'pdf_path' => $this->pdf_path,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
