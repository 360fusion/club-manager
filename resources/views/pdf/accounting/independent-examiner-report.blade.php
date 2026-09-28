<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Independent Examiner's Report {{ $report['financial_year'] }} - {{ $report['club_name'] }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; padding: 30px; color: #1e293b; background: #ffffff; font-size: 12px; line-height: 1.5; }
        .container { max-width: 720px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .club-name { font-size: 20px; font-weight: 900; color: #0f172a; text-transform: uppercase; }
        .report-title { font-size: 14px; font-weight: 700; color: #334155; margin-top: 4px; }
        .section-title { font-size: 13px; font-weight: 900; text-transform: uppercase; color: #0f172a; margin-top: 24px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals-row td { font-weight: 800; border-top: 2px solid #0f172a; border-bottom: none; }
        .muted { color: #64748b; font-size: 10px; }
        .footer { margin-top: 32px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 16px; }
        .signoff-mark { height: 60px; }
        .signoff-typed { font-family: 'DejaVu Sans', cursive; font-style: italic; font-size: 26px; color: #0f172a; }
        .signoff-image { max-height: 55px; max-width: 100%; }
        .signoff-line { border-top: 1px solid #334155; margin-top: 8px; padding-top: 4px; font-size: 10px; width: 50%; }
        .log-page { page-break-before: always; }
        .hash { font-family: monospace; word-break: break-all; font-size: 9px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="club-name">{{ $report['club_name'] }}</div>
        <div class="report-title">Independent Examiner's Report — Financial Year {{ $report['financial_year'] }}</div>
        @if($charity_number)<div class="muted">Registered charity number {{ $charity_number }}</div>@endif
    </div>

    <div class="section-title">Independent Examiner's Report to the Trustees</div>
    <p>I report on the accounts of the charity for the year ended {{ $report['financial_year'] }}, which are set out below.</p>

    <p><strong>Respective responsibilities of the trustees and the examiner.</strong> The charity's trustees are responsible for the preparation of the accounts. The charity's trustees consider that an audit is not required for this year under section 144 of the Charities Act 2011 (the Act) and that an independent examination is needed. It is my responsibility to examine the accounts under section 145 of the Act, to follow the procedures laid down in the general Directions given by the Charity Commission under section 145(5)(b) of the Act, and to state whether particular matters have come to my attention.</p>

    <p><strong>Basis of independent examiner's report.</strong> My examination was carried out in accordance with the general Directions given by the Charity Commission. An examination includes a review of the accounting records kept by the charity and a comparison of the accounts presented with those records. It also includes consideration of any unusual items or disclosures in the accounts, and seeking explanations from the trustees concerning any such matters. The procedures undertaken do not provide all the evidence that would be required in an audit, and consequently I do not express an audit opinion on the accounts.</p>

    <p><strong>Independent examiner's statement.</strong> In connection with my examination, no material matters have come to my attention which give me cause to believe that in any material respect the requirements to keep accounting records in accordance with section 130 of the Act, and to prepare accounts which accord with the accounting records and comply with the accounting requirements of the Act, have not been met, or to which, in my opinion, attention should be drawn in order to enable a proper understanding of the accounts to be reached.</p>

    @if($observations)
        <div class="section-title">Examiner's Notes</div>
        <p>{{ $observations }}</p>
    @endif

    <div class="section-title">Receipts &amp; Payments</div>
    <table>
        <thead><tr><th>Code</th><th>Account</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @forelse($report['general_fund']['rows'] as $row)
                <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="num">{{ $cs }}{{ number_format($row['amount'], 2) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">No General Fund activity this year.</td></tr>
            @endforelse
            <tr class="totals-row"><td colspan="2">Total Income</td><td class="num">{{ $cs }}{{ number_format($report['general_fund']['total_income'], 2) }}</td></tr>
            <tr class="totals-row"><td colspan="2">Total Expenditure</td><td class="num">{{ $cs }}{{ number_format($report['general_fund']['total_expenditure'], 2) }}</td></tr>
            <tr class="totals-row"><td colspan="2">Net Surplus / (Deficit)</td><td class="num">{{ $cs }}{{ number_format($report['general_fund']['net_surplus'], 2) }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">Charity / Benevolent Fund</div>
    <table>
        <tbody>
            <tr><td>Alms, Raffle &amp; Donation Collections</td><td class="num">{{ $cs }}{{ number_format($report['charity_fund']['collections_total'], 2) }}</td></tr>
            <tr><td>Grants Disbursed</td><td class="num">({{ $cs }}{{ number_format($report['charity_fund']['grants_disbursed_total'], 2) }})</td></tr>
            <tr class="totals-row"><td>Net Charity Fund Movement</td><td class="num">{{ $cs }}{{ number_format($report['charity_fund']['collections_total'] - $report['charity_fund']['grants_disbursed_total'], 2) }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">Independent Examiner</div>
    @if($signature)
        <div class="signoff-mark">
            @if($signature['method'] === 'typed')
                <span class="signoff-typed">{{ $signature['value'] }}</span>
            @else
                <img src="{{ $signature['value'] }}" class="signoff-image" alt="Signature">
            @endif
        </div>
        <div class="signoff-line">{{ $examiner_name }} — {{ $examined_at }}</div>
    @else
        <div class="signoff-mark"></div>
        <div class="signoff-line">{{ $examiner_name ?? 'Independent Examiner' }} — signature pending</div>
    @endif

    <div class="footer">
        A first-pass template built from the Charity Commission's published short-form wording. Check the wording, the examiner's
        eligibility and any thresholds with the Charity Commission or your Provincial Secretary before filing.
    </div>
</div>

@if(count($signature_log))
    <div class="container log-page">
        <div class="header"><div class="club-name">{{ $report['club_name'] }}</div><div class="report-title">Signature Audit Log</div></div>
        @foreach($signature_log as $entry)
            <table>
                <tr><td>Signed by</td><td>{{ $entry['signer_name'] }}{{ $entry['signer_email'] ? ' ('.$entry['signer_email'].')' : '' }}</td></tr>
                <tr><td>Method</td><td>{{ $entry['method'] }}</td></tr>
                <tr><td>Signed on</td><td>{{ $entry['signed_at'] }}</td></tr>
                <tr><td>IP address</td><td>{{ $entry['ip'] }}</td></tr>
                <tr><td>Consent</td><td>The signer confirmed this was their electronic signature, intended to have the same effect as a handwritten signature.</td></tr>
                <tr><td>Document reference</td><td class="hash">{{ $entry['document_hash'] }}</td></tr>
            </table>
        @endforeach
    </div>
@endif
</body>
</html>
