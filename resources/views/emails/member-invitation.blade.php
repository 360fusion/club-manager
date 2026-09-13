<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're invited to join {{ $club->name }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 560px; background-color: #ffffff; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);">
                    
                    <!-- Header Bar -->
                    <tr>
                        <td style="background-color: {{ $club->settings['primary_color'] ?? '#0369a1' }}; padding: 32px 32px 24px; text-align: center;">
                            @if(!empty($club->logo_url))
                                <img src="{{ $club->logo_url }}" alt="{{ $club->name }}" style="max-height: 48px; max-width: 180px; margin-bottom: 12px; display: inline-block;">
                            @else
                                <div style="display: inline-block; width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.2); color: #ffffff; font-size: 20px; font-weight: 900; line-height: 52px; text-align: center; text-transform: uppercase;">
                                    {{ strtoupper(substr($club->name, 0, 2)) }}
                                </div>
                            @endif
                            <h1 style="color: #ffffff; margin: 12px 0 4px; font-size: 22px; font-weight: 800; tracking-tight: -0.025em;">
                                {{ $club->name }}
                            </h1>
                            @if(!empty($club->settings['tagline']))
                                <p style="color: rgba(255,255,255,0.85); margin: 0; font-size: 13px; font-weight: 500;">
                                    {{ $club->settings['tagline'] }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Content Body -->
                    <tr>
                        <td style="padding: 36px 36px 28px;">
                            <h2 style="margin: 0 0 16px; font-size: 18px; font-weight: 800; color: #0f172a;">
                                Welcome, {{ $user->name }}! 👋
                            </h2>
                            @if(!empty($isExistingUser))
                                <p style="margin: 0 0 16px; font-size: 14px; line-height: 1.6; color: #475569;">
                                    You have been granted access to the official member portal for <strong>{{ $club->name }}</strong>.
                                </p>
                                <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #475569;">
                                    Since you already have a Club Manager account for <strong>{{ $user->email }}</strong>, click below to log in and access <strong>{{ $club->name }}</strong>.
                                </p>

                                <!-- CTA Button -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 28px 0 32px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $acceptUrl }}" target="_blank" style="display: inline-block; background-color: {{ $club->settings['primary_color'] ?? '#4f46e5' }}; color: #ffffff; font-size: 14px; font-weight: 800; text-decoration: none; padding: 14px 32px; border-radius: 14px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                                                Log In & Access {{ $club->name }} &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <p style="margin: 0 0 16px; font-size: 14px; line-height: 1.6; color: #475569;">
                                    You have been invited to join the official member portal for <strong>{{ $club->name }}</strong>.
                                </p>
                                <p style="margin: 0 0 24px; font-size: 14px; line-height: 1.6; color: #475569;">
                                    Click the button below to set up your account password, manage your roster details, view events, and access club communications.
                                </p>

                                <!-- CTA Button -->
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 28px 0 32px;">
                                    <tr>
                                        <td align="center">
                                            <a href="{{ $acceptUrl }}" target="_blank" style="display: inline-block; background-color: {{ $club->settings['primary_color'] ?? '#4f46e5' }}; color: #ffffff; font-size: 14px; font-weight: 800; text-decoration: none; padding: 14px 32px; border-radius: 14px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                                                Activate Account & Set Password &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif

                            <!-- Alternative Link -->
                            <div style="padding: 16px; background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 12px; color: #64748b; word-break: break-all;">
                                <p style="margin: 0 0 6px; font-weight: 700; color: #334155;">Having trouble with the button?</p>
                                <p style="margin: 0;">Copy and paste this URL into your browser:</p>
                                <a href="{{ $acceptUrl }}" style="color: #4f46e5; text-decoration: underline;">{{ $acceptUrl }}</a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 36px 32px; border-top: 1px solid #f1f5f9; text-align: center; font-size: 12px; color: #94a3b8;">
                            <p style="margin: 0 0 4px;">
                                Sent by <strong>{{ $club->name }}</strong>
                            </p>
                            @if(!empty($club->settings['email_footer_address']))
                                <p style="margin: 0;">{{ $club->settings['email_footer_address'] }}</p>
                            @endif
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
