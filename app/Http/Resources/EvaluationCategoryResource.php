<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationCategoryResource extends JsonResource
{
    /**
     * Transform the EvaluationCategory resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'sort_order' => $this->sort_order,
            'questions'  => EvaluationQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
