<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationQuestionResource extends JsonResource
{
    /**
     * Transform the EvaluationQuestion resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'question'   => $this->question_text,
            'max_score'  => (float) $this->score,
            'sort_order' => $this->sort_order,
        ];
    }
}
