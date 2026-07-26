<?php

namespace App\Exports;

use App\Models\StudentRecord;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncidentExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected ?int $batchId;
    protected ?string $category;

    public function __construct(?int $batchId = null, ?string $category = null)
    {
        $this->batchId  = $batchId;
        $this->category = $category;
    }

    public function collection()
    {
        $query = StudentRecord::with(['student.selectionBatch', 'creator']);

        $category = $this->category ?? StudentRecord::CATEGORY_INCIDENT;

        // If 'all', include all categories; otherwise filter by the specific category
        if ($category !== 'all') {
            $query->where('category', $category);
        }

        if ($this->batchId) {
            $query->whereHas('student', function ($q) {
                $q->where('selection_batch_id', $this->batchId);
            });
        }

        return $query->orderBy('record_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Student ID',
            'Student Name',
            'Batch',
            'Category',
            'Title',
            'Description',
            'Record Date',
            'Recorded By',
            'Created At',
        ];
    }

    public function map($record): array
    {
        return [
            $record->student?->student_id_no ?? '—',
            $record->student?->full_name ?? '—',
            $record->student?->selectionBatch?->name ?? '—',
            ucfirst($record->category),
            $record->title ?? '—',
            $record->description ?? '—',
            $record->record_date?->format('Y-m-d') ?? '—',
            $record->creator?->name ?? '—',
            $record->created_at?->format('Y-m-d H:i:s') ?? '—',
        ];
    }

    public function title(): string
    {
        return 'Incident & Records';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => '1E3A5F']]],
        ];
    }
}
