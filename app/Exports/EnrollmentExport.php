<?php

namespace App\Exports;

use App\Models\SelectionBatch;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class EnrollmentExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected ?int $batchId;
    protected ?string $status;

    public function __construct(?int $batchId = null, ?string $status = null)
    {
        $this->batchId = $batchId;
        $this->status  = $status;
    }

    public function collection()
    {
        $query = Student::with('selectionBatch')
            ->select([
                'student_id_no',
                'full_name',
                'gender',
                'dob',
                'phone',
                'email',
                'province',
                'high_school',
                'selection_batch_id',
                'enrollment_status',
                'is_confirmed',
                'intake_year',
                'enrolled_at',
                'created_at',
            ]);

        if ($this->batchId) {
            $query->where('selection_batch_id', $this->batchId);
        }

        if ($this->status) {
            $query->where('enrollment_status', $this->status);
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
            'Enrolled At',
            'Registered At',
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
            $student->enrolled_at?->format('Y-m-d') ?? '—',
            $student->created_at?->format('Y-m-d H:i:s') ?? '—',
        ];
    }

    public function title(): string
    {
        return 'Enrollment Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => '1E3A5F']]],
        ];
    }
}
