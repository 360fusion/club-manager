<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Email preferences</title>
</head>
<body style="margin:0;background:#f1f5f9;font-family:Helvetica,Arial,sans-serif;color:#0f172a;">
  <div style="max-width:480px;margin:48px auto;padding:0 16px;">
    <div style="background:#ffffff;border-radius:12px;padding:28px;">
      <div style="font-size:13px;color:#64748b;margin-bottom:8px;">{{ $club->name }}</div>

      @if (! empty($resubscribed))
        <h1 style="font-size:20px;margin:0 0 12px;">You are subscribed again</h1>
        <p style="line-height:1.5;">You will receive {{ $channel?->name ?? 'these emails' }} again.</p>
      @elseif ($done)
        <h1 style="font-size:20px;margin:0 0 12px;">You have been unsubscribed</h1>
        <p style="line-height:1.5;">You will no longer receive <strong>{{ $channel->name }}</strong> from {{ $club->name }}.</p>
        <form method="POST" action="{{ route('newsletters.unsubscribe', ['token' => $delivery->token]) }}" style="margin-top:16px;">
          @csrf
          <input type="hidden" name="resubscribe" value="1">
          <button type="submit" style="background:none;border:0;padding:0;color:#4f46e5;text-decoration:underline;font-size:14px;cursor:pointer;">Unsubscribed by mistake? Subscribe again</button>
        </form>
      @elseif ($canOptOut)
        <h1 style="font-size:20px;margin:0 0 12px;">Unsubscribe</h1>
        <p style="line-height:1.5;">Stop receiving <strong>{{ $channel->name }}</strong> from {{ $club->name }} at <strong>{{ $delivery->email }}</strong>?</p>
        <form method="POST" action="{{ route('newsletters.unsubscribe', ['token' => $delivery->token]) }}" style="margin-top:16px;">
          @csrf
          <button type="submit" style="background:#4f46e5;color:#ffffff;border:0;border-radius:8px;padding:10px 18px;font-size:14px;font-weight:bold;cursor:pointer;">Yes, unsubscribe me</button>
        </form>
      @else
        <h1 style="font-size:20px;margin:0 0 12px;">Official lodge notices</h1>
        <p style="line-height:1.5;">This is an official notice from {{ $club->name }}, so it cannot be switched off from here. You can manage your other email preferences after logging in to your member area.</p>
      @endif
    </div>
  </div>
</body>
</html>
