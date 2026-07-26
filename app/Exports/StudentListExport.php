<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentListExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Student::with('selectionBatch');

        if (!empty($this->filters['status'])) {
            $query->where('enrollment_status', $this->filters['status']);
        }

        if (!empty($this->filters['batch_id'])) {
            $query->where('selection_batch_id', $this->filters['batch_id']);
        }

        if (!empty($this->filters['province'])) {
            $query->where('province', 'like', '%' . $this->filters['province'] . '%');
        }

        if (!empty($this->filters['search'])) {
            $search = '%' . $this->filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('student_id_no', 'like', $search)
                  ->orWhere('full_name', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        return $query->orderBy('student_id_no', 'asc')->get();
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Full Name',
            'Gender',
            'Date of Birth',
            'Phone',
            'Email',
            'Province',
            'High School',
            'Batch',
            'Enrollment Status',
            'Confirmed',
            'Intake Year',
            'Has Photo',
        ];
    }

    public function map($student): array
    {
        return [
            $student->student_id_no,
            $student->full_name,
            $student->gender ?? '—',
            $student->dob?->format('Y-m-d') ?? '—',
            $student->phone ?? '—',
            $student->email ?? '—',
            $student->province ?? '—',
            $student->high_school ?? '—',
            $student->selectionBatch?->name ?? '—',
            $student->enrollment_status ?? 'Pending',
            $student->is_confirmed ? 'Yes' : 'No',
            $student->intake_year ?? '—',
            !empty($student->photo_path) ? 'Yes' : 'No',
        ];
    }

    public function title(): string
    {
        return 'Student List';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => '1E3A5F']]],
        ];
    }
}
