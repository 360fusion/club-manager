<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $item->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f5f7; color: #333333; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; padding: 30px; border: 1px solid #e5e7eb;">
        <p style="margin: 0 0 4px; font-size: 12px; color: #64748b;">{{ $kindLabel }}</p>
        <h2 style="color: #0f172a; margin-top: 0; font-size: 20px; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">{{ $item->title }}</h2>
        <p style="font-size: 13px; color: #475569;">{{ $item->passed_on_by }}</p>

        @if ($item->meeting_date)
            <p style="font-size: 14px; color: #1e293b; background: #f1f5f9; padding: 10px; border-radius: 6px;">
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
            <div style="font-size: 14px; color: #1e293b;">{!! $item->body !!}</div>
        @endif

        <p style="margin: 24px 0 0;">
            <a href="{{ $openUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: bold;">{{ $item->attachment_path ? 'Open it and the PDF' : 'Open it' }}</a>
        </p>

        <p style="margin: 24px 0 0; font-size: 12px; color: #64748b;">
            You get this because you chose to hear about {{ mb_strtolower($kindLabel) }}. <a href="{{ $choicesUrl }}" style="color: #64748b;">Change what you get</a>
        </p>
    </div>
</body>
</html>
