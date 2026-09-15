<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.6;
        color: #1e293b;
        background-color: #f8fafc;
        margin: 0;
        padding: 24px;
    }
    .email-container {
        max-width: 600px;
        margin: 0 auto;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }
    .header {
        background: #0f172a;
        color: #ffffff;
        padding: 24px 32px;
        text-align: center;
    }
    .header h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 800;
        letter-spacing: 0.5px;
    }
    .header p {
        margin: 4px 0 0;
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .content {
        padding: 32px;
    }
    .body-text {
        font-size: 14px;
        color: #334155;
        white-space: pre-wrap;
        line-height: 1.6;
    }
    .meeting-card {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 20px;
        margin: 20px 0;
        font-size: 13px;
    }
    .meeting-card strong {
        color: #0f172a;
    }
    .attachment-notice {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
        padding: 12px 16px;
        font-size: 12px;
        color: #166534;
        font-weight: 600;
        margin-top: 20px;
    }
    .footer {
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        padding: 16px 32px;
        text-align: center;
        font-size: 11px;
        color: #64748b;
    }
</style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>{{ $club->name }}</h1>
            <p>Executive Committee & Board of General Purposes</p>
        </div>

        <div class="content">
            <div class="body-text">{{ $bodyContent }}</div>

            <div class="meeting-card">
                <div style="font-weight: bold; font-size: 14px; color: #0f172a; margin-bottom: 8px;">
                    📅 {{ $meeting->title }}
                </div>
                <div><strong>Date & Time:</strong> {{ $meeting->meeting_date ? $meeting->meeting_date->format('l, jS F Y \a\t H:i') : 'TBD' }}</div>
                <div><strong>Venue:</strong> {{ $meeting->location ?: 'Lodge Committee Room' }}</div>
                @if($meeting->chair)
                    <div><strong>Chair:</strong> {{ $meeting->chair->name }}</div>
                @endif
            </div>

            <div class="attachment-notice">
                📎 An official formatted copy of the Executive Agenda Pack (PDF) is attached to this email for your reference.
            </div>
        </div>

        <div class="footer">
            Sent via Club Manager • Masonic Lodge Governance & Administration
        </div>
    </div>
</body>
</html>
