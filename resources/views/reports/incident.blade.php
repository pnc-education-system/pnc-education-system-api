<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Incident & Records Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 20px; }
        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #1e3a5f; color: white; padding: 6px 8px; text-align: left; font-size: 9px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; padding: 10px; border-top: 1px solid #e2e8f0; }
        .badge { display: inline-block; padding: 1px 6px; font-size: 8px; font-weight: 600; border-radius: 3px; }
        .incident { background-color: #fef2f2; color: #dc2626; }
        .disciplinary { background-color: #fef2f2; color: #dc2626; }
        .achievement { background-color: #ecfdf5; color: #059669; }
        .academic { background-color: #eff6ff; color: #2563eb; }
        .general { background-color: #f8fafc; color: #64748b; }
        .medical { background-color: #fdf2f8; color: #db2777; }
        .note { background-color: #fffbeb; color: #d97706; }
    </style>
</head>
<body>
    <h1>Incident & Records Report</h1>
    <p class="subtitle">Generated on: {{ now()->format('F j, Y H:i:s') }} | Total Records: {{ count($records) }}</p>

    @if($batchName)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Batch: {{ $batchName }}</p>
    @endif

    @if($categoryFilter)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Category Filter: {{ ucfirst($categoryFilter) }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Batch</th>
                <th>Category</th>
                <th>Title</th>
                <th>Description</th>
                <th>Record Date</th>
                <th>Recorded By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $record)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $record->student?->student_id_no ?? '—' }}</td>
                <td>{{ $record->student?->full_name ?? '—' }}</td>
                <td>{{ $record->student?->selectionBatch?->name ?? '—' }}</td>
                <td><span class="badge {{ $record->category }}">{{ ucfirst($record->category) }}</span></td>
                <td>{{ $record->title ?? '—' }}</td>
                <td>{{ Str::limit($record->description ?? '—', 60) }}</td>
                <td>{{ $record->record_date?->format('Y-m-d') ?? '—' }}</td>
                <td>{{ $record->creator?->name ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Passerellesnumeriques Cambodia — Incident & Records Report</div>
</body>
</html>
