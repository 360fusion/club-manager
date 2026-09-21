<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Catering summary - {{ $event->title }}</title>
<style>
  @page { size: A4 portrait; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 12px; color: #111; margin: 0; padding: {{ $printable ? '24px' : '0' }}; }
  h1 { font-size: 18px; margin: 0 0 2px; }
  h2 { font-size: 13px; margin: 18px 0 6px; text-transform: capitalize; }
  .meta { color: #555; margin-bottom: 12px; }
  .totals td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: center; }
  .totals .n { font-size: 22px; font-weight: bold; display: block; }
  table { border-collapse: collapse; }
  table.list { width: 100%; }
  .list th { text-align: left; background: #f1f5f9; font-size: 10px; text-transform: uppercase; }
  .list th, .list td { border: 1px solid #cbd5e1; padding: 5px 8px; }
  .count { width: 60px; text-align: right; font-weight: bold; }
  .toolbar { margin-bottom: 14px; }
  .toolbar a, .toolbar button { font: inherit; padding: 6px 12px; border: 1px solid #94a3b8; background: #fff; border-radius: 6px; cursor: pointer; text-decoration: none; color: #111; margin-right: 6px; }
  @media print { .toolbar { display: none; } body { padding: 0; } }
</style>
</head>
<body>
@if ($printable)
  <div class="toolbar">
    <button onclick="window.print()">Print</button>
    <a href="{{ route('admin.events.catering', ['clubSlug' => $club->slug, 'id' => $event->id, 'download' => 1]) }}">Download PDF</a>
    <a href="{{ route('admin.events.subscribers', ['clubSlug' => $club->slug, 'id' => $event->id]) }}">Back</a>
  </div>
@endif

<h1>Catering summary: {{ $event->title }}</h1>
<div class="meta">{{ $club->name }} &middot; {{ $event->starts_at?->format('l j F Y, H:i') }}</div>

<table class="totals">
  <tr>
    <td><span class="n">{{ $summary['covers'] }}</span>dining</td>
    <td><span class="n">{{ $summary['not_dining'] }}</span>not dining</td>
    <td><span class="n">{{ $summary['total'] }}</span>in total</td>
  </tr>
</table>

@foreach ($summary['courses'] as $course => $dishes)
  @if (count($dishes))
    <h2>{{ $course }}s</h2>
    <table class="list">
      <thead><tr><th>Dish</th><th class="count">Portions</th></tr></thead>
      <tbody>
        @foreach ($dishes as $dish)
          <tr><td>{{ $dish['name'] }}</td><td class="count">{{ $dish['count'] }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif
@endforeach

@if (array_sum($summary['flags']) > 0)
  <h2>Dish types chosen</h2>
  <table class="list">
    <tbody>
      <tr><td>Vegetarian dishes</td><td class="count">{{ $summary['flags']['vegetarian'] }}</td></tr>
      <tr><td>Vegan dishes</td><td class="count">{{ $summary['flags']['vegan'] }}</td></tr>
      <tr><td>Gluten free dishes</td><td class="count">{{ $summary['flags']['gluten_free'] }}</td></tr>
    </tbody>
  </table>
@endif

@if (count($summary['dietary']))
  <h2>Dietary and allergy notes</h2>
  <table class="list">
    <thead><tr><th>Name</th><th>Notes</th></tr></thead>
    <tbody>
      @foreach ($summary['dietary'] as $note)
        <tr><td>{{ $note['name'] }}</td><td>{{ $note['notes'] }}</td></tr>
      @endforeach
    </tbody>
  </table>
@endif
</body>
</html>
