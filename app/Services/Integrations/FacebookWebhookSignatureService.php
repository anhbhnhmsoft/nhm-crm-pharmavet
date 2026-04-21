<?php

namespace App\Services\Integrations;

class FacebookWebhookSignatureService
{
    public function isValid(?string $signatureHeader, string $payload): bool
    {
        if (!$signatureHeader) {
            return true;
        }

        $secret = (string) config('services.facebook.webhook_app_secret', '');
        if ($secret === '') {
            return false;
        }

        if (!str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
