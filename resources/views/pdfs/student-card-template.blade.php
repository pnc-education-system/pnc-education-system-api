<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Student ID Card</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', sans-serif; }
        .card { width: 210mm; height: 297mm; position: relative; overflow: hidden; }

        /* Classic */
        .classic-header { background-color: #1e3a5f; padding: 14px 20px; color: white; }
        .classic-logo-box { width: 40px; height: 40px; background-color: rgba(255,255,255,0.15); text-align: center; vertical-align: middle; font-size: 14px; font-weight: 800; color: white; }
        .classic-body { padding: 24px 20px; text-align: center; }

        /* Modern */
        .modern-gradient { background-color: #0f2847; padding: 14px 20px 28px; color: white; }
        .modern-logo-box { width: 40px; height: 40px; background-color: rgba(255,255,255,0.15); text-align: center; vertical-align: middle; font-size: 14px; font-weight: 800; color: white; }
        .modern-body { padding: 4px 20px 20px; text-align: center; margin-top: -20px; }

        /* Premium */
        .premium-card { background-color: #0f172a; }
        .premium-gold-top { height: 3px; background-color: #f59e0b; }
        .premium-header { padding: 12px 20px 8px; }
        .premium-logo-box { width: 40px; height: 40px; background-color: #d97706; text-align: center; vertical-align: middle; font-size: 14px; font-weight: 800; color: white; }
        .premium-body { padding: 8px 20px 20px; text-align: center; }
        .premium-gold-bottom { height: 3px; background-color: #f59e0b; }

        /* Shared */
        .header-title { font-size: 14px; font-weight: 700; margin: 0; line-height: 1.3; }
        .header-sub { font-size: 10px; margin: 0; }
        .photo-wrap { width: 110px; height: 110px; border: 3px solid #f1f5f9; background-color: #f8fafc; margin: 0 auto 12px; text-align: center; vertical-align: middle; overflow: hidden; }
        .photo-wrap img { width: 110px; height: 110px; }
        .photo-placeholder { width: 110px; height: 110px; background-color: #2563eb; font-size: 32px; font-weight: 700; color: white; text-align: center; line-height: 110px; }
        .name { font-size: 20px; font-weight: 700; color: #1e293b; margin: 0 0 4px; }
        .id-number { font-family: 'DejaVu Sans Mono', monospace; font-size: 16px; font-weight: 600; color: #3b82f6; margin: 0 0 10px; }
        .badge { display: inline-block; padding: 3px 12px; font-size: 11px; font-weight: 600; margin-bottom: 6px; }
        .badge-enrolled { background-color: #ecfdf5; color: #059669; }
        .badge-pending { background-color: #fffbeb; color: #d97706; }
        .badge-graduated { background-color: #f5f3ff; color: #7c3aed; }
        .batch-info { font-size: 11px; color: #94a3b8; margin: 0 0 6px; }
        .qr-section { position: absolute; bottom: 60px; left: 20px; right: 20px; }
        .qr-section td { padding: 0; vertical-align: middle; }
        .qr-label-text { font-size: 9px; color: #94a3b8; font-weight: 600; margin: 0; }
        .qr-label-id { font-size: 9px; color: #cbd5e1; font-family: 'DejaVu Sans Mono', monospace; margin: 0; }
        .qr-img { width: 60px; height: 60px; }
        .qr-img img { width: 60px; height: 60px; }
        .footer-text { position: absolute; bottom: 20px; left: 0; right: 0; text-align: center; font-size: 9px; color: #cbd5e1; margin: 0; }

        /* Premium overrides */
        .premium-body .name { color: #f8fafc; }
        .premium-body .id-number { color: #f59e0b; }
        .premium-body .batch-info { color: #64748b; }
        .premium-body .footer-text { color: rgba(245,158,11,0.3); }
        .premium-photo-border { border: 3px solid #f59e0b; padding: 2px; display: inline-block; margin: 0 auto 12px; }
        .premium-photo { width: 110px; height: 110px; background-color: #0f172a; text-align: center; vertical-align: middle; overflow: hidden; }
        .premium-photo img { width: 110px; height: 110px; }
        .premium-placeholder { width: 110px; height: 110px; background-color: #92400e; font-size: 32px; font-weight: 700; color: white; text-align: center; line-height: 110px; }
        .premium-qr-bg { background-color: #0f172a; border: 1px solid rgba(245,158,11,0.15); padding: 2px; }
        .premium-gold-divider { height: 1px; background-color: rgba(245,158,11,0.15); margin: 4px 0; }

        /* Back side shared */
        .back-header { background-color: #1e3a5f; padding: 12px 16px; color: white; }
        .back-body { padding: 16px 20px; }
        .back-org-text { font-size: 10px; color: #64748b; line-height: 1.6; margin: 0 0 10px; }
        .back-section-label { font-size: 8px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        .back-section-value { font-size: 14px; font-weight: 700; color: #334155; margin: 0 0 8px; }
        .back-divider { border: none; border-top: 1px solid #f1f5f9; margin: 8px 0; }
        .back-info-grid td { padding: 6px 10px; vertical-align: top; }
        .back-info-box { background-color: #eff6ff; padding: 8px 12px; }
        .back-info-label { font-size: 7px; font-weight: 600; color: #3b82f6; text-transform: uppercase; margin: 0; }
        .back-info-value { font-size: 10px; font-weight: 700; color: #1d4ed8; margin: 0; }

        .back-modern-header { background-color: #0f2847; padding: 12px 16px; color: white; }
        .back-premium-section { background-color: #0f172a; color: #f8fafc; }
        .back-premium-title { color: rgba(245,198,90,0.9); }
        .back-premium-text { color: #94a3b8; }
        .back-premium-divider { height: 1px; background-color: rgba(245,158,11,0.15); margin: 8px 0; }
        .back-premium-label { color: rgba(245,158,11,0.6); }
        .back-premium-value { color: #f59e0b; }
        .back-premium-box { background-color: rgba(255,255,255,0.05); border: 1px solid rgba(245,158,11,0.15); padding: 8px 12px; }
    </style>
</head>
<body>
@php
    $layout = $layout ?? 'classic';
    $status = strtolower($student->enrollment_status ?? '');
    $badgeClass = 'badge-pending';
    if ($status === 'enrolled') $badgeClass = 'badge-enrolled';
    elseif ($status === 'graduated') $badgeClass = 'badge-graduated';
    $initials = 'ST';
    if ($student->full_name) {
        $parts = explode(' ', trim($student->full_name));
        $firstChars = '';
        foreach ($parts as $part) { $firstChars .= substr($part, 0, 1); }
        $initials = strtoupper(substr($firstChars, 0, 2));
    }
    $managerName = $managerName ?? 'SIM HUL';
    $issueDate = $student->intake_year ? 'October 1, ' . $student->intake_year : '—';
    $expiredDate = $student->intake_year ? 'October 1, ' . ($student->intake_year + 2) : '—';
@endphp

{{-- ═══ FRONT ═══ --}}
@if ($layout === 'premium')
<div class="card premium-card">
    <div class="premium-gold-top"></div>
    <div class="premium-header">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="40"><div class="premium-logo-box">PNC</div></td>
                <td style="padding-left: 12px;">
                    <p class="header-title" style="color: rgba(245,198,90,0.9);">Passerellesnumeriques Cambodia</p>
                    <p class="header-sub" style="color: rgba(245,158,11,0.4);">Cambodia</p>
                </td>
            </tr>
        </table>
    </div>
    <div class="premium-body">
        <div class="premium-photo-border"><div class="premium-photo">
            @if(!empty($photoBase64))<img src="{{ $photoBase64 }}" alt="Photo" />
            @else<div class="premium-placeholder">{{ $initials }}</div>@endif
        </div></div>
        <p class="name">{{ $student->full_name ?? 'Student Name' }}</p>
        <p class="id-number">{{ $student->student_id_no ?? 'ST-0000' }}</p>
        <span class="badge {{ $badgeClass }}">{{ ucfirst($student->enrollment_status ?? 'Pending') }}</span>
        @if($student->selection_batch_name ?? false)<p class="batch-info">{{ $student->selection_batch_name }}@if($student->intake_year) · {{ $student->intake_year }}@endif</p>@endif
        <div class="premium-gold-divider"></div>
        @if(!empty($qrCodeBase64))
        <div class="qr-section">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <p class="qr-label-text" style="color: rgba(245,158,11,0.4);">Scan to verify</p>
                        <p class="qr-label-id" style="color: #64748b;">{{ $student->student_id_no ?? '' }}</p>
                    </td>
                    <td width="64"><div class="premium-qr-bg"><div class="qr-img"><img src="{{ $qrCodeBase64 }}" alt="QR" /></div></div></td>
                </tr>
            </table>
        </div>
        @endif
        <p class="footer-text">Passerellesnumeriques Cambodia @if($student->intake_year)· {{ $student->intake_year }}@endif</p>
    </div>
    <div class="premium-gold-bottom"></div>
</div>
@elseif ($layout === 'modern')
<div class="card">
    <div class="modern-gradient">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="40"><div class="modern-logo-box">PNC</div></td>
                <td style="padding-left: 12px;">
                    <p class="header-title">Passerellesnumeriques Cambodia</p>
                    <p class="header-sub" style="color: rgba(255,255,255,0.5);">Cambodia</p>
                </td>
            </tr>
        </table>
    </div>
    <div class="modern-body">
        <div class="photo-wrap" style="border: 3px solid white;">
            @if(!empty($photoBase64))<img src="{{ $photoBase64 }}" alt="Photo" />
            @else<div class="photo-placeholder" style="background-color: #3b82f6;">{{ $initials }}</div>@endif
        </div>
        <p class="name">{{ $student->full_name ?? 'Student Name' }}</p>
        <p class="id-number">{{ $student->student_id_no ?? 'ST-0000' }}</p>
        <span class="badge {{ $badgeClass }}">{{ ucfirst($student->enrollment_status ?? 'Pending') }}</span>
        <span style="display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 600; margin: 0 2px 4px; background-color: #eff6ff; color: #2563eb;">{{ $student->selection_batch_name ?? '—' }}</span>
        @if($student->intake_year)<span style="display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: 600; margin: 0 2px 4px; background-color: #eef2ff; color: #4f46e5;">{{ $student->intake_year }}</span>@endif
        @if(!empty($qrCodeBase64))
        <div class="qr-section">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td><p class="qr-label-text">Scan to verify</p><p class="qr-label-id">{{ $student->student_id_no ?? '' }}</p></td>
                    <td width="64"><div class="qr-img"><img src="{{ $qrCodeBase64 }}" alt="QR" /></div></td>
                </tr>
            </table>
        </div>
        @endif
        <p class="footer-text">Passerellesnumeriques Cambodia @if($student->intake_year)· {{ $student->intake_year }}@endif</p>
    </div>
</div>
@else
<div class="card">
    <div class="classic-header">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="40"><div class="classic-logo-box">PNC</div></td>
                <td style="padding-left: 12px;">
                    <p class="header-title">Passerellesnumeriques Cambodia</p>
                    <p class="header-sub" style="color: rgba(255,255,255,0.5);">Cambodia</p>
                </td>
            </tr>
        </table>
    </div>
    <div class="classic-body">
        <div class="photo-wrap">
            @if(!empty($photoBase64))<img src="{{ $photoBase64 }}" alt="Photo" />
            @else<div class="photo-placeholder" style="background-color: #2563eb;">{{ $initials }}</div>@endif
        </div>
        <p class="name">{{ $student->full_name ?? 'Student Name' }}</p>
        <p class="id-number">{{ $student->student_id_no ?? 'ST-0000' }}</p>
        <span class="badge {{ $badgeClass }}">{{ ucfirst($student->enrollment_status ?? 'Pending') }}</span>
        @if($student->selection_batch_name ?? false)<p class="batch-info">{{ $student->selection_batch_name }}@if($student->intake_year) · {{ $student->intake_year }}@endif</p>@endif
        @if(!empty($qrCodeBase64))
        <div class="qr-section">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td><p class="qr-label-text">Scan to verify</p><p class="qr-label-id">{{ $student->student_id_no ?? '' }}</p></td>
                    <td width="64"><div class="qr-img"><img src="{{ $qrCodeBase64 }}" alt="QR" /></div></td>
                </tr>
            </table>
        </div>
        @endif
        <p class="footer-text">Passerellesnumeriques Cambodia @if($student->intake_year)· {{ $student->intake_year }}@endif</p>
    </div>
</div>
@endif

{{-- ═══ BACK ═══ --}}
<div class="card">
    @if ($layout === 'premium')
    <div style="background-color: #0f172a; width: 100%; height: 100%;">
        <div class="premium-gold-top"></div>
        <div class="premium-header">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="36"><div class="premium-logo-box" style="width: 36px; height: 36px; font-size: 11px;">PNC</div></td>
                    <td style="padding-left: 10px;">
                        <p class="header-title" style="color: rgba(245,198,90,0.9); font-size: 12px;">Passerellesnumeriques Cambodia</p>
                        <p class="header-sub" style="color: rgba(245,158,11,0.4); font-size: 8px;">Education for a Better Future</p>
                    </td>
                </tr>
            </table>
        </div>
        <div style="padding: 14px 20px;">
            <p class="back-org-text" style="color: #94a3b8;"><b>Passerellesnumeriques Cambodia.</b> Is a french non-profit organization, created in 2005, which intends to enable the most under privileged young people access to higher education and skilled employment in the promising sector of Information Technology.</p>
            <div class="back-premium-divider"></div>
            <p class="back-premium-label" style="font-size: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Education Manager</p>
            <p class="back-premium-value" style="font-size: 14px; font-weight: 700; margin: 0 0 8px;">{{ $managerName }}</p>
            <div class="back-premium-divider"></div>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%"><div class="back-premium-box" style="margin-right: 4px;">
                        <p class="back-premium-label" style="font-size: 7px;">Issue Date</p>
                        <p class="back-premium-value" style="font-size: 10px;">{{ $issueDate }}</p>
                    </div></td>
                    <td width="50%"><div class="back-premium-box" style="margin-left: 4px;">
                        <p class="back-premium-label" style="font-size: 7px;">Expired Date</p>
                        <p class="back-premium-value" style="font-size: 10px;">{{ $expiredDate }}</p>
                    </div></td>
                </tr>
            </table>
            <p class="footer-text" style="color: rgba(245,158,11,0.2); position: absolute; bottom: 12px;">Property of PNC Cambodia</p>
        </div>
    </div>
    @elseif ($layout === 'modern')
    <div>
        <div class="back-modern-header">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="36"><div class="modern-logo-box" style="width: 36px; height: 36px; font-size: 11px;">PNC</div></td>
                    <td style="padding-left: 10px;">
                        <p class="header-title" style="font-size: 12px;">Passerellesnumeriques Cambodia</p>
                        <p class="header-sub" style="font-size: 8px; color: rgba(255,255,255,0.5);">Education for a Better Future</p>
                    </td>
                </tr>
            </table>
        </div>
        <div class="back-body">
            <p class="back-org-text"><b>Passerellesnumeriques Cambodia.</b> Is a french non-profit organization, created in 2005, which intends to enable the most under privileged young people access to higher education and skilled employment in the promising sector of Information Technology.</p>
            <hr class="back-divider" />
            <p class="back-section-label">Education Manager</p>
            <p class="back-section-value">{{ $managerName }}</p>
            <hr class="back-divider" />
            <table class="back-info-grid" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%"><div class="back-info-box" style="margin-right: 4px;">
                        <p class="back-info-label">Issue Date</p>
                        <p class="back-info-value">{{ $issueDate }}</p>
                    </div></td>
                    <td width="50%"><div class="back-info-box" style="margin-left: 4px; background-color: #fffbeb;">
                        <p class="back-info-label" style="color: #d97706;">Expired Date</p>
                        <p class="back-info-value" style="color: #d97706;">{{ $expiredDate }}</p>
                    </div></td>
                </tr>
            </table>
            <p class="footer-text" style="position: absolute; bottom: 12px;">Property of PNC Cambodia</p>
        </div>
    </div>
    @else
    <div>
        <div class="back-header">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="36"><div class="classic-logo-box" style="width: 36px; height: 36px; font-size: 11px;">PNC</div></td>
                    <td style="padding-left: 10px;">
                        <p class="header-title" style="font-size: 12px;">Passerellesnumeriques Cambodia</p>
                        <p class="header-sub" style="font-size: 8px; color: rgba(255,255,255,0.5);">Education for a Better Future</p>
                    </td>
                </tr>
            </table>
        </div>
        <div class="back-body">
            <p class="back-org-text"><b>Passerellesnumeriques Cambodia.</b> Is a french non-profit organization, created in 2005, which intends to enable the most under privileged young people access to higher education and skilled employment in the promising sector of Information Technology.</p>
            <hr class="back-divider" />
            <p class="back-section-label">Education Manager</p>
            <p class="back-section-value">{{ $managerName }}</p>
            <hr class="back-divider" />
            <table class="back-info-grid" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%"><div class="back-info-box" style="margin-right: 4px;">
                        <p class="back-info-label">Issue Date</p>
                        <p class="back-info-value">{{ $issueDate }}</p>
                    </div></td>
                    <td width="50%"><div class="back-info-box" style="margin-left: 4px; background-color: #fffbeb;">
                        <p class="back-info-label" style="color: #d97706;">Expired Date</p>
                        <p class="back-info-value" style="color: #d97706;">{{ $expiredDate }}</p>
                    </div></td>
                </tr>
            </table>
            <p class="footer-text" style="position: absolute; bottom: 12px;">Property of PNC Cambodia</p>
        </div>
    </div>
    @endif
</div>
</body>
</html>
