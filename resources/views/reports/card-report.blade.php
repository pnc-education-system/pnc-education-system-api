<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Card Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 20px; }
        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #1e3a5f; color: white; padding: 6px 8px; text-align: left; font-size: 9px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; padding: 10px; border-top: 1px solid #e2e8f0; }
        .badge-yes { display: inline-block; padding: 1px 6px; font-size: 8px; font-weight: 600; border-radius: 3px; background-color: #ecfdf5; color: #059669; }
        .badge-no { display: inline-block; padding: 1px 6px; font-size: 8px; font-weight: 600; border-radius: 3px; background-color: #fef2f2; color: #dc2626; }
    </style>
</head>
<body>
    <h1>Student Card Report</h1>
    <p class="subtitle">Generated on: {{ now()->format('F j, Y H:i:s') }} | Total Cards: {{ count($cards) }}</p>

    @if($batchName)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Batch: {{ $batchName }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Card Number</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Batch</th>
                <th>Template</th>
                <th>Issued Date</th>
                <th>Printed Count</th>
                <th>Has PDF</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cards as $index => $card)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $card->card_number }}</td>
                <td>{{ $card->student?->student_id_no ?? '—' }}</td>
                <td>{{ $card->student?->full_name ?? '—' }}</td>
                <td>{{ $card->student?->selectionBatch?->name ?? '—' }}</td>
                <td>{{ $card->cardTemplate?->name ?? '—' }}</td>
                <td>{{ $card->issued_date?->format('Y-m-d') ?? '—' }}</td>
                <td>{{ $card->printed_count ?? 0 }}</td>
                <td>
                    <span class="{{ !empty($card->pdf_path) ? 'badge-yes' : 'badge-no' }}">
                        {{ !empty($card->pdf_path) ? 'Yes' : 'No' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Passerellesnumeriques Cambodia — Card Report</div>
</body>
</html>
