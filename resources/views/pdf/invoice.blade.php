<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $invoice->invoice_number }} - {{ $club->name }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            padding: 30px;
            color: #1e293b;
            background: #ffffff;
            font-size: 14px;
            line-height: 1.5;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .header-left {
            display: table-cell;
            vertical-align: middle;
        }
        .header-right {
            display: table-cell;
            text-align: right;
            vertical-align: middle;
        }
        .brand-name {
            font-size: 22px;
            font-weight: 900;
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }
        .badge {
            background-color: #dcfce7;
            color: #15803d;
            font-size: 11px;
            font-weight: 800;
            padding: 6px 14px;
            border-radius: 9999px;
            border: 1px solid #bbf7d0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .details-grid {
            margin: 24px 0;
            width: 100%;
            display: table;
        }
        .detail-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .detail-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .detail-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        .table th {
            background-color: #f8fafc;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .total-box {
            margin-top: 24px;
            text-align: right;
            padding-top: 16px;
            border-top: 2px solid #0f172a;
        }
        .total-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 700;
        }
        .total-amount {
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-left">
            <div class="brand-name">{{ $club->name }}</div>
            <div class="subtitle">Official Payment Receipt & Tax Invoice</div>
        </div>
        <div class="header-right">
            <span class="badge">{{ strtoupper($invoice->status ?? 'PAID') }}</span>
        </div>
    </div>

    <div class="details-grid">
        <div class="detail-col">
            <div class="detail-label">Receipt Number</div>
            <div class="detail-value">#{{ $invoice->invoice_number }}</div>
            <br>
            <div class="detail-label">Date Paid</div>
            <div class="detail-value">{{ $invoice->paid_at ? $invoice->paid_at->format('M d, Y @ H:i') : date('M d, Y') }}</div>
        </div>
        <div class="detail-col">
            <div class="detail-label">Billed To</div>
            <div class="detail-value">{{ $invoice->user->name ?? 'Club Member' }}</div>
            <div style="font-size: 12px; color: #64748b;">{{ $invoice->user->email ?? '' }}</div>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Item Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $invoice->title }}</strong>
                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Club membership & activity fees</div>
                </td>
                <td style="text-align: right; font-weight: 700;">£{{ number_format($invoice->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="total-box">
        <span class="total-label">Total Amount Paid:</span>
        <div class="total-amount">£{{ number_format($invoice->amount, 2) }}</div>
    </div>

    <div class="footer">
        Thank you for your support of {{ $club->name }}.<br>
        Generated via ClubManager Platform • All Rights Reserved
    </div>
</div>

</body>
</html>
