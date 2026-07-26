<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\StudentRecord;
use App\Models\Evaluation;
use App\Models\EvaluationForm;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportService $reportService
    ) {}

    /**
     * List all supported report types with their metadata.
     */
    public function types(): JsonResponse
    {
        $types = [
            [
                'key'         => 'enrollment',
                'name'        => 'Enrollment Report',
                'description' => 'Student enrollment data grouped by status and batch',
                'formats'     => ['excel', 'pdf'],
                'filters'     => [
                    ['key' => 'batch_id', 'type' => 'select', 'label' => 'Batch', 'source' => 'batches'],
                    ['key' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['Pending', 'Enrolled', 'Rejected', 'Graduated', 'Dropped']],
                ],
            ],
            [
                'key'         => 'student-list',
                'name'        => 'Student List',
                'description' => 'Complete student list with personal and enrollment details',
                'formats'     => ['excel', 'pdf'],
                'filters'     => [
                    ['key' => 'batch_id', 'type' => 'select', 'label' => 'Batch', 'source' => 'batches'],
                    ['key' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['Pending', 'Enrolled', 'Rejected', 'Graduated', 'Dropped']],
                    ['key' => 'province', 'type' => 'text', 'label' => 'Province'],
                    ['key' => 'search', 'type' => 'text', 'label' => 'Search'],
                ],
            ],
            [
                'key'         => 'card',
                'name'        => 'Card Report',
                'description' => 'Generated student ID cards with issuance details',
                'formats'     => ['excel', 'pdf'],
                'filters'     => [
                    ['key' => 'batch_id', 'type' => 'select', 'label' => 'Batch', 'source' => 'batches'],
                    ['key' => 'template_id', 'type' => 'select', 'label' => 'Template', 'source' => 'templates'],
                ],
            ],
            [
                'key'         => 'eval',
                'name'        => 'Evaluation Report',
                'description' => 'Student evaluation scores and results',
                'formats'     => ['excel', 'pdf'],
                'filters'     => [
                    ['key' => 'form_id', 'type' => 'select', 'label' => 'Evaluation Form', 'source' => 'evaluation_forms'],
                    ['key' => 'batch_id', 'type' => 'select', 'label' => 'Batch', 'source' => 'batches'],
                    ['key' => 'status', 'type' => 'select', 'label' => 'Status', 'options' => ['Pending', 'Completed', 'Graded']],
                ],
            ],
            [
                'key'         => 'incident',
                'name'        => 'Incident & Records',
                'description' => 'Student incident reports, disciplinary actions, and journal records',
                'formats'     => ['excel', 'pdf'],
                'filters'     => [
                    ['key' => 'batch_id', 'type' => 'select', 'label' => 'Batch', 'source' => 'batches'],
                    ['key' => 'category', 'type' => 'select', 'label' => 'Category', 'options' => array_merge(['all'], StudentRecord::CATEGORIES)],
                ],
            ],
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Report types retrieved successfully',
            'data' => $types,
        ]);
    }

    /**
     * Get available filter source data (batches, templates, evaluation forms).
     */
    public function filterSources(): JsonResponse
    {
        $batches = SelectionBatch::orderBy('name', 'asc')->get(['id', 'name', 'year']);
        $templates = \App\Models\CardTemplate::orderBy('name', 'asc')->get(['id', 'name']);
        $evaluationForms = EvaluationForm::orderBy('name', 'asc')->get(['id', 'name']);

        return response()->json([
            'status' => 'success',
            'message' => 'Filter sources retrieved successfully',
            'data' => [
                'batches'          => $batches,
                'templates'        => $templates,
                'evaluation_forms' => $evaluationForms,
            ],
        ]);
    }

    /**
     * Generate a report in the specified format.
     *
     * Request body:
     *   - type: string (enrollment, student-list, card, eval, incident)
     *   - format: string (excel, pdf)
     *   - filters: object (optional)
     *       - batch_id: int (optional)
     *       - status: string (optional)
     *       - search: string (optional)
     *       - province: string (optional)
     *       - template_id: int (optional)
     *       - form_id: int (optional)
     *       - category: string (optional)
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'        => 'required|string|in:' . implode(',', ReportService::TYPES),
            'format'      => 'required|string|in:excel,pdf',
            'filters'     => 'nullable|array',
            'filters.batch_id'     => 'nullable|integer|exists:selection_batches,id',
            'filters.status'       => 'nullable|string',
            'filters.search'       => 'nullable|string|max:200',
            'filters.province'     => 'nullable|string|max:100',
            'filters.template_id'  => 'nullable|integer|exists:card_templates,id',
            'filters.form_id'      => 'nullable|integer|exists:evaluation_forms,id',
            'filters.category'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $type    = $request->input('type');
        $format  = $request->input('format');
        $filters = $request->input('filters', []);

        $result = $this->reportService->generate($type, $format, $filters);

        if (!$result['success']) {
            return $this->error($result['error'], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => ucfirst($type) . ' report generated successfully',
            'data'    => [
                'type'     => $type,
                'format'   => $format,
                'filename' => $result['filename'] ?? null,
                'path'     => $result['path'] ?? null,
                'url'      => $result['url'] ?? null,
            ],
        ], 201);
    }

    /**
     * Directly download a report as PDF (streams to browser).
     */
    public function downloadPdf(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $validator = Validator::make($request->all(), [
            'type'        => 'required|string|in:' . implode(',', ReportService::TYPES),
            'filters'     => 'nullable|array',
            'filters.batch_id'     => 'nullable|integer|exists:selection_batches,id',
            'filters.status'       => 'nullable|string',
            'filters.search'       => 'nullable|string|max:200',
            'filters.province'     => 'nullable|string|max:100',
            'filters.template_id'  => 'nullable|integer|exists:card_templates,id',
            'filters.form_id'      => 'nullable|integer|exists:evaluation_forms,id',
            'filters.category'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $type    = $request->input('type');
        $filters = $request->input('filters', []);

        $pdfBytes = $this->reportService->getPdfBytes($type, $filters);

        if (!$pdfBytes) {
            return $this->error('Failed to generate PDF report', 500);
        }

        $filename = "{$type}_report_" . now()->format('Y-m-d') . ".pdf";

        return response($pdfBytes)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$filename}\"");
    }

    /**
     * Get summary stats for dashboard/overview.
     */
    public function summary(): JsonResponse
    {
        $totalStudents   = Student::count();
        $totalCards      = StudentCard::whereNotNull('pdf_path')->count();
        $totalEvaluations = Evaluation::count();
        $totalRecords    = StudentRecord::count();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_students'   => $totalStudents,
                'total_cards'      => $totalCards,
                'total_evaluations' => $totalEvaluations,
                'total_records'    => $totalRecords,
            ],
        ]);
    }
}
