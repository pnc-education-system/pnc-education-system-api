<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #333;
            padding: 15px;
        }
        .header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #2C3E50;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 18px;
            color: #2C3E50;
            margin-bottom: 4px;
        }
        .header .meta {
            font-size: 9px;
            color: #666;
        }
        .header .meta span {
            margin: 0 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }
        thead {
            display: table-header-group;
        }
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        th {
            background-color: #2C3E50;
            color: #fff;
            padding: 6px 5px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 5px;
            border-bottom: 1px solid #ddd;
            font-size: 8px;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }
        .status-Enrolled   { background: #d4edda; color: #155724; }
        .status-Pending    { background: #fff3cd; color: #856404; }
        .status-Rejected   { background: #f8d7da; color: #721c24; }
        .status-Graduated  { background: #cce5ff; color: #004085; }
        .status-Dropped    { background: #e2e3e5; color: #383d41; }
        .footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
        .page-number:after {
            content: counter(page);
        }
        .summary {
            margin-bottom: 12px;
            padding: 8px 12px;
            background: #f0f4f8;
            border-radius: 4px;
            font-size: 9px;
            display: flex;
            justify-content: space-between;
        }
        .summary strong {
            color: #2C3E50;
        }
        .no-data {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Student Report</h1>
        <div class="meta">
            <span>Generated: {{ $generated_at }}</span>
            <span>Total Students: <strong>{{ $total_count }}</strong></span>
            @if(!empty($filters['batch_id']))
                <span>Batch ID: {{ $filters['batch_id'] }}</span>
            @endif
            @if(!empty($filters['status']))
                <span>Status: {{ $filters['status'] }}</span>
            @endif
            @if(!empty($filters['intake_year']))
                <span>Intake Year: {{ $filters['intake_year'] }}</span>
            @endif
        </div>
    </div>

    @if(!empty($filters['province']))
    <div class="summary">
        <span><strong>Province filter:</strong> {{ $filters['province'] }}</span>
        <span><strong>Records:</strong> {{ $total_count }}</span>
    </div>
    @endif

    @if(count($students) === 0)
        <div class="no-data">No student records match the selected filters.</div>
    @else
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Gender</th>
                <th>DOB</th>
                <th>Phone</th>
                <th>Province</th>
                <th>Batch</th>
                <th>Status</th>
                <th>Intake Year</th>
                <th>Enrolled At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $index => $student)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $student->student_id_no }}</td>
                <td>{{ $student->full_name }}</td>
                <td>{{ $student->gender }}</td>
                <td>{{ $student->dob?->format('Y-m-d') }}</td>
                <td>{{ $student->phone }}</td>
                <td>{{ $student->province }}</td>
                <td>{{ $student->selectionBatch?->name }}</td>
                <td>
                    <span class="status-badge status-{{ $student->enrollment_status }}">
                        {{ $student->enrollment_status }}
                    </span>
                </td>
                <td>{{ $student->intake_year }}</td>
                <td>{{ $student->enrolled_at?->format('Y-m-d') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Page <span class="page-number"></span> &mdash; Student Report &mdash; Generated {{ $generated_at }}
    </div>
</body>
</html>
