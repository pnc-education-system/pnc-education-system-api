<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'graduated_status' => $this->graduated_status,
            'note' => $this->note,
            'changed_by' => $this->changed_by,
            'changed_by_name' => $this->whenLoaded('changedBy', fn() => $this->changedBy?->name),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
