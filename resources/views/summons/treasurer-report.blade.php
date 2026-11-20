@php($fmt = fn ($amount) => \App\Support\Currencies::format($amount, $club))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Treasurer's report - {{ $meeting->title }} - {{ $club->name }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; background: #ffffff; font-size: 13px; line-height: 1.45; margin: 0; padding: 24px; }
        .sheet { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        td.amount { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: 700; border-bottom: 0; border-top: 1px solid #cbd5e1; }
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
        </div>
    @endunless

    <h1>Treasurer's report: {{ $meeting->title }}</h1>
    <div class="muted">{{ $club->name }} · {{ $meeting->meeting_date->format('l j F Y') }}</div>
    <p class="muted">Since {{ $previous ? 'the last meeting on '.$previous->meeting_date->format('j F Y') : 'the start of the financial year, '.$from->format('j F Y') }}, up to {{ $to->format('j F Y') }}. Printed {{ now($club->timezone())->format('j M Y, H:i') }}.</p>

    <h2>Money held</h2>
    <table>
        @foreach ($money as $account)
            <tr><td>{{ $account['name'] }}</td><td class="amount">{{ $fmt($account['balance']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total</td><td class="amount">{{ $fmt($money_total) }}</td></tr>
    </table>

    <h2>Money in</h2>
    <table>
        @forelse ($income as $line)
            <tr><td>{{ $line['name'] }}</td><td class="amount">{{ $fmt($line['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">Nothing received.</td><td></td></tr>
        @endforelse
        <tr class="total"><td>Total in</td><td class="amount">{{ $fmt($income_total) }}</td></tr>
    </table>

    <h2>Money out</h2>
    <table>
        @forelse ($spending as $line)
            <tr><td>{{ $line['name'] }}</td><td class="amount">{{ $fmt($line['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">Nothing paid out.</td><td></td></tr>
        @endforelse
        <tr class="total"><td>Total out</td><td class="amount">{{ $fmt($spending_total) }}</td></tr>
    </table>

    <h2>Dues still owed</h2>
    <p>{{ $dues['members'] === 0 ? 'Every member is paid up.' : $fmt($dues['total']).' owed by '.$dues['members'].' '.\Illuminate\Support\Str::plural('member', $dues['members']).'.' }}</p>

    <h2>Charity collected</h2>
    <table>
        @forelse ($charity as $line)
            <tr><td>{{ $line['type'] }}</td><td class="amount">{{ $fmt($line['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">No collections recorded.</td><td></td></tr>
        @endforelse
    </table>

    <h2>Bills waiting for approval</h2>
    <table>
        @forelse ($bills as $bill)
            <tr><td>{{ $bill['vendor'] }}@if ($bill['due']) <span class="muted">· due {{ $bill['due'] }}</span>@endif</td><td class="amount">{{ $fmt($bill['amount']) }}</td></tr>
        @empty
            <tr><td class="muted">None.</td><td></td></tr>
        @endforelse
    </table>
</div>
@unless ($pdf)
    <script>document.getElementById('print-sheet').addEventListener('click', () => window.print());</script>
@endunless
</body>
</html>
