<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Batch Evaluation Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #333;
            padding: 12px;
        }
        .header {
            text-align: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #1a5276;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 15px;
            color: #1a5276;
            margin-bottom: 3px;
        }
        .header .meta {
            font-size: 8px;
            color: #666;
        }
        .header .meta span {
            margin: 0 5px;
        }

        .summary-bar {
            background: #eaf2f8;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            border-left: 4px solid #1a5276;
        }
        .summary-bar .stat {
            text-align: center;
        }
        .summary-bar .stat .num {
            font-size: 14px;
            font-weight: bold;
            color: #1a5276;
        }
        .summary-bar .stat .lbl {
            font-size: 7px;
            color: #888;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th {
            background: #1a5276;
            color: #fff;
            padding: 5px 4px;
            text-align: left;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        td {
            padding: 4px;
            border-bottom: 1px solid #ddd;
            font-size: 7px;
        }
        tr:nth-child(even) { background: #f8f9fa; }

        .pct-bar {
            display: inline-block;
            height: 8px;
            min-width: 2px;
            border-radius: 2px;
            vertical-align: middle;
        }
        .pct-green { background: #27ae60; }
        .pct-yellow { background: #f39c12; }
        .pct-red { background: #e74c3c; }

        .sub-eval {
            font-size: 6px;
            color: #666;
            margin: 1px 0;
        }

        .meta-info {
            font-size: 7px;
            color: #555;
            padding: 4px 0;
        }

        .footer {
            position: fixed;
            bottom: 8px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 6px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 4px;
        }
        .page-number:after { content: counter(page); }

        .no-data {
            text-align: center;
            padding: 25px;
            color: #999;
            font-size: 11px;
        }

        .badge {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 6px;
            font-weight: bold;
        }
        .badge-green { background: #d5f5e3; color: #1e8449; }
        .badge-yellow { background: #fdebd0; color: #b7950b; }
        .badge-red { background: #fadbd8; color: #922b21; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Batch Evaluation Report</h1>
        <div class="meta">
            <span>Generated: {{ $generated_at }}</span>
            @if($batch)
                <span>Batch: <strong>{{ $batch->name }} ({{ $batch->year }})</strong></span>
            @endif
            @if($evaluation_form)
                <span>Form: <strong>{{ $evaluation_form->name }}</strong></span>
            @endif
            @if($period)
                <span>Period: <strong>{{ $period }}</strong></span>
            @else
                <span>Period: <strong>All Periods</strong></span>
            @endif
        </div>
    </div>

    <div class="summary-bar">
        <div class="stat">
            <div class="num">{{ $student_count }}</div>
            <div class="lbl">Students</div>
        </div>
        <div class="stat">
            <div class="num">{{ $average_score }}</div>
            <div class="lbl">Average Score</div>
        </div>
        <div class="stat">
            <div class="num">{{ count($periods) }}</div>
            <div class="lbl">Periods</div>
        </div>
    </div>

    @if(count($students) === 0)
        <div class="no-data">No evaluation data matches the selected criteria.</div>
    @else
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Full Name</th>
                <th>Gender</th>
                <th>Province</th>
                <th>Total Score</th>
                <th>Max</th>
                <th>%</th>
                <th>Progress</th>
                <th>Evaluations</th>
            </tr>
        </thead>
        <tbody>
            @foreach($students as $idx => $student)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td><strong>{{ $student['student_id_no'] }}</strong></td>
                <td>{{ $student['full_name'] }}</td>
                <td>{{ $student['gender'] ?? '—' }}</td>
                <td>{{ $student['province'] ?? '—' }}</td>
                <td><strong>{{ $student['total_score'] }}</strong></td>
                <td>{{ $student['max_score'] }}</td>
                <td>
                    <span class="badge {{ $student['percentage'] >= 70 ? 'badge-green' : ($student['percentage'] >= 40 ? 'badge-yellow' : 'badge-red') }}">
                        {{ $student['percentage'] }}%
                    </span>
                </td>
                <td>
                    <div style="width: 50px; height: 8px; background: #eee; border-radius: 2px; overflow: hidden;">
                        <div class="pct-bar {{ $student['percentage'] >= 70 ? 'pct-green' : ($student['percentage'] >= 40 ? 'pct-yellow' : 'pct-red') }}" style="width: {{ min($student['percentage'], 100) }}%;"></div>
                    </div>
                </td>
                <td>
                    @foreach($student['evaluations'] as $eval)
                    <div class="sub-eval">
                        {{ $eval['evaluation_period'] }}: {{ $eval['total_score'] }}/{{ $eval['max_score'] }} ({{ $eval['percentage'] }}%)
                    </div>
                    @endforeach
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if($periods)
    <div class="meta-info">
        <strong>Periods covered:</strong> {{ implode(', ', $periods) }}
        @if($evaluation_form)
            &nbsp;|&nbsp; <strong>Form:</strong> {{ $evaluation_form->name }}
        @endif
    </div>
    @endif

    <div class="footer">
        Page <span class="page-number"></span> &mdash; Batch Evaluation Report &mdash; Generated {{ $generated_at }}
    </div>
</body>
</html>
