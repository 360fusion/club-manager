<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $newsletter->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Helvetica,Arial,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
        <tr><td style="background:{{ $colour }};padding:20px 28px;">
          @if ($logo)<img src="{{ $logo }}" alt="{{ $club->name }}" height="40" style="display:block;border:0;height:40px;margin-bottom:8px;">@endif
          <div style="color:#ffffff;font-size:20px;font-weight:bold;">{{ $club->name }}</div>
        </td></tr>

        @if ($isTest)
          <tr><td style="background:#fef3c7;color:#92400e;font-size:12px;padding:8px 28px;">This is a test. The unsubscribe link below does not work in a test.</td></tr>
        @endif

        <tr><td style="padding:28px;font-size:15px;line-height:1.6;">
          <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;">{{ $newsletter->subject }}</h1>
          {!! $body !!}

          @if (count($attachments))
            <div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;">
              <div style="font-size:12px;font-weight:bold;text-transform:uppercase;color:#64748b;margin-bottom:8px;">Downloads</div>
              @foreach ($attachments as $file)
                <a href="{{ $file['url'] }}" style="display:inline-block;margin:0 8px 8px 0;padding:8px 14px;background:{{ $colour }};color:#ffffff;text-decoration:none;border-radius:8px;font-size:13px;font-weight:bold;">{{ $file['name'] }}</a>
              @endforeach
            </div>
          @endif
        </td></tr>

        <tr><td style="background:#f8fafc;padding:18px 28px;font-size:12px;color:#64748b;line-height:1.5;">
          Sent by {{ $club->name }}.
          @if ($viewUrl)<a href="{{ $viewUrl }}" style="color:#64748b;">View in your browser</a>.@endif
          @if ($unsubscribeUrl && ! $web)<br>Don't want these emails? <a href="{{ $unsubscribeUrl }}" style="color:#64748b;">Unsubscribe</a>.@endif
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
