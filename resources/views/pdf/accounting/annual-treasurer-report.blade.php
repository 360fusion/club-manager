<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Annual Treasurer's Report {{ $report['financial_year'] }} - {{ $report['club_name'] }}</title>
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
        .status-badge { display: inline-block; margin-top: 8px; padding: 3px 10px; border-radius: 9999px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .status-closed { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .status-open { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .section-title { font-size: 13px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals-row td { font-weight: 800; border-top: 2px solid #0f172a; border-bottom: none; }
        .muted { color: #64748b; font-size: 10px; }
        .footer { margin-top: 32px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px; }
        .signoff { margin-top: 24px; display: table; width: 100%; }
        .signoff-col { display: table-cell; width: 50%; padding-right: 16px; }
        .signoff-line { border-top: 1px solid #334155; margin-top: 32px; padding-top: 4px; font-size: 10px; }
        .signoff-mark { height: 60px; display: flex; align-items: flex-end; }
        .signoff-typed { font-family: 'DejaVu Sans', cursive; font-style: italic; font-size: 26px; color: #0f172a; }
        .signoff-image { max-height: 55px; max-width: 100%; }
        .audit-log-page { page-break-before: always; padding-top: 10px; }
        .audit-log-entry { margin-bottom: 20px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; }
        .audit-log-entry table td.label { width: 150px; color: #64748b; font-size: 10px; text-transform: uppercase; font-weight: 700; }
        .hash { font-family: monospace; word-break: break-all; font-size: 9px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="club-name">{{ $report['club_name'] }}</div>
        <div class="report-title">Annual Treasurer's Report — Financial Year {{ $report['financial_year'] }}</div>
        <span class="status-badge {{ $report['is_closed'] ? 'status-closed' : 'status-open' }}">
            {{ $report['is_closed'] ? 'Books Closed' : 'Books Open — Draft' }}
        </span>
    </div>

    <div class="section-title">General Fund — Receipts &amp; Payments</div>
    <table>
        <thead><tr><th>Code</th><th>Account</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @forelse($report['general_fund']['rows'] as $row)
                <tr>
                    <td>{{ $row['code'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ $cs }}{{ number_format($row['amount'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No General Fund activity this year.</td></tr>
            @endforelse
            <tr class="totals-row">
                <td colspan="2">Total Income</td>
                <td class="num">{{ $cs }}{{ number_format($report['general_fund']['total_income'], 2) }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="2">Total Expenditure</td>
                <td class="num">{{ $cs }}{{ number_format($report['general_fund']['total_expenditure'], 2) }}</td>
            </tr>
            <tr class="totals-row">
                <td colspan="2">Net Surplus / (Deficit)</td>
                <td class="num">{{ $cs }}{{ number_format($report['general_fund']['net_surplus'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Charity / Benevolent Fund</div>
    <table>
        <tbody>
            <tr><td>Alms, Raffle &amp; Donation Collections</td><td class="num">{{ $cs }}{{ number_format($report['charity_fund']['collections_total'], 2) }}</td></tr>
            <tr><td>Grants Disbursed</td><td class="num">({{ $cs }}{{ number_format($report['charity_fund']['grants_disbursed_total'], 2) }})</td></tr>
            <tr class="totals-row">
                <td>Net Charity Fund Movement</td>
                <td class="num">{{ $cs }}{{ number_format($report['charity_fund']['collections_total'] - $report['charity_fund']['grants_disbursed_total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Bank Reconciliation</div>
    <table>
        <thead><tr><th>Bank / Account</th><th class="num">Opening Balance</th><th class="num">Closing (Ledger)</th><th class="num">Closing (Statement)</th></tr></thead>
        <tbody>
            @forelse($report['bank_accounts'] as $b)
                <tr>
                    <td>{{ $b['bank_name'] }} — {{ $b['account_name'] }}</td>
                    <td class="num">{{ $cs }}{{ number_format($b['opening_balance'], 2) }}</td>
                    <td class="num">{{ $cs }}{{ number_format($b['closing_ledger_balance'], 2) }}</td>
                    <td class="num">{{ $cs }}{{ number_format($b['closing_statement_balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No bank accounts connected.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Membership — Arrears (Rule 181)</div>
    <table>
        <thead><tr><th>Member</th><th>Invoice Reference</th><th class="num">Balance Due</th></tr></thead>
        <tbody>
            @forelse($report['membership']['in_arrears'] as $m)
                <tr>
                    <td>{{ $m['member_name'] }}</td>
                    <td>{{ $m['invoice_reference'] }}</td>
                    <td class="num">{{ $cs }}{{ number_format($m['balance_due'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No members in arrears.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($report['vat'] && $report['vat']['enabled'])
        <div class="section-title">VAT Summary (Q{{ \Carbon\Carbon::parse($report['vat']['quarter_start'])->quarter }})</div>
        <table>
            <tbody>
                <tr><td>Output VAT</td><td class="num">{{ $cs }}{{ number_format($report['vat']['output_vat'], 2) }}</td></tr>
                <tr><td>Input VAT</td><td class="num">{{ $cs }}{{ number_format($report['vat']['input_vat'], 2) }}</td></tr>
                <tr class="totals-row"><td>Net VAT Due</td><td class="num">{{ $cs }}{{ number_format($report['vat']['net_vat_due'], 2) }}</td></tr>
            </tbody>
        </table>
    @endif

    @if($report['audit_sign_off'])
        <div class="section-title">Auditors' Sign-Off</div>
        @if($report['signatures'])
            <div class="signoff">
                <div class="signoff-col">
                    <div class="signoff-mark">
                        @if($report['signatures']['auditor_one']['method'] === 'typed')
                            <span class="signoff-typed">{{ $report['signatures']['auditor_one']['value'] }}</span>
                        @else
                            <img src="{{ $report['signatures']['auditor_one']['value'] }}" class="signoff-image" alt="Signature">
                        @endif
                    </div>
                    <div class="signoff-line">{{ $report['audit_sign_off']['auditor_one'] }} — {{ $report['audit_sign_off']['signed_off_at'] }}</div>
                </div>
                <div class="signoff-col">
                    <div class="signoff-mark">
                        @if($report['signatures']['auditor_two']['method'] === 'typed')
                            <span class="signoff-typed">{{ $report['signatures']['auditor_two']['value'] }}</span>
                        @else
                            <img src="{{ $report['signatures']['auditor_two']['value'] }}" class="signoff-image" alt="Signature">
                        @endif
                    </div>
                    <div class="signoff-line">{{ $report['audit_sign_off']['auditor_two'] }} — {{ $report['audit_sign_off']['signed_off_at'] }}</div>
                </div>
            </div>
            @if($report['audit_sign_off']['notes'])
                <p class="muted">{{ $report['audit_sign_off']['notes'] }}</p>
            @endif
        @else
            <p class="muted">Verified by {{ $report['audit_sign_off']['auditor_one'] }} and {{ $report['audit_sign_off']['auditor_two'] }} on {{ $report['audit_sign_off']['signed_off_at'] }}.
                @if($report['audit_sign_off']['notes']) {{ $report['audit_sign_off']['notes'] }} @endif
            </p>
        @endif
    @else
        <div class="signoff">
            <div class="signoff-col">
                <div class="signoff-line">Auditor Signature (1)</div>
            </div>
            <div class="signoff-col">
                <div class="signoff-line">Auditor Signature (2)</div>
            </div>
        </div>
    @endif

    <div class="footer">
        Prepared from the club's accounting records. This is a first pass at the annual return format used by UK Provincial
        Grand Lodges — check the exact layout and figures with your Provincial Secretary before submission.
    </div>
</div>

@if($report['audit_log'])
    <div class="container audit-log-page">
        <div class="header">
            <div class="club-name">{{ $report['club_name'] }}</div>
            <div class="report-title">Signature Audit Log</div>
        </div>
        @foreach($report['audit_log'] as $entry)
            <div class="audit-log-entry">
                <table>
                    <tr><td class="label">Signed by</td><td>{{ $entry['signer_name'] }}{{ $entry['signer_email'] ? ' ('.$entry['signer_email'].')' : '' }}</td></tr>
                    <tr><td class="label">Method</td><td>{{ $entry['method'] }}</td></tr>
                    <tr><td class="label">Signed on</td><td>{{ $entry['signed_at'] }}</td></tr>
                    <tr><td class="label">Requested by</td><td>{{ $entry['requested_by'] ?? '—' }} on {{ $entry['requested_at'] }}</td></tr>
                    <tr><td class="label">IP address</td><td>{{ $entry['ip'] }}</td></tr>
                    <tr><td class="label">Consent</td><td>The signer confirmed this was their electronic signature, intended to have the same effect as a handwritten signature.</td></tr>
                    <tr><td class="label">Document reference</td><td class="hash">{{ $entry['document_hash'] }}</td></tr>
                </table>
            </div>
        @endforeach
        <div class="footer">
            This page is generated by Club Manager as the audit trail for the electronic signatures above. It is appended automatically once both auditors have signed.
        </div>
    </div>
@endif
</body>
</html>
