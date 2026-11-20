<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>{{ $item->title }}</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Helvetica,Arial,sans-serif;color:#0f172a;">
  <div style="max-width:560px;margin:32px auto;padding:0 16px;">
    @if ($unsubscribed)
      <div style="background:#ecfdf5;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:14px;">You will not get {{ mb_strtolower($kindLabel) }} by email any more. You can switch it back on below.</div>
    @elseif ($saved)
      <div style="background:#ecfdf5;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:14px;">Your choices are saved.</div>
    @endif

    <div style="background:#ffffff;border-radius:12px;padding:24px;">
      <div style="font-size:13px;color:#64748b;margin-bottom:6px;">{{ $kindLabel }}</div>
      <h1 style="font-size:21px;margin:0 0 6px;">{{ $item->title }}</h1>
      @if ($item->cancelled_at)
        <p style="background:#fef3c7;border-radius:8px;padding:10px;font-size:14px;margin:0 0 12px;">This meeting has been cancelled.</p>
      @endif
      <p style="font-size:13px;color:#475569;margin:0 0 16px;">{{ $item->passed_on_by }}</p>

      @if ($item->meeting_date)
        <p style="background:#f1f5f9;border-radius:8px;padding:12px;font-size:14px;line-height:1.5;">
          <strong>{{ $item->meeting_date->format('l j F Y') }}@if ($item->meeting_time), {{ $item->meeting_time }}@endif</strong>
          @if ($item->venue)<br>{{ $item->venue }}@endif
          @if ($item->dress_code)<br>Dress: {{ $item->dress_code }}@endif
          @if ($item->dining)
            @if (! empty($item->dining['theme']))<br>Dining: {{ $item->dining['theme'] }}@endif
            @if (! empty($item->dining['menu']))<br>{!! nl2br(e($item->dining['menu'])) !!}@endif
            @if (! empty($item->dining['guest_cost']))<br>Guests: {{ $item->dining['guest_cost'] }}@endif
          @endif
        </p>
      @endif

      @if ($item->body)
        <div style="font-size:15px;line-height:1.55;">{!! $item->body !!}</div>
      @endif

      @if ($item->attachment_path)
        <p style="margin:20px 0 0;"><a href="{{ route('passed_on.public.attachment', ['token' => $copy->token]) }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;padding:12px 18px;font-size:15px;font-weight:bold;">Open the PDF</a></p>
      @endif
    </div>

    <div id="choices" style="background:#ffffff;border-radius:12px;padding:24px;margin-top:16px;">
      <h2 style="font-size:17px;margin:0 0 4px;">What you get by email</h2>
      <p style="font-size:13px;color:#64748b;margin:0 0 14px;">From your lodge's Secretary, as well as your own lodge's summonses.</p>
      <form method="POST" action="{{ route('passed_on.choices', ['token' => $copy->token]) }}">
        @foreach ($labels as $kind => $label)
          <label style="display:flex;align-items:center;gap:10px;min-height:44px;font-size:15px;">
            <input type="checkbox" name="email[]" value="{{ $kind }}" @checked(in_array($kind, $emailOn, true)) style="width:20px;height:20px;">
            {{ $label }}
          </label>
        @endforeach
        @if ($item->lodge)
          <label style="display:flex;align-items:center;gap:10px;min-height:44px;font-size:15px;border-top:1px solid #e2e8f0;margin-top:8px;padding-top:8px;">
            <input type="hidden" name="mute" value="0">
            <input type="checkbox" name="mute" value="1" @checked($muted) style="width:20px;height:20px;">
            Nothing more about {{ $item->lodge->displayName() }}
          </label>
        @endif
        <button type="submit" style="margin-top:14px;background:#2563eb;color:#ffffff;border:0;border-radius:8px;padding:12px 18px;font-size:15px;font-weight:bold;cursor:pointer;">Save my choices</button>
      </form>
    </div>
  </div>
</body>
</html>
