<?php

namespace App\Exports;

use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * StudentsPdfExport — generates a PDF report of student data.
 *
 * For datasets ≤ sync_threshold, the PDF is generated synchronously.
 * For larger datasets, processing is queued via ExportStudentsPdfJob.
 */
class StudentsPdfExport
{
    /**
     * Optional filter parameters.
     */
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Build the filtered query.
     */
    public function query(): Builder
    {
        $query = Student::query()
            ->select([
                'students.id',
                'students.student_id_no',
                'students.full_name',
                'students.gender',
                'students.dob',
                'students.phone',
                'students.email',
                'students.province',
                'students.high_school',
                'students.selection_batch_id',
                'students.enrollment_status',
                'students.intake_year',
                'students.enrolled_at',
                'students.is_confirmed',
            ])
            ->with('selectionBatch:id,name,year')
            ->orderBy('students.student_id_no', 'asc');

        // Apply filters
        if (!empty($this->filters['batch_id'])) {
            $query->where('students.selection_batch_id', $this->filters['batch_id']);
        }
        if (!empty($this->filters['status'])) {
            $query->where('students.enrollment_status', $this->filters['status']);
        }
        if (!empty($this->filters['province'])) {
            $query->where('students.province', $this->filters['province']);
        }
        if (!empty($this->filters['intake_year'])) {
            $query->where('students.intake_year', $this->filters['intake_year']);
        }

        return $query;
    }

    /**
     * Generate the PDF synchronously.
     *
     * @return string Raw PDF bytes
     */
    public function generate(): string
    {
        $students = $this->query()->get();

        $pdf = Pdf::loadView('pdfs.export-student-report', [
            'students'     => $students,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filters'      => $this->filters,
            'total_count'  => $students->count(),
        ])
            ->setPaper(
                config('export.pdf.page_size', 'A4'),
                config('export.pdf.orientation', 'landscape')
            );

        return $pdf->output();
    }

    /**
     * Estimate the row count without loading all records.
     */
    public function estimateCount(): int
    {
        return $this->query()->count();
    }
}
