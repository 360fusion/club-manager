<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $heading }}</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f5f7; color: #333333; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; padding: 30px; border: 1px solid #e5e7eb;">
        <h2 style="color: #0f172a; margin-top: 0; font-size: 20px; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">{{ $heading }}</h2>

        <p style="font-size: 14px; color: #1e293b; white-space: pre-wrap;">{{ $body }}</p>

        <p style="margin: 24px 0 0;">
            <a href="{{ $actionUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: bold;">{{ $actionLabel }}</a>
        </p>
    </div>
</body>
</html>
