<?php

namespace App\Services\Marketing;

use App\Jobs\Marketing\DispatchFacebookCapiEventJob;
use App\Models\FacebookEventLog;
use App\Repositories\FacebookEventLogRepository;
use App\Repositories\IntegrationRepository;
use App\Repositories\IntegrationTokenRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MarketingConversionService
{
    public function __construct(
        protected FacebookEventLogRepository $facebookEventLogRepository,
        protected IntegrationRepository $integrationRepository,
        protected IntegrationTokenRepository $integrationTokenRepository,
    ) {
    }

    public function queueEvent(array $data): FacebookEventLog
    {
        $integration = $this->integrationRepository->find((int) $data['integration_id']);

        $payload = [
            'event_name' => (string) $data['event_name'],
            'event_time' => (int) ($data['event_time'] ?? now()->timestamp),
            'event_source_url' => (string) ($data['event_source_url'] ?? ''),
            'action_source' => (string) ($data['action_source'] ?? 'website'),
            'user_data' => (array) ($data['user_data'] ?? []),
            'custom_data' => (array) ($data['custom_data'] ?? []),
        ];

        $eventLog = $this->facebookEventLogRepository->create([
            'organization_id' => (int) ($integration->organization_id ?? 0),
            'integration_id' => (int) ($integration->id ?? 0),
            'entity_id' => null,
            'event_name' => (string) $payload['event_name'],
            'event_id' => (string) ($data['event_id'] ?? Str::uuid()),
            'payload_json' => $payload,
            'hashed_payload_json' => $this->buildHashedPayload($payload),
            'status' => 'pending',
            'retry_count' => 0,
            'next_retry_at' => now(),
            'processed_at' => null,
        ]);

        DispatchFacebookCapiEventJob::dispatch((int) $eventLog->id)->onQueue('marketing_capi');

        return $eventLog;
    }

    public function dispatchEvent(FacebookEventLog $eventLog): bool
    {
        $integration = $this->integrationRepository->find((int) $eventLog->integration_id);
        if (!$integration) {
            $eventLog->update([
                'status' => 'failed',
                'last_error' => 'Integration not found',
            ]);
            return false;
        }

        $pixelId = (string) Arr::get($integration->config ?? [], 'pixel_id', '');
        if ($pixelId === '') {
            $eventLog->update([
                'status' => 'failed',
                'last_error' => 'Pixel id missing',
            ]);
            return false;
        }

        $token = $this->integrationTokenRepository->getUserLongLivedToken((int) $integration->id);
        if (!$token) {
            $eventLog->update([
                'status' => 'failed',
                'last_error' => 'Access token missing',
            ]);
            return false;
        }

        $hashed = (array) $eventLog->hashed_payload_json;
        $payload = [
            'data' => [[
                'event_name' => $hashed['event_name'] ?? $eventLog->event_name,
                'event_time' => $hashed['event_time'] ?? now()->timestamp,
                'event_id' => $eventLog->event_id,
                'event_source_url' => $hashed['event_source_url'] ?? '',
                'action_source' => $hashed['action_source'] ?? 'website',
                'user_data' => $hashed['user_data'] ?? [],
                'custom_data' => $hashed['custom_data'] ?? [],
            ]],
        ];

        $url = 'https://graph.facebook.com/v24.0/' . $pixelId . '/events';
        $response = Http::asJson()->post($url . '?access_token=' . $token->token, $payload);

        if ($response->successful()) {
            $eventLog->update([
                'status' => 'processed',
                'processed_at' => now(),
                'last_error' => null,
            ]);
            return true;
        }

        $this->markRetry($eventLog, (string) $response->body());
        return false;
    }

    public function markRetry(FacebookEventLog $eventLog, string $error): void
    {
        $retryCount = (int) $eventLog->retry_count + 1;
        $maxRetry = (int) config('marketing.facebook.retry_max', 5);
        $backoff = config('marketing.facebook.retry_backoff_seconds', [60, 120, 300, 600, 900]);
        $delay = (int) ($backoff[min($retryCount - 1, count($backoff) - 1)] ?? 900);

        $eventLog->update([
            'retry_count' => $retryCount,
            'last_error' => $error,
            'status' => $retryCount >= $maxRetry ? 'failed' : 'retrying',
            'next_retry_at' => $retryCount >= $maxRetry ? null : now()->addSeconds($delay),
        ]);
    }

    protected function buildHashedPayload(array $payload): array
    {
        $userData = (array) ($payload['user_data'] ?? []);

        $hashedUserData = [];
        foreach ($userData as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $normalized = strtolower(trim((string) $value));
            $hashedUserData[$key] = hash('sha256', $normalized);
        }

        return [
            'event_name' => $payload['event_name'] ?? '',
            'event_time' => $payload['event_time'] ?? now()->timestamp,
            'event_source_url' => $payload['event_source_url'] ?? '',
            'action_source' => $payload['action_source'] ?? 'website',
            'user_data' => $hashedUserData,
            'custom_data' => $payload['custom_data'] ?? [],
        ];
    }
}
