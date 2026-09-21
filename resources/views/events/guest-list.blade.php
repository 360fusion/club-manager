<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Guest list - {{ $event->title }}</title>
<style>
  @page { size: A4 portrait; margin: 14mm; }
  * { box-sizing: border-box; }
  body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #111; margin: 0; padding: {{ $printable ? '24px' : '0' }}; }
  h1 { font-size: 18px; margin: 0 0 2px; }
  h2 { font-size: 13px; margin: 18px 0 6px; }
  .meta { color: #555; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
  th, td { border: 1px solid #cbd5e1; padding: 5px 6px; vertical-align: top; }
  tr { page-break-inside: avoid; }
  .guest { color: #92400e; font-size: 9px; font-weight: bold; }
  .of { color: #64748b; font-size: 9px; }
  .diet { color: #b91c1c; font-weight: bold; }
  .box { width: 14px; height: 14px; border: 1px solid #111; display: inline-block; }
  .toolbar { margin-bottom: 14px; }
  .toolbar a, .toolbar button { font: inherit; padding: 6px 12px; border: 1px solid #94a3b8; background: #fff; border-radius: 6px; cursor: pointer; text-decoration: none; color: #111; margin-right: 6px; }
  @media print { .toolbar { display: none; } body { padding: 0; } }
</style>
</head>
<body>
@if ($printable)
  <div class="toolbar">
    <button onclick="window.print()">Print</button>
    <a href="{{ route('admin.events.guest_list', ['clubSlug' => $club->slug, 'id' => $event->id, 'sort' => $sort === 'table' ? 'name' : 'table']) }}">Sort by {{ $sort === 'table' ? 'name' : 'table' }}</a>
    <a href="{{ route('admin.events.guest_list', ['clubSlug' => $club->slug, 'id' => $event->id, 'sort' => $sort, 'download' => 1]) }}">Download PDF</a>
    <a href="{{ route('admin.events.export', ['clubSlug' => $club->slug, 'id' => $event->id]) }}">Download CSV</a>
    <a href="{{ route('admin.events.subscribers', ['clubSlug' => $club->slug, 'id' => $event->id]) }}">Back</a>
  </div>
@endif

<h1>{{ $event->title }}</h1>
<div class="meta">
  {{ $club->name }} &middot; {{ $event->starts_at?->format('l j F Y, H:i') }}
  @if ($event->formatted_location) &middot; {{ $event->formatted_location }} @endif
  <br>{{ $people->count() }} {{ \Illuminate\Support\Str::plural('person', $people->count()) }}
  @if ($event->capacity) of {{ $event->capacity }} places @endif
  &middot; printed {{ now()->format('j M Y H:i') }}
</div>

<table>
  <thead>
    <tr>
      <th style="width:22px">#</th>
      <th>Name</th>
      @if ($sort === 'table' || $people->contains(fn ($p) => $p['table_label']))<th style="width:48px">Table</th>@endif
      @if ($event->has_dining)<th>Starter</th><th>Main</th><th>Dessert</th>@endif
      <th>Dietary</th>
      <th style="width:44px">Paid</th>
      <th style="width:34px">In</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($people as $person)
      <tr>
        <td>{{ $loop->iteration }}</td>
        <td>
          {{ $person['name'] }}
          @if ($person['is_guest'])<span class="guest">GUEST</span>@endif
          @if ($person['status'] === 'tentative')<span class="of">(maybe)</span>@endif
          @if ($person['guest_of'])<div class="of">of {{ $person['guest_of'] }}</div>@endif
          @if ($person['organisation'])<div class="of">{{ $person['organisation'] }}</div>@endif
        </td>
        @if ($sort === 'table' || $people->contains(fn ($p) => $p['table_label']))<td>{{ $person['table_label'] }}</td>@endif
        @if ($event->has_dining)
          @if ($person['dining'])
            <td>{{ $person['meal']['starter'] }}</td><td>{{ $person['meal']['main'] }}</td><td>{{ $person['meal']['dessert'] }}</td>
          @else
            <td colspan="3" class="of">Not dining</td>
          @endif
        @endif
        <td class="diet">{{ $person['dietary'] }}</td>
        <td>{{ $person['payment_status'] === 'paid' ? 'Paid' : ($person['payment_status'] === 'waived' ? 'Waived' : '-') }}</td>
        <td>@if ($person['checked_in'])&#10003;@else<span class="box"></span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="9">Nobody has booked yet.</td></tr>
    @endforelse
  </tbody>
</table>

@if ($waitlist->isNotEmpty())
  <h2>Waiting list</h2>
  <table>
    <tbody>
      @foreach ($waitlist as $person)
        <tr><td style="width:22px">{{ $loop->iteration }}</td><td>{{ $person['name'] }}@if ($person['guest_of']) <span class="of">of {{ $person['guest_of'] }}</span>@endif</td></tr>
      @endforeach
    </tbody>
  </table>
@endif
</body>
</html>
