<?php

namespace App\Exports;

use App\Models\StudentCard;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CardReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle, WithStyles, ShouldAutoSize
{
    protected ?int $batchId;
    protected ?int $templateId;

    public function __construct(?int $batchId = null, ?int $templateId = null)
    {
        $this->batchId   = $batchId;
        $this->templateId = $templateId;
    }

    public function collection()
    {
        $query = StudentCard::with(['student.selectionBatch', 'cardTemplate']);

        if ($this->batchId) {
            $query->whereHas('student', function ($q) {
                $q->where('selection_batch_id', $this->batchId);
            });
        }

        if ($this->templateId) {
            $query->where('template_id', $this->templateId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function headings(): array
    {
        return [
            'Card Number',
            'Student ID',
            'Student Name',
            'Batch',
            'Template',
            'Issued Date',
            'Expiry Date',
            'Printed Count',
            'Has PDF',
            'Created At',
        ];
    }

    public function map($card): array
    {
        return [
            $card->card_number,
            $card->student?->student_id_no ?? '—',
            $card->student?->full_name ?? '—',
            $card->student?->selectionBatch?->name ?? '—',
            $card->cardTemplate?->name ?? '—',
            $card->issued_date?->format('Y-m-d') ?? '—',
            $card->expired_date?->format('Y-m-d') ?? '—',
            $card->printed_count ?? 0,
            !empty($card->pdf_path) ? 'Yes' : 'No',
            $card->created_at?->format('Y-m-d H:i:s') ?? '—',
        ];
    }

    public function title(): string
    {
        return 'Card Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => '1E3A5F']]],
        ];
    }
}
