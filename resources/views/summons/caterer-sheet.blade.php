<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Caterer sheet - {{ $meeting->title }} - {{ $club->name }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1e293b; background: #ffffff; font-size: 13px; line-height: 1.45; margin: 0; padding: 24px; }
        .sheet { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0; }
        h2 { font-size: 14px; margin: 20px 0 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .muted { color: #64748b; }
        .count { font-size: 28px; font-weight: 700; margin: 12px 0 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
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

    <h1>{{ $club->name }}: {{ $meeting->title }}</h1>
    <div class="muted">{{ $meeting->meeting_date->format('l j F Y') }}@if ($meeting->starts_at), meeting at {{ $meeting->starts_at }}@endif @if ($meeting->venue)· {{ $meeting->venue }}@endif</div>

    <p class="count">{{ $numbers['dining'] }} dining</p>
    <div class="muted">{{ $numbers['dining_members'] }} members and visitors, {{ $numbers['dining_guests'] }} {{ \Illuminate\Support\Str::plural('guest', $numbers['dining_guests']) }}. Printed {{ now($club->timezone())->format('j M Y, H:i') }}.</div>

    @if ($meeting->festive_board_menu)
        <h2>Menu</h2>
        <div>{!! nl2br(e($meeting->festive_board_menu)) !!}</div>
    @endif

    <h2>Dietary needs</h2>
    @forelse ($numbers['dietary'] as $need)
        <div><strong>{{ $need['person'] }}</strong>: {{ $need['requirement'] }}</div>
    @empty
        <div class="muted">None given.</div>
    @endforelse

    <h2>Who is dining</h2>
    <table>
        <thead><tr><th>Name</th><th>Guest of</th><th>Dietary needs</th></tr></thead>
        <tbody>
        @foreach ($diners as $diner)
            <tr><td>{{ $diner['name'] }}</td><td>{{ $diner['host'] }}</td><td>{{ $diner['dietary'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@unless ($pdf)
    <script>document.getElementById('print-sheet').addEventListener('click', () => window.print());</script>
@endunless
</body>
</html>
