<?php

namespace App\Exports;

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\SelectionBatch;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * BatchEvaluationExport — generates a PDF report
 * for evaluation results across a batch (selection batch),
 * optionally filtered by evaluation form and/or period.
 *
 * Scope: Batch
 */
class BatchEvaluationExport
{
    /**
     * Filter parameters.
     */
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Generate the batch evaluation report PDF.
     *
     * @return string Raw PDF bytes
     */
    public function generate(): string
    {
        $batchId = $this->filters['batch_id'] ?? null;
        $formId  = $this->filters['evaluation_form_id'] ?? null;
        $period  = $this->filters['period'] ?? null;

        // Resolve batch info
        $batch = null;
        if ($batchId) {
            $batch = SelectionBatch::find($batchId);
        }

        // Resolve evaluation form info
        $evalForm = null;
        if ($formId) {
            $evalForm = EvaluationForm::with(['categories.questions'])->find($formId);
        }

        // Build query for students in the batch
        $studentQuery = Student::with('selectionBatch')
            ->whereHas('evaluations', function ($q) use ($formId, $period) {
                $q->where('status', 'Submitted');
                if ($formId) {
                    $q->where('evaluation_form_id', $formId);
                }
                if ($period) {
                    $q->where('evaluation_period', $period);
                }
            });

        if ($batchId) {
            $studentQuery->where('selection_batch_id', $batchId);
        }

        $students = $studentQuery->orderBy('students.student_id_no', 'asc')->get();

        // Build per-student evaluation data
        $studentData = [];
        $totalScores = [];
        $periodsFound = [];

        foreach ($students as $student) {
            $evalQuery = Evaluation::where('student_id', $student->id)
                ->where('status', 'Submitted')
                ->with(['answers.question.category', 'evaluationForm.categories.questions']);

            if ($formId) {
                $evalQuery->where('evaluation_form_id', $formId);
            }
            if ($period) {
                $evalQuery->where('evaluation_period', $period);
            }

            $evaluations = $evalQuery->orderBy('submitted_at', 'desc')->get();

            $studentEvals = [];
            $studentTotal = 0;
            $studentMax = 0;

            foreach ($evaluations as $eval) {
                $score = (float) $eval->total_score;
                $studentTotal += $score;

                // Calculate max possible score for this evaluation
                $maxScore = 0;
                $form = $eval->evaluationForm;
                if ($form && $form->relationLoaded('categories')) {
                    foreach ($form->categories as $cat) {
                        foreach ($cat->questions as $q) {
                            $maxScore += (float) $q->score;
                        }
                    }
                }
                $studentMax += $maxScore;

                $periodsFound[$eval->evaluation_period] = true;

                $studentEvals[] = [
                    'evaluation_id'    => $eval->id,
                    'evaluation_period' => $eval->evaluation_period,
                    'form_name'        => $form?->name ?? 'N/A',
                    'total_score'      => $score,
                    'max_score'        => $maxScore,
                    'percentage'       => $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0,
                    'submitted_at'     => $eval->submitted_at?->format('Y-m-d'),
                ];
            }

            $overallPct = $studentMax > 0
                ? round(($studentTotal / $studentMax) * 100, 2)
                : 0;

            $totalScores[] = $studentTotal;

            $studentData[] = [
                'student_id_no' => $student->student_id_no,
                'full_name'     => $student->full_name,
                'gender'        => $student->gender,
                'province'      => $student->province,
                'total_score'   => $studentTotal,
                'max_score'     => $studentMax,
                'percentage'    => $overallPct,
                'evaluations'   => $studentEvals,
            ];
        }

        // Summary statistics
        $studentCount = count($studentData);
        $avgScore = $studentCount > 0
            ? round(array_sum($totalScores) / $studentCount, 2)
            : 0;

        $sortedPeriods = array_keys($periodsFound);
        sort($sortedPeriods);

        $pdf = Pdf::loadView('pdfs.evaluation-batch-report', [
            'batch'         => $batch,
            'evaluation_form' => $evalForm,
            'period'        => $period,
            'students'      => $studentData,
            'student_count' => $studentCount,
            'average_score' => $avgScore,
            'periods'       => $sortedPeriods,
            'filters'       => $this->filters,
            'generated_at'  => now()->format('Y-m-d H:i:s'),
        ])
            ->setPaper('A4', 'landscape');

        return $pdf->output();
    }

    /**
     * Estimate the number of students that would be included.
     */
    public function estimateCount(): int
    {
        $batchId = $this->filters['batch_id'] ?? null;
        $formId  = $this->filters['evaluation_form_id'] ?? null;
        $period  = $this->filters['period'] ?? null;

        $query = Student::whereHas('evaluations', function ($q) use ($formId, $period) {
            $q->where('status', 'Submitted');
            if ($formId) {
                $q->where('evaluation_form_id', $formId);
            }
            if ($period) {
                $q->where('evaluation_period', $period);
            }
        });

        if ($batchId) {
            $query->where('selection_batch_id', $batchId);
        }

        return $query->count();
    }
}
