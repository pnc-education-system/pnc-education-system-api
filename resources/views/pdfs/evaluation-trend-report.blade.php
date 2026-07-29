<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Evaluation Trend Report</title>
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

        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1a5276;
            margin: 14px 0 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #ccc;
        }

        /* Period Trend Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            page-break-inside: auto;
        }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th {
            background: #1a5276;
            color: #fff;
            padding: 4px;
            text-align: left;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        td {
            padding: 3px 4px;
            border-bottom: 1px solid #ddd;
            font-size: 7px;
        }
        tr:nth-child(even) { background: #f8f9fa; }

        .pct-bar {
            display: inline-block;
            height: 10px;
            min-width: 2px;
            border-radius: 2px;
            vertical-align: middle;
        }
        .pct-green { background: #27ae60; }
        .pct-yellow { background: #f39c12; }
        .pct-red { background: #e74c3c; }

        .trend-indicator {
            font-weight: bold;
            font-size: 8px;
        }
        .trend-up { color: #27ae60; }
        .trend-down { color: #e74c3c; }
        .trend-flat { color: #f39c12; }

        .trend-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .trend-card {
            flex: 1;
            min-width: 80px;
            background: #f4f6f7;
            padding: 6px 8px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .trend-card .value {
            font-size: 13px;
            font-weight: bold;
        }
        .trend-card .lbl {
            font-size: 6px;
            color: #888;
            text-transform: uppercase;
        }
        .trend-card.highlight { border-color: #1a5276; }
        .trend-card.highlight .value { color: #1a5276; }

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
        .badge-red { background: #fadbd8; color: #922b21; }
        .badge-yellow { background: #fdebd0; color: #b7950b; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Evaluation Trend Report</h1>
        <div class="meta">
            <span>Generated: {{ $generated_at }}</span>
            @if($batch)
                <span>Batch: <strong>{{ $batch->name }} ({{ $batch->year }})</strong></span>
            @endif
            @if($evaluation_form)
                <span>Form: <strong>{{ $evaluation_form->name }}</strong></span>
            @endif
            <span>Students: <strong>{{ $student_count }}</strong></span>
        </div>
    </div>

    {{-- Overall Trend Summary --}}
    <div class="section-title">Overall Trend Summary</div>
    <div class="trend-cards">
        <div class="trend-card highlight">
            <div class="value">{{ $trend_summary['overall_average'] }}</div>
            <div class="lbl">Overall Avg</div>
        </div>
        <div class="trend-card">
            <div class="value {{ str_starts_with($trend_summary['improvement_trend'], '+') ? 'trend-up' : (str_starts_with($trend_summary['improvement_trend'], '-') ? 'trend-down' : 'trend-flat') }}">{{ $trend_summary['improvement_trend'] }}</div>
            <div class="lbl">Improvement</div>
        </div>
        <div class="trend-card">
            <div class="value">{{ $trend_summary['best_period'] ?? 'N/A' }}</div>
            <div class="lbl">Best Period</div>
        </div>
        <div class="trend-card">
            <div class="value">{{ $trend_summary['worst_period'] ?? 'N/A' }}</div>
            <div class="lbl">Worst Period</div>
        </div>
        <div class="trend-card">
            <div class="value">{{ $trend_summary['total_periods'] }}</div>
            <div class="lbl">Periods</div>
        </div>
    </div>

    {{-- Period-by-Period Trends --}}
    <div class="section-title">Period-by-Period Averages</div>
    @if(count($period_trends) === 0)
        <div class="no-data">No trend data available.</div>
    @else
    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Avg Score</th>
                <th>Min</th>
                <th>Max</th>
                <th>Students</th>
                <th>Avg %</th>
                <th>Visual</th>
            </tr>
        </thead>
        <tbody>
            @foreach($period_trends as $trend)
            @php
                $pct = $trend['total_max'] > 0 ? round(($trend['average_score'] / $trend['total_max']) * 100, 1) : 0;
            @endphp
            <tr>
                <td><strong>{{ $trend['period'] }}</strong></td>
                <td>{{ $trend['average_score'] }}</td>
                <td>{{ $trend['min_score'] }}</td>
                <td>{{ $trend['max_score'] }}</td>
                <td>{{ $trend['total_students'] }}</td>
                <td>
                    <span class="badge {{ $pct >= 70 ? 'badge-green' : ($pct >= 40 ? 'badge-yellow' : 'badge-red') }}">
                        {{ $pct }}%
                    </span>
                </td>
                <td>
                    <div style="width: 60px; height: 10px; background: #eee; border-radius: 2px; overflow: hidden;">
                        <div class="pct-bar {{ $pct >= 70 ? 'pct-green' : ($pct >= 40 ? 'pct-yellow' : 'pct-red') }}" style="width: {{ min($pct, 100) }}%;"></div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Per-Student Trend Data --}}
    <div class="section-title">Per-Student Score Trends</div>
    @if(count($student_trends) === 0)
        <div class="no-data">No student trend data available.</div>
    @else
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student ID</th>
                <th>Full Name</th>
                @foreach($periods as $p)
                    <th>{{ $p }}</th>
                @endforeach
                <th>Improvement</th>
            </tr>
        </thead>
        <tbody>
            @foreach($student_trends as $idx => $sTrend)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td><strong>{{ $sTrend['student_id_no'] }}</strong></td>
                <td>{{ $sTrend['full_name'] }}</td>
                @foreach($periods as $p)
                    <td>{{ $sTrend['scores_by_period'][$p] ?? '—' }}</td>
                @endforeach
                <td>
                    @if($sTrend['improvement'])
                    <span class="trend-indicator {{ str_starts_with($sTrend['improvement'], '+') ? 'trend-up' : (str_starts_with($sTrend['improvement'], '-') ? 'trend-down' : 'trend-flat') }}">
                        {{ $sTrend['improvement'] }}
                    </span>
                    @else
                        —
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        Page <span class="page-number"></span> &mdash; Evaluation Trend Report &mdash; Generated {{ $generated_at }}
    </div>
</body>
</html>
