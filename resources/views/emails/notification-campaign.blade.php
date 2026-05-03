<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $campaign->subject ?: $campaign->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f7fafc; color: #1f2937; padding: 24px;">
<div style="max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px;">
    <p style="font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: #10b981; margin: 0 0 12px;">
        {{ strtoupper($campaign->type) }}
    </p>
    <h1 style="font-size: 24px; margin: 0 0 16px;">{{ $campaign->title }}</h1>
    <div style="font-size: 15px; line-height: 1.7; white-space: pre-line;">{{ $campaign->body }}</div>

    @if($campaign->cta_url)
        <p style="margin-top: 24px;">
            <a href="{{ $campaign->cta_url }}" style="display: inline-block; background: #10b981; color: #ffffff; text-decoration: none; padding: 12px 18px; border-radius: 8px;">
                {{ $campaign->cta_label ?: 'Open' }}
            </a>
        </p>
    @endif

    <p style="margin-top: 32px; font-size: 13px; color: #6b7280;">
        Sent to {{ $user->email }} from Scanwell notifications.
    </p>
</div>
</body>
</html>
