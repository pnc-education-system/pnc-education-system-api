<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluationHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'evaluation_form_id' => $this->evaluation_form_id,
            'evaluation_period' => $this->evaluation_period,
            'total_score' => (float) $this->total_score,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'reviewed_by' => $this->reviewed_by,
            'reviewer_name' => $this->whenLoaded('reviewer', fn() => $this->reviewer?->name),
            'evaluation_form' => $this->whenLoaded('evaluationForm', fn() => [
                'id' => $this->evaluationForm->id,
                'name' => $this->evaluationForm->name,
            ]),
            'category_scores' => $this->whenLoaded('answers', fn() => $this->calculateCategoryScores()),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];

        // Add period_over_period if it exists in the resource's additional data
        if (isset($this->resource->period_over_period)) {
            $data['period_over_period'] = $this->resource->period_over_period;
        }

        return $data;
    }

    private function calculateCategoryScores(): array
    {
        $categoryScores = [];
        
        foreach ($this->answers as $answer) {
            $category = $answer->question->category;
            if ($category) {
                $categoryName = $category->name;
                if (!isset($categoryScores[$categoryName])) {
                    $categoryScores[$categoryName] = [
                        'total_score' => 0,
                        'max_score' => 0,
                        'question_count' => 0,
                    ];
                }
                $categoryScores[$categoryName]['total_score'] += (float) $answer->score;
                $categoryScores[$categoryName]['max_score'] += (float) $answer->question->score;
                $categoryScores[$categoryName]['question_count']++;
            }
        }

        // Calculate percentage for each category
        foreach ($categoryScores as &$category) {
            $category['percentage'] = $category['max_score'] > 0 
                ? round(($category['total_score'] / $category['max_score']) * 100, 2)
                : 0;
        }

        return $categoryScores;
    }
}
