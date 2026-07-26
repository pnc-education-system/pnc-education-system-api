<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Evaluation Report</title>
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
        .completed { background-color: #ecfdf5; color: #059669; }
        .pending { background-color: #fffbeb; color: #d97706; }
        .graded { background-color: #f5f3ff; color: #7c3aed; }
    </style>
</head>
<body>
    <h1>Evaluation Report</h1>
    <p class="subtitle">Generated on: {{ now()->format('F j, Y H:i:s') }} | Total Evaluations: {{ count($evaluations) }}</p>

    @if($formName)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Evaluation Form: {{ $formName }}</p>
    @endif

    @if($batchName)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Batch: {{ $batchName }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Batch</th>
                <th>Form</th>
                <th>Period</th>
                <th>Total Score</th>
                <th>Status</th>
                <th>Submitted At</th>
                <th>Reviewed By</th>
            </tr>
        </thead>
        <tbody>
            @foreach($evaluations as $index => $eval)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $eval->student?->student_id_no ?? '—' }}</td>
                <td>{{ $eval->student?->full_name ?? '—' }}</td>
                <td>{{ $eval->student?->selectionBatch?->name ?? '—' }}</td>
                <td>{{ $eval->evaluationForm?->name ?? '—' }}</td>
                <td>{{ $eval->evaluation_period ?? '—' }}</td>
                <td>{{ $eval->total_score ?? '—' }}</td>
                <td><span class="badge {{ strtolower($eval->status ?? 'pending') }}">{{ $eval->status ?? 'Pending' }}</span></td>
                <td>{{ $eval->submitted_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                <td>{{ $eval->reviewer?->name ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Passerellesnumeriques Cambodia — Evaluation Report</div>
</body>
</html>
