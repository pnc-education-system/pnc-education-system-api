<?php

namespace App\Exports;

use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Models\SelectionBatch;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * TrendEvaluationExport — generates a PDF report
 * showing evaluation score trends across periods
 * within a batch and evaluation form.
 *
 * Scope: Trend
 */
class TrendEvaluationExport
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
     * Generate the trend evaluation report PDF.
     *
     * @return string Raw PDF bytes
     */
    public function generate(): string
    {
        $batchId = $this->filters['batch_id'] ?? null;
        $formId  = $this->filters['evaluation_form_id'] ?? null;
        $studentId = $this->filters['student_id'] ?? null;

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

        // Build student query
        $studentQuery = Student::with('selectionBatch')
            ->whereHas('evaluations', function ($q) use ($formId) {
                $q->where('status', 'Submitted');
                if ($formId) {
                    $q->where('evaluation_form_id', $formId);
                }
            });

        if ($batchId) {
            $studentQuery->where('selection_batch_id', $batchId);
        }

        if ($studentId) {
            $studentQuery->where('id', $studentId);
        }

        $students = $studentQuery->orderBy('students.student_id_no', 'asc')->get();

        // Collect all available periods
        $allPeriods = Evaluation::whereHas('student', function ($q) use ($batchId, $studentId) {
                if ($batchId) $q->where('selection_batch_id', $batchId);
                if ($studentId) $q->where('id', $studentId);
            })
            ->where('status', 'Submitted')
            ->when($formId, fn($q) => $q->where('evaluation_form_id', $formId))
            ->select('evaluation_period')
            ->distinct()
            ->orderBy('evaluation_period', 'asc')
            ->pluck('evaluation_period')
            ->toArray();

        // Build trend data: per-period averages and per-student scores
        $periodTrends = [];
        foreach ($allPeriods as $period) {
            $evalsInPeriod = Evaluation::where('status', 'Submitted')
                ->where('evaluation_period', $period)
                ->when($formId, fn($q) => $q->where('evaluation_form_id', $formId))
                ->whereHas('student', function ($q) use ($batchId, $studentId) {
                    if ($batchId) $q->where('selection_batch_id', $batchId);
                    if ($studentId) $q->where('id', $studentId);
                })
                ->get();

            $scores = $evalsInPeriod->pluck('total_score')->map(fn($s) => (float) $s);
            $count = $scores->count();

            $periodTrends[] = [
                'period'        => $period,
                'average_score' => $count > 0 ? round($scores->avg(), 2) : 0,
                'min_score'     => $count > 0 ? round($scores->min(), 2) : 0,
                'max_score'     => $count > 0 ? round($scores->max(), 2) : 0,
                'total_students' => $count,
                'total_max'     => $this->getMaxPossibleForPeriod($formId),
            ];
        }

        // Per-student trend data
        $studentTrends = [];
        foreach ($students as $student) {
            $evals = Evaluation::where('student_id', $student->id)
                ->where('status', 'Submitted')
                ->when($formId, fn($q) => $q->where('evaluation_form_id', $formId))
                ->orderBy('submitted_at', 'asc')
                ->get();

            $scoresByPeriod = [];
            foreach ($evals as $eval) {
                $scoresByPeriod[$eval->evaluation_period] = (float) $eval->total_score;
            }

            // Calculate improvement
            $improvement = null;
            if ($evals->count() > 1) {
                $first = (float) $evals->first()->total_score;
                $last = (float) $evals->last()->total_score;
                $change = $last - $first;
                $rate = $first > 0 ? round(($change / $first) * 100, 2) : 0;
                $improvement = $rate >= 0 ? "+{$rate}%" : "{$rate}%";
            }

            $studentTrends[] = [
                'student_id_no'  => $student->student_id_no,
                'full_name'      => $student->full_name,
                'scores_by_period' => $scoresByPeriod,
                'improvement'    => $improvement,
                'total_evaluations' => $evals->count(),
            ];
        }

        // Overall trend summary
        $trendSummary = $this->calculateOverallTrend($periodTrends);

        $pdf = Pdf::loadView('pdfs.evaluation-trend-report', [
            'batch'          => $batch,
            'evaluation_form' => $evalForm,
            'periods'        => $allPeriods,
            'period_trends'  => $periodTrends,
            'student_trends' => $studentTrends,
            'trend_summary'  => $trendSummary,
            'student_count'  => count($students),
            'filters'        => $this->filters,
            'generated_at'   => now()->format('Y-m-d H:i:s'),
        ])
            ->setPaper('A4', 'landscape');

        return $pdf->output();
    }

    /**
     * Calculate the maximum possible score for the evaluation form.
     */
    private function getMaxPossibleForPeriod(?int $formId): float
    {
        if (!$formId) {
            return 0;
        }

        $form = EvaluationForm::with('categories.questions')->find($formId);
        if (!$form) {
            return 0;
        }

        $max = 0;
        foreach ($form->categories as $cat) {
            foreach ($cat->questions as $q) {
                $max += (float) $q->score;
            }
        }

        return $max;
    }

    /**
     * Calculate overall trend summary.
     */
    private function calculateOverallTrend(array $periodTrends): array
    {
        if (empty($periodTrends)) {
            return [
                'overall_average'  => 0,
                'improvement_trend' => '0%',
                'best_period'      => null,
                'worst_period'     => null,
                'total_periods'    => 0,
            ];
        }

        $averages = array_column($periodTrends, 'average_score');
        $overallAvg = round(array_sum($averages) / count($averages), 2);

        $bestIdx = array_search(max($averages), $averages);
        $worstIdx = array_search(min($averages), $averages);

        $improvementTrend = '0%';
        if (count($periodTrends) > 1) {
            $first = $periodTrends[0]['average_score'];
            $last = $periodTrends[count($periodTrends) - 1]['average_score'];
            $change = $last - $first;
            $rate = $first > 0 ? round(($change / $first) * 100, 2) : 0;
            $improvementTrend = $rate >= 0 ? "+{$rate}%" : "{$rate}%";
        }

        return [
            'overall_average'   => $overallAvg,
            'improvement_trend' => $improvementTrend,
            'best_period'       => $periodTrends[$bestIdx]['period'] ?? null,
            'worst_period'      => $periodTrends[$worstIdx]['period'] ?? null,
            'total_periods'     => count($periodTrends),
        ];
    }

    /**
     * Estimate the number of students that would be included.
     */
    public function estimateCount(): int
    {
        $batchId   = $this->filters['batch_id'] ?? null;
        $formId    = $this->filters['evaluation_form_id'] ?? null;
        $studentId = $this->filters['student_id'] ?? null;

        $query = Student::whereHas('evaluations', function ($q) use ($formId) {
            $q->where('status', 'Submitted');
            if ($formId) {
                $q->where('evaluation_form_id', $formId);
            }
        });

        if ($batchId) {
            $query->where('selection_batch_id', $batchId);
        }
        if ($studentId) {
            $query->where('id', $studentId);
        }

        return $query->count();
    }
}
