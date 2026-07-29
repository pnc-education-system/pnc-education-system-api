<?php

namespace App\Exports;

use App\Models\Evaluation;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * IndividualEvaluationExport — generates a PDF report
 * for a single student's evaluation history, including
 * category breakdowns, period-over-period changes, and a
 * trend summary.
 *
 * Scope: Individual
 */
class IndividualEvaluationExport
{
    public function __construct(
        public int $studentId,
    ) {}

    /**
     * Generate the individual evaluation report PDF.
     *
     * @return string Raw PDF bytes
     */
    public function generate(): string
    {
        $student = Student::with('selectionBatch')->findOrFail($this->studentId);

        // Get evaluations with answers, questions, and categories (ordered chronologically)
        $evaluations = Evaluation::where('student_id', $this->studentId)
            ->with([
                'answers.question.category',
                'evaluationForm.categories.questions',
                'reviewer',
            ])
            ->orderBy('submitted_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Build enriched evaluation data with category scores and period-over-period
        $evaluationData = [];
        $previousEval = null;

        foreach ($evaluations as $evaluation) {
            $categoryScores = $this->buildCategoryScores($evaluation);

            $currentTotal = (float) $evaluation->total_score;
            $periodOverPeriod = null;

            if ($previousEval) {
                $prevTotal = (float) $previousEval->total_score;
                $change = $currentTotal - $prevTotal;
                $changePercent = $prevTotal > 0
                    ? round(($change / $prevTotal) * 100, 2)
                    : 0;

                $periodOverPeriod = [
                    'previous_total'  => $prevTotal,
                    'change'          => $change >= 0 ? "+{$change}" : (string) $change,
                    'change_percent'  => $changePercent >= 0 ? "+{$changePercent}%" : "{$changePercent}%",
                ];
            }

            $evaluationData[] = [
                'id'                 => $evaluation->id,
                'evaluation_period'  => $evaluation->evaluation_period,
                'evaluation_form'    => $evaluation->evaluationForm?->name ?? 'N/A',
                'total_score'        => $currentTotal,
                'status'             => $evaluation->status,
                'submitted_at'       => $evaluation->submitted_at?->format('Y-m-d H:i:s'),
                'reviewer_name'      => $evaluation->reviewer?->name ?? 'N/A',
                'category_scores'    => $categoryScores,
                'period_over_period' => $periodOverPeriod,
            ];

            $previousEval = $evaluation;
        }

        // Trend summary
        $trendSummary = $this->calculateTrendSummary($evaluations);

        // Reverse to show newest first in the report
        $evaluationData = array_reverse($evaluationData);

        // Evaluate max possible score from the evaluation form structure
        $maxPossibleScore = $this->calculateMaxScore($evaluations);

        $pdf = Pdf::loadView('pdfs.evaluation-individual-report', [
            'student'            => $student,
            'evaluations'        => $evaluationData,
            'trend_summary'      => $trendSummary,
            'max_possible_score' => $maxPossibleScore,
            'generated_at'       => now()->format('Y-m-d H:i:s'),
        ])
            ->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Estimate the number of evaluations for this student.
     */
    public function estimateCount(): int
    {
        return Evaluation::where('student_id', $this->studentId)->count();
    }

    /**
     * Build category-level scores for an evaluation.
     */
    private function buildCategoryScores(Evaluation $evaluation): array
    {
        $form = $evaluation->evaluationForm;

        if (!$form || !$form->relationLoaded('categories')) {
            return [];
        }

        $answersByQuestion = $evaluation->answers->keyBy('question_id');

        $categories = [];
        foreach ($form->categories as $category) {
            $totalScore = 0;
            $maxScore = 0;
            $questions = [];

            foreach ($category->questions as $question) {
                $answer = $answersByQuestion->get($question->id);
                $score = $answer ? (float) $answer->score : 0;
                $totalScore += $score;
                $maxScore += (float) $question->score;

                $questions[] = [
                    'question_text' => $question->question_text,
                    'score'         => $score,
                    'max_score'     => (float) $question->score,
                ];
            }

            $percentage = $maxScore > 0
                ? round(($totalScore / $maxScore) * 100, 2)
                : 0;

            $categories[] = [
                'category_name' => $category->name,
                'total_score'   => $totalScore,
                'max_score'     => $maxScore,
                'percentage'    => $percentage,
                'questions'     => $questions,
            ];
        }

        return $categories;
    }

    /**
     * Calculate trend summary statistics.
     */
    private function calculateTrendSummary($evaluations): array
    {
        if ($evaluations->isEmpty()) {
            return [
                'average_score'      => 0,
                'improvement_rate'   => '0%',
                'best_period'        => null,
                'best_score'         => 0,
                'total_evaluations'  => 0,
            ];
        }

        $totalScore = 0;
        $bestScore = 0;
        $bestPeriod = null;

        foreach ($evaluations as $evaluation) {
            $score = (float) $evaluation->total_score;
            $totalScore += $score;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPeriod = $evaluation->evaluation_period;
            }
        }

        $averageScore = round($totalScore / $evaluations->count(), 2);

        $improvementRate = '0%';
        if ($evaluations->count() > 1) {
            $oldestScore = (float) $evaluations->first()->total_score;
            $newestScore = (float) $evaluations->last()->total_score;
            $change = $newestScore - $oldestScore;
            $rate = $oldestScore > 0 ? round(($change / $oldestScore) * 100, 2) : 0;
            $improvementRate = $rate >= 0 ? "+{$rate}%" : "{$rate}%";
        }

        return [
            'average_score'      => $averageScore,
            'improvement_rate'   => $improvementRate,
            'best_period'        => $bestPeriod,
            'best_score'         => $bestScore,
            'total_evaluations'  => $evaluations->count(),
        ];
    }

    /**
     * Calculate the maximum possible score from the evaluation form(s).
     */
    private function calculateMaxScore($evaluations): float
    {
        $maxScore = 0;

        foreach ($evaluations as $evaluation) {
            $form = $evaluation->evaluationForm;
            if ($form && $form->relationLoaded('categories')) {
                foreach ($form->categories as $category) {
                    foreach ($category->questions as $question) {
                        $maxScore += (float) $question->score;
                    }
                }
            }
        }

        return $maxScore;
    }
}
