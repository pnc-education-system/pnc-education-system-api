<?php

namespace App\Services;

use App\Exports\CardReportExport;
use App\Exports\EnrollmentExport;
use App\Exports\EvaluationExport;
use App\Exports\IncidentExport;
use App\Exports\StudentListExport;
use App\Models\Evaluation;
use App\Models\SelectionBatch;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\StudentRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReportService
{
    /**
     * Supported report types.
     */
    const TYPES = ['enrollment', 'student-list', 'card', 'eval', 'incident'];

    /**
     * Generate a report and return the file path or download response.
     *
     * @param string $type       Report type: enrollment, student-list, card, eval, incident
     * @param string $format     Export format: excel, pdf
     * @param array  $filters    Optional filters (batch_id, status, form_id, category, etc.)
     * @return array{success: bool, path?: string, filename?: string, error?: string}
     */
    public function generate(string $type, string $format, array $filters = []): array
    {
        if (!in_array($type, self::TYPES)) {
            return ['success' => false, 'error' => "Unsupported report type: {$type}"];
        }

        if (!in_array($format, ['excel', 'pdf'])) {
            return ['success' => false, 'error' => "Unsupported format: {$format}. Supported: excel, pdf"];
        }

        try {
            $method = $format === 'excel' ? 'generateExcel' : 'generatePdf';
            return $this->{$method}($type, $filters);
        } catch (\Exception $e) {
            Log::error("Report generation failed: {$type}/{$format}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'error' => "Report generation failed: " . $e->getMessage()];
        }
    }

    /**
     * Generate an Excel report.
     */
    protected function generateExcel(string $type, array $filters): array
    {
        $export = $this->resolveExport($type, $filters);

        if (!$export) {
            return ['success' => false, 'error' => "Could not resolve export class for type: {$type}"];
        }

        $filename = $this->buildFilename($type, 'xlsx');
        $disk = config('reports.disk', 'public');
        $path = config('reports.path', 'reports');
        $fullPath = $path . '/' . $filename;

        // Store the file
        Excel::store($export, $fullPath, $disk);

        return [
            'success'  => true,
            'path'     => $fullPath,
            'filename' => $filename,
            'url'      => url("storage/{$fullPath}"),
        ];
    }

    /**
     * Generate a PDF report.
     */
    protected function generatePdf(string $type, array $filters): array
    {
        $viewData = $this->resolveViewData($type, $filters);

        if (!$viewData) {
            return ['success' => false, 'error' => "Could not resolve view data for type: {$type}"];
        }

        $view = "reports.{$type}";
        $pdf = Pdf::loadView($view, $viewData)
            ->setPaper('a4', 'landscape');

        $filename = $this->buildFilename($type, 'pdf');
        $disk = config('reports.disk', 'public');
        $path = config('reports.path', 'reports');
        $fullPath = $path . '/' . $filename;

        Storage::disk($disk)->put($fullPath, $pdf->output());

        return [
            'success'  => true,
            'path'     => $fullPath,
            'filename' => $filename,
            'url'      => url("storage/{$fullPath}"),
        ];
    }

    /**
     * Get the raw PDF bytes for direct download.
     */
    public function getPdfBytes(string $type, array $filters = []): ?string
    {
        $viewData = $this->resolveViewData($type, $filters);

        if (!$viewData) {
            return null;
        }

        $view = "reports.{$type}";
        $pdf = Pdf::loadView($view, $viewData)
            ->setPaper('a4', 'landscape');

        return $pdf->output();
    }

    /**
     * Resolve the Excel export class for a report type.
     */
    protected function resolveExport(string $type, array $filters): ?object
    {
        return match ($type) {
            'enrollment'  => new EnrollmentExport(
                $filters['batch_id'] ?? null,
                $filters['status'] ?? null
            ),
            'student-list' => new StudentListExport($filters),
            'card'         => new CardReportExport(
                $filters['batch_id'] ?? null,
                $filters['template_id'] ?? null
            ),
            'eval'         => new EvaluationExport(
                $filters['form_id'] ?? null,
                $filters['batch_id'] ?? null,
                $filters['status'] ?? null
            ),
            'incident'     => new IncidentExport(
                $filters['batch_id'] ?? null,
                $filters['category'] ?? null
            ),
            default => null,
        };
    }

    /**
     * Resolve the view data for a PDF report.
     */
    protected function resolveViewData(string $type, array $filters): ?array
    {
        $batchName = null;
        if (!empty($filters['batch_id'])) {
            $batch = SelectionBatch::find($filters['batch_id']);
            $batchName = $batch?->name;
        }

        return match ($type) {
            'enrollment' => $this->enrollmentViewData($filters, $batchName),
            'student-list' => $this->studentListViewData($filters, $batchName),
            'card' => $this->cardViewData($filters, $batchName),
            'eval' => $this->evalViewData($filters, $batchName),
            'incident' => $this->incidentViewData($filters, $batchName),
            default => null,
        };
    }

    protected function enrollmentViewData(array $filters, ?string $batchName): array
    {
        $query = Student::with('selectionBatch');

        if (!empty($filters['batch_id'])) {
            $query->where('selection_batch_id', $filters['batch_id']);
        }

        $statusFilter = $filters['status'] ?? null;
        if ($statusFilter) {
            $query->where('enrollment_status', $statusFilter);
        }

        $students = $query->orderBy('student_id_no', 'asc')->get();

        $total    = $students->count();
        $enrolled = $students->where('enrollment_status', 'Enrolled')->count();
        $pending  = $students->where('enrollment_status', 'Pending')->count();
        $rejected = $students->where('enrollment_status', 'Rejected')->count();

        return [
            'students'     => $students,
            'summary'      => compact('total', 'enrolled', 'pending', 'rejected') + ['rate' => $total > 0 ? round(($enrolled / $total) * 100, 2) : 0],
            'batchName'    => $batchName,
            'statusFilter' => $statusFilter,
        ];
    }

    protected function studentListViewData(array $filters, ?string $batchName): array
    {
        $query = Student::with('selectionBatch');

        if (!empty($filters['status'])) {
            $query->where('enrollment_status', $filters['status']);
        }

        if (!empty($filters['batch_id'])) {
            $query->where('selection_batch_id', $filters['batch_id']);
        }

        if (!empty($filters['province'])) {
            $query->where('province', 'like', '%' . $filters['province'] . '%');
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('student_id_no', 'like', $search)
                  ->orWhere('full_name', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $students = $query->orderBy('student_id_no', 'asc')->get();

        return [
            'students'     => $students,
            'batchName'    => $batchName,
            'statusFilter' => $filters['status'] ?? null,
        ];
    }

    protected function cardViewData(array $filters, ?string $batchName): array
    {
        $query = StudentCard::with(['student.selectionBatch', 'cardTemplate']);

        if (!empty($filters['batch_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('selection_batch_id', $filters['batch_id']);
            });
        }

        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        $cards = $query->orderBy('created_at', 'desc')->get();

        return [
            'cards'     => $cards,
            'batchName' => $batchName,
        ];
    }

    protected function evalViewData(array $filters, ?string $batchName): array
    {
        $query = Evaluation::with([
            'student.selectionBatch',
            'evaluationForm',
            'reviewer',
        ]);

        if (!empty($filters['form_id'])) {
            $query->where('evaluation_form_id', $filters['form_id']);
        }

        if (!empty($filters['batch_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('selection_batch_id', $filters['batch_id']);
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $evaluations = $query->orderBy('submitted_at', 'desc')->get();

        $formName = null;
        if (!empty($filters['form_id'])) {
            $form = \App\Models\EvaluationForm::find($filters['form_id']);
            $formName = $form?->name;
        }

        return [
            'evaluations' => $evaluations,
            'formName'    => $formName,
            'batchName'   => $batchName,
        ];
    }

    protected function incidentViewData(array $filters, ?string $batchName): array
    {
        $query = StudentRecord::with(['student.selectionBatch', 'creator']);

        $category = $filters['category'] ?? StudentRecord::CATEGORY_INCIDENT;

        if ($category !== 'all') {
            $query->where('category', $category);
        }

        if (!empty($filters['batch_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('selection_batch_id', $filters['batch_id']);
            });
        }

        $records = $query->orderBy('record_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'records'        => $records,
            'batchName'      => $batchName,
            'categoryFilter' => $category,
        ];
    }

    /**
     * Build a filename for the report export.
     */
    protected function buildFilename(string $type, string $extension): string
    {
        $date = now()->format('Y-m-d_His');
        $rand = Str::random(6);
        return "{$type}_report_{$date}_{$rand}.{$extension}";
    }
}
