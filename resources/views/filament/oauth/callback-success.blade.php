<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('filament.integration.oauth.success_title') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .card { max-width: 520px; margin: 10vh auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,.06); padding: 24px; text-align: center; }
        .title { font-size: 18px; font-weight: 600; margin-bottom: 8px; }
        .desc { font-size: 14px; color: #475569; margin-bottom: 16px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; background: #2563eb; color: #fff; text-decoration: none; font-weight: 600; }
        .muted { font-size: 12px; color: #64748b; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">{{ __('filament.integration.oauth.success_heading') }}</div>
        <div class="desc">{{ __('filament.integration.oauth.success_message') }}</div>
        <a href="#" class="btn" onclick="window.close(); return false;">{{ __('filament.integration.oauth.close_window') }}</a>
        <div class="muted">{{ __('filament.integration.notifications.connected.title') }}</div>
    </div>

    <script>
        (function () {
            try {
                const payload = {
                    type: 'facebook-oauth-success',
                    pagesCount: {{ (int) ($pagesCount ?? 0) }},
                    integrationId: {{ (int) ($integrationId ?? 0) }},
                    lastSync: @json($lastSync ?? null),
                    redirectUrl: @json($redirectUrl ?? null),
                };
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, window.location.origin);
                }
            } catch (e) {}
            setTimeout(function () { try { window.close(); } catch (e) {} }, 1200);
        })();
    </script>
</body>
</html>
