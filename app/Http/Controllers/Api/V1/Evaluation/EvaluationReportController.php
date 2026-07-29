<?php

namespace App\Http\Controllers\Api\V1\Evaluation;

use App\Exports\BatchEvaluationExport;
use App\Exports\IndividualEvaluationExport;
use App\Exports\TrendEvaluationExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\EvaluationForm;
use App\Models\SelectionBatch;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * EvaluationReportController — exports evaluation-specific reports
 * in PDF format with three scopes:
 *   - Individual: a single student's evaluation history
 *   - Batch:     evaluation results across a batch
 *   - Trend:     score trends across periods
 *
 * Each endpoint generates the PDF synchronously (for reasonable data sizes)
 * and streams the result for download.
 */
class EvaluationReportController extends Controller
{
    use ApiResponse;

    /**
     * Export an individual student's evaluation report.
     *
     * GET /v1/exports/evaluations/individual/{studentId}
     */
    public function individual(int $studentId): JsonResponse|\Illuminate\Http\Response
    {
        $student = Student::find($studentId);

        if (!$student) {
            return $this->error('Student not found', 404);
        }

        try {
            $export = new IndividualEvaluationExport($studentId);
            $pdfBytes = $export->generate();

            $filename = 'evaluation_individual_'
                . $student->student_id_no . '_'
                . now()->format('Y-m-d_His') . '.pdf';

            return response($pdfBytes)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            Log::error('Individual evaluation export failed', [
                'student_id' => $studentId,
                'error'      => $e->getMessage(),
            ]);

            return $this->error('Failed to generate individual evaluation report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export a batch evaluation report.
     *
     * GET /v1/exports/evaluations/batch
     *
     * Query params:
     *   - batch_id (optional): filter by selection batch
     *   - evaluation_form_id (optional): filter by evaluation form
     *   - period (optional): filter by evaluation period
     */
    public function batch(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $filters = [
            'batch_id'           => $request->query('batch_id'),
            'evaluation_form_id' => $request->query('evaluation_form_id'),
            'period'             => $request->query('period'),
        ];

        try {
            $export = new BatchEvaluationExport($filters);
            $pdfBytes = $export->generate();

            $filename = 'evaluation_batch_'
                . now()->format('Y-m-d_His') . '.pdf';

            return response($pdfBytes)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            Log::error('Batch evaluation export failed', [
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to generate batch evaluation report: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export a trend evaluation report.
     *
     * GET /v1/exports/evaluations/trend
     *
     * Query params:
     *   - batch_id (optional): filter by selection batch
     *   - evaluation_form_id (required): evaluation form to analyze
     *   - student_id (optional): filter by specific student
     */
    public function trend(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $formId = $request->query('evaluation_form_id');

        if (!$formId) {
            return $this->error('The evaluation_form_id query parameter is required.', 422);
        }

        $form = EvaluationForm::find($formId);
        if (!$form) {
            return $this->error('Evaluation form not found.', 404);
        }

        $filters = [
            'batch_id'           => $request->query('batch_id'),
            'evaluation_form_id' => $formId,
            'student_id'         => $request->query('student_id'),
        ];

        try {
            $export = new TrendEvaluationExport($filters);
            $pdfBytes = $export->generate();

            $filename = 'evaluation_trend_'
                . now()->format('Y-m-d_His') . '.pdf';

            return response($pdfBytes)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Throwable $e) {
            Log::error('Trend evaluation export failed', [
                'filters' => $filters,
                'error'   => $e->getMessage(),
            ]);

            return $this->error('Failed to generate trend evaluation report: ' . $e->getMessage(), 500);
        }
    }
}
