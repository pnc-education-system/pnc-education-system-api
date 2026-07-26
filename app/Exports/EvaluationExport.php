<?php

namespace App\Exports;

use App\Models\Evaluation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EvaluationExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected ?int $formId;
    protected ?int $batchId;
    protected ?string $status;

    public function __construct(?int $formId = null, ?int $batchId = null, ?string $status = null)
    {
        $this->formId  = $formId;
        $this->batchId = $batchId;
        $this->status  = $status;
    }

    public function collection()
    {
        $query = Evaluation::with([
            'student.selectionBatch',
            'evaluationForm',
            'reviewer',
            'answers.question',
        ]);

        if ($this->formId) {
            $query->where('evaluation_form_id', $this->formId);
        }

        if ($this->batchId) {
            $query->whereHas('student', function ($q) {
                $q->where('selection_batch_id', $this->batchId);
            });
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        return $query->orderBy('submitted_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Student Name',
            'Batch',
            'Evaluation Form',
            'Period',
            'Total Score',
            'Status',
            'Submitted At',
            'Reviewed By',
        ];
    }

    public function map($evaluation): array
    {
        return [
            $evaluation->student?->student_id_no ?? '—',
            $evaluation->student?->full_name ?? '—',
            $evaluation->student?->selectionBatch?->name ?? '—',
            $evaluation->evaluationForm?->name ?? '—',
            $evaluation->evaluation_period ?? '—',
            $evaluation->total_score ?? '—',
            $evaluation->status ?? '—',
            $evaluation->submitted_at?->format('Y-m-d H:i:s') ?? '—',
            $evaluation->reviewer?->name ?? '—',
        ];
    }

    public function title(): string
    {
        return 'Evaluation Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => '1E3A5F']]],
        ];
    }
}
