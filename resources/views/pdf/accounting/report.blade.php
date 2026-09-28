<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }} - {{ $club->name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            padding: 30px;
            color: #1e293b;
            background: #ffffff;
            font-size: 12px;
            line-height: 1.5;
        }
        .container { max-width: 720px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .club-name { font-size: 20px; font-weight: 900; color: #0f172a; text-transform: uppercase; }
        .report-title { font-size: 14px; font-weight: 700; color: #334155; margin-top: 4px; }
        .muted { font-size: 10px; color: #94a3b8; margin-top: 4px; }
        .section-title { font-size: 13px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals-row td { font-weight: 800; border-top: 2px solid #0f172a; border-bottom: none; }
        .footer { margin-top: 32px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="club-name">{{ $club->name }}</div>
        <div class="report-title">{{ $reportTitle }}</div>
        <div class="muted">As of {{ now()->format('d M Y H:i') }}</div>
    </div>

    @include('pdf.accounting.reports.'.$reportKey)

    <div class="footer">
        Generated from the club's accounting records by Club Manager.
    </div>
</div>
</body>
</html>
