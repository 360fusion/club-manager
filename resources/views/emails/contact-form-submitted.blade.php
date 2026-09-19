<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Website Contact Form Inquiry</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f5f7; color: #333333; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid #e5e7eb;">
        <h2 style="color: #0f172a; margin-top: 0; font-size: 20px; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">
            📨 New Contact Form Inquiry
        </h2>

        <p style="font-size: 14px; color: #475569;">
            You have received a new message submitted via the <strong>{{ $club->name }}</strong> public website.
        </p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold; width: 120px; color: #64748b;">Name:</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: 600;">{{ $senderName }}</td>
            </tr>
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold; color: #64748b;">Email:</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #2563eb;">
                    <a href="mailto:{{ $senderEmail }}" style="color: #2563eb; text-decoration: none;">{{ $senderEmail }}</a>
                </td>
            </tr>
            @if(!empty($senderPhone))
            <tr>
                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-weight: bold; color: #64748b;">Phone:</td>
                <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; color: #0f172a;">{{ $senderPhone }}</td>
            </tr>
            @endif
        </table>

        <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px; border-radius: 4px; margin: 20px 0;">
            <p style="margin: 0 0 5px 0; font-weight: bold; color: #475569; font-size: 12px; text-transform: uppercase;">Message:</p>
            <p style="margin: 0; font-size: 14px; color: #1e293b; white-space: pre-wrap;">{{ $messageContent }}</p>
        </div>

        <p style="font-size: 12px; color: #94a3b8; margin-top: 30px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 15px;">
            You can reply directly to this email to respond to {{ $senderName }} ({{ $senderEmail }}).
        </p>
    </div>
</body>
</html>
