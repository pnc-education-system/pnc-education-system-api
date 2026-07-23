<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Batch Student ID Cards</title>
    <style>
        body {
            margin: 0;
            padding: 10px;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 7px;
        }
        .page { width: 190mm; margin: 0 auto; }
        .card-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .card-table td { width: 50%; padding: 4px; vertical-align: top; }
        .card-inner {
            border: 1px solid #e2e8f0;
            overflow: hidden;
            page-break-inside: avoid;
        }

        /* ═══ CLASSIC LAYOUT ═══ */
        .classic-header { background-color: #1e3a5f; padding: 5px 7px; color: white; }
        .classic-logo { width: 22px; height: 22px; background-color: rgba(255,255,255,0.15); text-align: center; vertical-align: middle; font-size: 7px; font-weight: 800; color: white; }
        .classic-body { padding: 5px 7px 3px; text-align: center; }

        /* ═══ MODERN LAYOUT ═══ */
        .modern-gradient {
            background: #0f2847;
            padding: 5px 7px 16px;
            color: white;
        }
        .modern-logo { width: 22px; height: 22px; background-color: rgba(255,255,255,0.15); text-align: center; vertical-align: middle; font-size: 7px; font-weight: 800; color: white; }
        .modern-body { padding: 0 7px 3px; text-align: center; margin-top: -12px; }

        /* ═══ PREMIUM LAYOUT ═══ */
        .premium-card { background-color: #0f172a; }
        .premium-gold-top { height: 2px; background-color: #f59e0b; }
        .premium-header { padding: 4px 7px 2px; }
        .premium-logo { width: 22px; height: 22px; background-color: #d97706; text-align: center; vertical-align: middle; font-size: 7px; font-weight: 800; color: white; }
        .premium-body { padding: 2px 7px 3px; text-align: center; }
        .premium-gold-bottom { height: 2px; background-color: #f59e0b; }

        /* Shared */
        .header-title { font-size: 8px; font-weight: 700; margin: 0; line-height: 1.2; }
        .header-sub { font-size: 5px; margin: 0; }
        .photo-wrap { width: 34px; height: 34px; border: 1.5px solid #f1f5f9; background-color: #f8fafc; margin: 0 auto 3px; text-align: center; vertical-align: middle; overflow: hidden; }
        .photo-wrap img { width: 34px; height: 34px; }
        .photo-placeholder { width: 34px; height: 34px; font-size: 11px; font-weight: 700; color: white; text-align: center; line-height: 34px; }
        .name { font-size: 8px; font-weight: 700; color: #1e293b; margin: 0 0 1px; }
        .id-number { font-family: 'DejaVu Sans Mono', monospace; font-size: 7px; font-weight: 600; color: #3b82f6; margin: 0 0 2px; }
        .badge { display: inline-block; padding: 1px 5px; font-size: 6px; font-weight: 600; margin-bottom: 2px; }
        .badge-enrolled { background-color: #ecfdf5; color: #059669; }
        .badge-pending { background-color: #fffbeb; color: #d97706; }
        .badge-graduated { background-color: #f5f3ff; color: #7c3aed; }
        .batch-label { font-size: 6px; color: #94a3b8; margin: 0 0 2px; }
        .qr-row td { padding: 0; vertical-align: middle; }
        .qr-label-text { font-size: 5px; color: #94a3b8; font-weight: 600; margin: 0; }
        .qr-label-id { font-size: 5px; color: #cbd5e1; font-family: 'DejaVu Sans Mono', monospace; margin: 0; }
        .qr-img-box { width: 26px; height: 26px; text-align: right; }
        .qr-img-box img { width: 26px; height: 26px; }
        .footer-note { text-align: center; font-size: 5px; color: #cbd5e1; margin: 2px 0 0; }

        /* Premium overrides */
        .premium-body .name { color: #f8fafc; }
        .premium-body .id-number { color: #f59e0b; }
        .premium-body .batch-label { color: #64748b; }
        .premium-body .footer-note { color: rgba(245,158,11,0.3); }
        .premium-photo-border { border: 2px solid #f59e0b; padding: 1px; display: inline-block; margin: 0 auto 3px; }
        .premium-photo { width: 34px; height: 34px; background-color: #0f172a; text-align: center; vertical-align: middle; overflow: hidden; }
        .premium-photo img { width: 34px; height: 34px; }
        .premium-placeholder { width: 34px; height: 34px; background-color: #92400e; font-size: 11px; font-weight: 700; color: white; text-align: center; line-height: 34px; }
        .premium-qr-bg { background-color: #0f172a; border: 1px solid rgba(245,158,11,0.15); padding: 1px; }
        .premium-gold-divider { height: 1px; background-color: rgba(245,158,11,0.15); margin: 2px 0; }
    </style>
</head>
<body>
    <div class="page">
        @php
            $layout = $layout ?? 'classic';
            $rows = [];
            $currentRow = [];
            foreach ($cards as $index => $card) {
                $currentRow[] = $card;
                if (count($currentRow) === 2 || $index === count($cards) - 1) {
                    $rows[] = $currentRow;
                    $currentRow = [];
                }
            }
        @endphp

        <table class="card-table">
            @foreach ($rows as $row)
            <tr>
                @foreach ($row as $card)
                @php
                    $student = $card['student'];
                    $status = strtolower($student->enrollment_status ?? '');
                    $badgeClass = 'badge-pending';
                    if ($status === 'enrolled') $badgeClass = 'badge-enrolled';
                    elseif ($status === 'graduated') $badgeClass = 'badge-graduated';
                    $initials = 'ST';
                    if ($student->full_name) {
                        $parts = explode(' ', trim($student->full_name));
                        $firstChars = '';
                        foreach ($parts as $part) {
                            $firstChars .= substr($part, 0, 1);
                        }
                        $initials = strtoupper(substr($firstChars, 0, 2));
                    }
                @endphp
                <td>
                @if ($layout === 'premium')
                    {{-- ═══ PREMIUM ═══ --}}
                    <div class="card-inner premium-card">
                        <div class="premium-gold-top"></div>

                        {{-- Premium Header --}}
                        <div class="premium-header">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="22">
                                        <div class="premium-logo">PNC</div>
                                    </td>
                                    <td style="padding-left: 6px;">
                                        <p class="header-title" style="color: rgba(245,198,90,0.9);">Passerellesnumeriques Cambodia</p>
                                        <p class="header-sub" style="color: rgba(245,158,11,0.4);">Cambodia</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        {{-- Premium Body --}}
                        <div class="premium-body">
                            {{-- Photo with gold border --}}
                            <div class="premium-photo-border">
                                <div class="premium-photo">
                                    @if(!empty($card['photoBase64']))
                                        <img src="{{ $card['photoBase64'] }}" alt="Photo" />
                                    @else
                                        <div class="premium-placeholder">{{ $initials }}</div>
                                    @endif
                                </div>
                            </div>

                            <p class="name">{{ Str::limit($student->full_name ?? 'Student Name', 24) }}</p>
                            <p class="id-number">{{ $student->student_id_no ?? 'ST-0000' }}</p>

                            <span class="badge {{ $badgeClass }}">{{ ucfirst($student->enrollment_status ?? 'Pending') }}</span>

                            @if($student->selection_batch_name ?? false)
                            <p class="batch-label">{{ $student->selection_batch_name }}@if($student->intake_year) · {{ $student->intake_year }}@endif</p>
                            @endif

                            <div class="premium-gold-divider"></div>

                            @if($card['qrCodeBase64'])
                            <table class="qr-row" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p class="qr-label-text" style="color: rgba(245,158,11,0.4);">Scan to verify</p>
                                        <p class="qr-label-id" style="color: #64748b;">{{ $student->student_id_no ?? '' }}</p>
                                    </td>
                                    <td class="qr-img-box">
                                        <div class="premium-qr-bg">
                                            <img src="{{ $card['qrCodeBase64'] }}" alt="QR" />
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <p class="footer-note">PNC @if($student->intake_year) · {{ $student->intake_year }}@endif</p>
                        </div>

                        <div class="premium-gold-bottom"></div>
                    </div>

                @elseif ($layout === 'modern')
                    {{-- ═══ MODERN ═══ --}}
                    <div class="card-inner">
                        <div class="modern-gradient">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="22">
                                        <div class="modern-logo">PNC</div>
                                    </td>
                                    <td style="padding-left: 6px;">
                                        <p class="header-title">Passerellesnumeriques Cambodia</p>
                                        <p class="header-sub" style="color: rgba(255,255,255,0.5);">Cambodia</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="modern-body">
                            <div class="photo-wrap" style="border: 2px solid white;">
                                @if(!empty($card['photoBase64']))
                                    <img src="{{ $card['photoBase64'] }}" alt="Photo" />
                                @else
                                    <div class="photo-placeholder" style="background-color: #3b82f6;">{{ $initials }}</div>
                                @endif
                            </div>

                            <p class="name">{{ Str::limit($student->full_name ?? 'Student Name', 24) }}</p>
                            <p class="id-number">{{ $student->student_id_no ?? 'ST-0000' }}</p>

                            <span class="badge {{ $badgeClass }}">{{ ucfirst($student->enrollment_status ?? 'Pending') }}</span>

                            <span style="display: inline-block; padding: 1px 5px; font-size: 6px; font-weight: 600; margin: 0 1px 2px; background-color: #eff6ff; color: #2563eb;">
                                {{ $student->selection_batch_name ?? '—' }}
                            </span>
                            @if($student->intake_year)
                            <span style="display: inline-block; padding: 1px 5px; font-size: 6px; font-weight: 600; margin: 0 1px 2px; background-color: #eef2ff; color: #4f46e5;">
                                {{ $student->intake_year }}
                            </span>
                            @endif
                            <span style="display: inline-block; padding: 1px 5px; font-size: 6px; font-weight: 600; margin: 0 1px 2px; background-color: #f9fafb; color: #64748b;">
                                {{ $student->gender ?? '—' }}
                            </span>

                            @if($card['qrCodeBase64'])
                            <table class="qr-row" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 2px;">
                                <tr>
                                    <td>
                                        <p class="qr-label-text">Scan to verify</p>
                                        <p class="qr-label-id">{{ $student->student_id_no ?? '' }}</p>
                                    </td>
                                    <td class="qr-img-box">
                                        <img src="{{ $card['qrCodeBase64'] }}" alt="QR" />
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <p class="footer-note">PNC @if($student->intake_year) · {{ $student->intake_year }}@endif</p>
                        </div>
                    </div>

                @else
                    {{-- ═══ CLASSIC (default) ═══ --}}
                    <div class="card-inner">
                        <div class="classic-header">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td width="22">
                                        <div class="classic-logo">PNC</div>
                                    </td>
                                    <td style="padding-left: 6px;">
                                        <p class="header-title">Passerellesnumeriques Cambodia</p>
                                        <p class="header-sub" style="color: rgba(255,255,255,0.5);">Cambodia</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="classic-body">
                            <div class="photo-wrap">
                                @if(!empty($card['photoBase64']))
                                    <img src="{{ $card['photoBase64'] }}" alt="Photo" />
                                @else
                                    <div class="photo-placeholder" style="background-color: #2563eb;">{{ $initials }}</div>
                                @endif
                            </div>

                            <p class="name">{{ Str::limit($student->full_name ?? 'Student Name', 24) }}</p>
                            <p class="id-number">{{ $student->student_id_no ?? 'ST-0000' }}</p>

                            <span class="badge {{ $badgeClass }}">{{ ucfirst($student->enrollment_status ?? 'Pending') }}</span>

                            @if($student->selection_batch_name ?? false)
                            <p class="batch-label">{{ $student->selection_batch_name }}@if($student->intake_year) · {{ $student->intake_year }}@endif</p>
                            @endif

                            @if($card['qrCodeBase64'])
                            <table class="qr-row" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <p class="qr-label-text">Scan to verify</p>
                                        <p class="qr-label-id">{{ $student->student_id_no ?? '' }}</p>
                                    </td>
                                    <td class="qr-img-box">
                                        <img src="{{ $card['qrCodeBase64'] }}" alt="QR" />
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <p class="footer-note">PNC @if($student->intake_year) · {{ $student->intake_year }}@endif</p>
                        </div>
                    </div>
                @endif
                </td>
                @endforeach

                @if (count($row) < 2)
                <td>
                    <div class="card-inner" style="border: 1px dashed #e2e8f0; background: #f8fafc; min-height: 140px; color: #94a3b8; font-size: 7px; text-align: center; padding-top: 60px;">
                        Empty slot
                    </div>
                </td>
                @endif
            </tr>
            @endforeach
        </table>
    </div>
</body>
</html>
