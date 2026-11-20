@php($fmt = fn ($amount) => $amount === null ? 'Rate not set' : \App\Support\Currencies::format($amount, $club))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Annual return of members {{ $year }} - {{ $club->name }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; background: #ffffff; font-size: 13px; line-height: 1.45; margin: 0; padding: 24px; }
        .sheet { max-width: 760px; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        td, th { text-align: left; padding: 4px 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
        td.amount, th.amount { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: 700; border-bottom: 0; border-top: 1px solid #cbd5e1; }
        .warning { border: 1px solid #fcd34d; background: #fffbeb; border-radius: 12px; padding: 8px 12px; margin-top: 12px; }
        .actions { margin-bottom: 16px; }
        .actions a, .actions button { font: inherit; font-size: 13px; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; color: #1e293b; text-decoration: none; cursor: pointer; margin-right: 6px; }
        @media print { .actions { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
<div class="sheet">
    @unless ($pdf)
        <div class="actions">
            <button type="button" id="print-sheet">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['download' => 1]) }}">Download PDF</a>
            <a href="{{ request()->fullUrlWithQuery(['year' => $year - 1, 'download' => null]) }}">Year {{ $year - 1 }}</a>
            @if ($year < $currentYear)
                <a href="{{ request()->fullUrlWithQuery(['year' => $year + 1, 'download' => null]) }}">Year {{ $year + 1 }}</a>
            @endif
        </div>
    @endunless

    <h1>Annual return of members {{ $year }}</h1>
    <div class="muted">{{ $club->name }}@if ($province_name) · {{ $province_name }}@endif @if ($grand_lodge_name) · {{ $grand_lodge_name }}@endif</div>
    <p class="muted">Financial year {{ $start->format('j F Y') }} to {{ $end->format('j F Y') }}. Printed {{ now($club->timezone())->format('j M Y, H:i') }}.</p>

    <h2>Membership</h2>
    <table>
        <tr><td>Members at the start of the year</td><td class="amount">{{ $counts['at_start'] }}</td></tr>
        <tr><td>Initiated</td><td class="amount">+ {{ $counts['initiated'] }}</td></tr>
        <tr><td>Joined from another lodge</td><td class="amount">+ {{ $counts['joined'] }}</td></tr>
        <tr><td>Resigned, excluded or died</td><td class="amount">&minus; {{ $counts['left'] }}</td></tr>
        <tr class="total"><td>Members at the end of the year</td><td class="amount">{{ $counts['at_end'] }}</td></tr>
        <tr><td class="muted">of whom subscribing</td><td class="amount muted">{{ $counts['subscribing'] }}</td></tr>
        <tr><td class="muted">of whom honorary (no dues)</td><td class="amount muted">{{ $counts['honorary'] }}</td></tr>
    </table>

    @if ($left_undated !== [])
        <div class="warning">No leaving date is recorded for {{ implode(', ', $left_undated) }}, so they are not counted above. Add it on their member record.</div>
    @endif

    <h2>Dues for {{ $counts['subscribing'] }} subscribing {{ \Illuminate\Support\Str::plural('member', $counts['subscribing']) }}</h2>
    <table>
        <tr><th>Due to</th><th class="amount">Per member</th><th class="amount">Total</th></tr>
        <tr><td>Grand Lodge</td><td class="amount">{{ $fmt($rates['grand_lodge']) }}</td><td class="amount">{{ $fmt($dues['grand_lodge']) }}</td></tr>
        <tr><td>Province</td><td class="amount">{{ $fmt($rates['province']) }}</td><td class="amount">{{ $fmt($dues['province']) }}</td></tr>
        <tr><td>Festival contribution</td><td class="amount">{{ $fmt($rates['festival']) }}</td><td class="amount">{{ $fmt($dues['festival']) }}</td></tr>
        <tr class="total"><td>Total</td><td></td><td class="amount">{{ $fmt($dues_total) }}</td></tr>
    </table>
    @if (in_array(null, $rates, true))
        <p class="muted">Where a rate is not set, ask your Province for the figure; the platform's administrators can add it.</p>
    @endif

    @foreach (['Initiated' => $initiated, 'Joined from another lodge' => $joined] as $heading => $rows)
        @if ($rows !== [])
            <h2>{{ $heading }}</h2>
            <table>
                <tr><th>Name</th><th>Grand Lodge number</th><th>Date</th></tr>
                @foreach ($rows as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['number'] }}</td><td>{{ $row['date'] }}</td></tr>
                @endforeach
            </table>
        @endif
    @endforeach

    @if ($leavers !== [])
        <h2>Resigned, excluded or died</h2>
        <table>
            <tr><th>Name</th><th>Grand Lodge number</th><th>Reason</th><th>Date</th></tr>
            @foreach ($leavers as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['number'] }}</td><td>{{ $row['reason'] }}</td><td>{{ $row['date'] }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2>Subscribing members at the end of the year</h2>
    <table>
        <tr><th>Name</th><th>Grand Lodge number</th><th>Member since</th></tr>
        @forelse ($subscribing_members as $row)
            <tr><td>{{ $row['name'] }}</td><td>{{ $row['number'] }}</td><td>{{ $row['date'] ?? 'Not recorded' }}</td></tr>
        @empty
            <tr><td class="muted" colspan="3">None.</td></tr>
        @endforelse
    </table>

    @if ($honorary_members !== [])
        <h2>Honorary members</h2>
        <table>
            <tr><th>Name</th><th>Grand Lodge number</th><th>Member since</th></tr>
            @foreach ($honorary_members as $row)
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['number'] }}</td><td>{{ $row['date'] ?? 'Not recorded' }}</td></tr>
            @endforeach
        </table>
    @endif
</div>
@unless ($pdf)
    <script>document.getElementById('print-sheet').addEventListener('click', () => window.print());</script>
@endunless
</body>
</html>
