<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('filament.integration.oauth.error_title') }}</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; margin: 0; background: #fff1f2; color: #0f172a; }
        .card { max-width: 520px; margin: 10vh auto; background: #fff; border: 1px solid #fecdd3; border-radius: 12px; box-shadow: 0 10px 20px rgba(0,0,0,.06); padding: 24px; text-align: center; }
        .title { font-size: 18px; font-weight: 600; margin-bottom: 8px; color: #dc2626; }
        .desc { font-size: 14px; color: #7f1d1d; margin-bottom: 16px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; background: #dc2626; color: #fff; text-decoration: none; font-weight: 600; }
        .muted { font-size: 12px; color: #7f1d1d; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="title">{{ __('filament.integration.oauth.error_heading') }}</div>
        <div class="desc">{{ $error ?? __('filament.integration.oauth.error_message') }}</div>
        <a href="#" class="btn" onclick="window.close(); return false;">{{ __('filament.integration.oauth.close_window') }}</a>
    </div>

    <script>
        (function () {
            try {
                const payload = {
                    type: 'facebook-oauth-error',
                    message: @json($error ?? __('filament.integration.oauth.unknown_error')),
                };
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, window.location.origin);
                }
            } catch (e) {}
        })();
    </script>
</body>
</html>
