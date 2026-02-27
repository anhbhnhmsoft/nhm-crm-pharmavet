<?php

namespace App\Services;

use App\Core\Logging;
use App\Models\ExchangeRate;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\OrganizationRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ExchangeRateService
{
    private string $apiBaseUrl;
    private ?string $apiKey;

    public function __construct(
        protected ExchangeRateRepository $exchangeRateRepository,
        protected OrganizationRepository $organizationRepository,
    ) {
        $this->apiBaseUrl = config('services.exchangerate.base_url', 'https://v6.exchangerate-api.com/v6');
        $this->apiKey = config('services.exchangerate.api_key');
    }

    /**
     * Đảm bảo có tỷ giá USD -> VND cho organization theo ngày đối soát.
     * API hiện tại chỉ lấy latest, nên hệ thống sẽ lưu latest vào đúng rate_date được truyền vào.
     */
    public function getOrSyncUsdVndRate(int $organizationId, string $rateDate): ?ExchangeRate
    {
        $normalizedDate = Carbon::parse($rateDate)->toDateString();

        $existingRate = $this->exchangeRateRepository->query()
            ->where('organization_id', $organizationId)
            ->whereDate('rate_date', $normalizedDate)
            ->where('from_currency', 'USD')
            ->where('to_currency', 'VND')
            ->first();

        if ($existingRate) {
            return $existingRate;
        }

        $latestRate = $this->fetchLatestUsdToVndRate();

        if ($latestRate === null) {
            return null;
        }

        return $this->exchangeRateRepository->query()->updateOrCreate(
            [
                'organization_id' => $organizationId,
                'rate_date' => $normalizedDate,
                'from_currency' => 'USD',
                'to_currency' => 'VND',
            ],
            [
                'rate' => $latestRate,
                'source' => 'api',
                'note' => 'Auto synced from ExchangeRate-API latest/USD',
            ]
        );
    }

    /**
     * Đồng bộ tỷ giá USD -> VND cho tất cả tổ chức foreign theo 1 ngày cụ thể.
     */
    public function syncForAllForeignOrganizations(?string $date = null): int
    {
        $rateDate = Carbon::parse($date ?? now())->toDateString();
        $syncedCount = 0;

        $this->organizationRepository->query()
            ->where('is_foreign', true)
            ->select('id')
            ->chunkById(100, function ($organizations) use ($rateDate, &$syncedCount) {
                foreach ($organizations as $organization) {
                    $rate = $this->getOrSyncUsdVndRate((int) $organization->id, $rateDate);
                    if ($rate) {
                        $syncedCount++;
                    }
                }
            });

        return $syncedCount;
    }

    private function fetchLatestUsdToVndRate(): ?float
    {
        $apiKey = $this->apiKey;

        if (empty($apiKey)) {
            Logging::error('ExchangeRate API key is missing');
            return null;
        }

        $cacheKey = 'exchange_rate_api.latest.usd_vnd';

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($apiKey) {
            try {
                $response = Http::retry(3, 500)
                    ->timeout(10)
                    ->acceptJson()
                    ->get($this->apiBaseUrl . '/' . $apiKey . '/latest/USD');

                if (!$response->successful()) {
                    Logging::error('ExchangeRate API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $data = $response->json();
                $rate = $data['conversion_rates']['VND'] ?? null;

                if (!is_numeric($rate) || (float) $rate <= 0) {
                    Logging::error('ExchangeRate API returned invalid VND rate', [
                        'payload' => $data,
                    ]);

                    return null;
                }

                return (float) $rate;
            }catch (Throwable $e) {
                Logging::error('ExchangeRate API exception', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }
}
