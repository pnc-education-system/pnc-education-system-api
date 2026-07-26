<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Student List Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; margin: 20px; }
        h1 { font-size: 18px; color: #1e3a5f; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #1e3a5f; color: white; padding: 6px 8px; text-align: left; font-size: 9px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .badge { display: inline-block; padding: 1px 6px; font-size: 8px; font-weight: 600; border-radius: 3px; }
        .enrolled { background-color: #ecfdf5; color: #059669; }
        .pending { background-color: #fffbeb; color: #d97706; }
        .rejected { background-color: #fef2f2; color: #dc2626; }
        .graduated { background-color: #f5f3ff; color: #7c3aed; }
        .dropped { background-color: #f1f5f9; color: #64748b; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 8px; color: #94a3b8; padding: 10px; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <h1>Student List Report</h1>
    <p class="subtitle">Generated on: {{ now()->format('F j, Y H:i:s') }} | Total Students: {{ count($students) }}</p>

    @if($batchName)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Batch: {{ $batchName }}</p>
    @endif

    @if($statusFilter)
        <p style="font-size: 11px; color: #1e3a5f; font-weight: 700;">Status Filter: {{ $statusFilter }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Gender</th>
                <th>DOB</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Province</th>
                <th>High School</th>
                <th>Batch</th>
                <th>Status</th>
                <th>Confirmed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $student->student_id_no }}</td>
                <td>{{ $student->full_name }}</td>
                <td>{{ $student->gender ?? '—' }}</td>
                <td>{{ $student->dob?->format('Y-m-d') ?? '—' }}</td>
                <td>{{ $student->phone ?? '—' }}</td>
                <td>{{ $student->email ?? '—' }}</td>
                <td>{{ $student->province ?? '—' }}</td>
                <td>{{ $student->high_school ?? '—' }}</td>
                <td>{{ $student->selectionBatch?->name ?? '—' }}</td>
                <td><span class="badge {{ strtolower($student->enrollment_status ?? 'pending') }}">{{ $student->enrollment_status ?? 'Pending' }}</span></td>
                <td>{{ $student->is_confirmed ? 'Yes' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Passerellesnumeriques Cambodia — Student List Report</div>
</body>
</html>
