<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Individual Evaluation Report</title>
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
            border-bottom: 2px solid #1a5276;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 16px;
            color: #1a5276;
            margin-bottom: 4px;
        }
        .header .meta {
            font-size: 9px;
            color: #666;
        }
        .header .meta span {
            margin: 0 6px;
        }

        .student-info {
            background: #eaf2f8;
            padding: 10px 14px;
            border-radius: 4px;
            margin-bottom: 14px;
            border-left: 4px solid #1a5276;
        }
        .student-info table { width: 100%; border-collapse: collapse; }
        .student-info td { padding: 2px 8px; font-size: 9px; }
        .student-info .label { font-weight: bold; color: #1a5276; width: 100px; }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1a5276;
            margin: 14px 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ccc;
        }

        .trend-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .trend-card {
            flex: 1;
            min-width: 100px;
            background: #f4f6f7;
            padding: 8px 10px;
            border-radius: 4px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .trend-card .value {
            font-size: 16px;
            font-weight: bold;
            color: #1a5276;
        }
        .trend-card .label {
            font-size: 7px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .trend-card.positive { border-color: #27ae60; }
        .trend-card.positive .value { color: #27ae60; }
        .trend-card.negative { border-color: #e74c3c; }
        .trend-card.negative .value { color: #e74c3c; }
        .trend-card.neutral { border-color: #f39c12; }
        .trend-card.neutral .value { color: #f39c12; }

        .evaluation-block {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .eval-header {
            background: #1a5276;
            color: #fff;
            padding: 6px 10px;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
        }
        .eval-header .period { font-weight: bold; }
        .eval-header .score { font-weight: bold; }
        .eval-body { padding: 8px 10px; }

        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .category-table th {
            background: #2c3e50;
            color: #fff;
            padding: 4px 6px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
        }
        .category-table td {
            padding: 3px 6px;
            border-bottom: 1px solid #eee;
            font-size: 8px;
        }
        .category-table tr:nth-child(even) { background: #f8f9fa; }
        .category-table .cat-name { font-weight: bold; color: #1a5276; }

        .progress-bar {
            height: 6px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 2px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 3px;
        }
        .fill-green { background: #27ae60; }
        .fill-yellow { background: #f39c12; }
        .fill-red { background: #e74c3c; }

        .pop-change {
            font-size: 8px;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        .pop-positive { background: #d5f5e3; color: #1e8449; }
        .pop-negative { background: #fadbd8; color: #922b21; }
        .pop-neutral  { background: #fdebd0; color: #b7950b; }

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
        .page-number:after { content: counter(page); }

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
        <h1>Individual Evaluation Report</h1>
        <div class="meta">
            <span>Generated: {{ $generated_at }}</span>
            <span>Evaluations: <strong>{{ count($evaluations) }}</strong></span>
            @if($max_possible_score > 0)
            <span>Max Possible Score: <strong>{{ $max_possible_score }}</strong></span>
            @endif
        </div>
    </div>

    {{-- Student Info --}}
    <div class="student-info">
        <table>
            <tr>
                <td class="label">Student ID</td>
                <td><strong>{{ $student->student_id_no }}</strong></td>
                <td class="label">Gender</td>
                <td>{{ $student->gender ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Full Name</td>
                <td><strong>{{ $student->full_name }}</strong></td>
                <td class="label">Province</td>
                <td>{{ $student->province ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Batch</td>
                <td>{{ $student->selectionBatch?->name ?? 'N/A' }}</td>
                <td class="label">Intake Year</td>
                <td>{{ $student->intake_year ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    {{-- Trend Summary --}}
    <div class="section-title">Trend Summary</div>
    <div class="trend-summary">
        <div class="trend-card">
            <div class="value">{{ $trend_summary['average_score'] }}</div>
            <div class="label">Average Score</div>
        </div>
        <div class="trend-card {{ $trend_summary['total_evaluations'] > 1 ? (str_starts_with($trend_summary['improvement_rate'], '+') ? 'positive' : (str_starts_with($trend_summary['improvement_rate'], '-') ? 'negative' : 'neutral')) : 'neutral' }}">
            <div class="value">{{ $trend_summary['improvement_rate'] }}</div>
            <div class="label">Improvement Rate</div>
        </div>
        <div class="trend-card">
            <div class="value">{{ $trend_summary['best_score'] }}</div>
            <div class="label">Best Score</div>
        </div>
        <div class="trend-card">
            <div class="value">{{ $trend_summary['best_period'] ?? 'N/A' }}</div>
            <div class="label">Best Period</div>
        </div>
        <div class="trend-card">
            <div class="value">{{ $trend_summary['total_evaluations'] }}</div>
            <div class="label">Total Evaluations</div>
        </div>
    </div>

    {{-- Evaluation Details --}}
    <div class="section-title">Evaluation History</div>

    @if(count($evaluations) === 0)
        <div class="no-data">No evaluations found for this student.</div>
    @else
        @foreach($evaluations as $eval)
        <div class="evaluation-block">
            <div class="eval-header">
                <span class="period">{{ $eval['evaluation_period'] }} — {{ $eval['evaluation_form'] }}</span>
                <span class="score">Score: {{ $eval['total_score'] }}</span>
            </div>
            <div class="eval-body">
                @if($eval['period_over_period'])
                <div style="margin-bottom: 6px;">
                    <span class="pop-change {{ str_starts_with($eval['period_over_period']['change'], '+') ? 'pop-positive' : (str_starts_with($eval['period_over_period']['change'], '-') ? 'pop-negative' : 'pop-neutral') }}">
                        Previous: {{ $eval['period_over_period']['previous_total'] }}
                        ({{ $eval['period_over_period']['change'] }} / {{ $eval['period_over_period']['change_percent'] }})
                    </span>
                </div>
                @endif

                @if(count($eval['category_scores']) > 0)
                <table class="category-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Category</th>
                            <th style="width: 15%;">Score</th>
                            <th style="width: 15%;">Max</th>
                            <th style="width: 15%;">%</th>
                            <th style="width: 25%;">Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($eval['category_scores'] as $cat)
                        <tr>
                            <td class="cat-name">{{ $cat['category_name'] }}</td>
                            <td>{{ $cat['total_score'] }}</td>
                            <td>{{ $cat['max_score'] }}</td>
                            <td>{{ $cat['percentage'] }}%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill {{ $cat['percentage'] >= 70 ? 'fill-green' : ($cat['percentage'] >= 40 ? 'fill-yellow' : 'fill-red') }}" style="width: {{ min($cat['percentage'], 100) }}%;"></div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <div style="font-size: 7px; color: #999; margin-top: 4px;">
                    Submitted: {{ $eval['submitted_at'] }} | Reviewer: {{ $eval['reviewer_name'] }} | Status: {{ $eval['status'] }}
                </div>
            </div>
        </div>
        @endforeach
    @endif

    <div class="footer">
        Page <span class="page-number"></span> &mdash; Individual Evaluation Report &mdash; Generated {{ $generated_at }}
    </div>
</body>
</html>
