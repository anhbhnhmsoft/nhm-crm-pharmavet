<?php

namespace App\Services;

use App\Common\Constants\Order\API;
use App\Core\ServiceReturn;
use App\Models\ShippingConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GHNService
{
    protected ?string $token = null;
    protected ?int $shopId = null;

    /**
     * Initialize with organization ID
     */
    public function __construct(?int $organizationId = null)
    {
        if ($organizationId) {
            $this->loadConfig($organizationId);
        }
    }

    /**
     * Load config from database
     */
    protected function loadConfig(int $organizationId): void
    {
        $config = ShippingConfig::where('organization_id', $organizationId)->first();

        if ($config) {
            $this->token = $config->api_token;
            $this->shopId = $config->default_store_id;
        }
    }

    /**
     * Set token manually
     */
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * Set shop ID manually
     */
    public function setShopId(int $shopId): self
    {
        $this->shopId = $shopId;
        return $this;
    }

    /**
     * Get all shops
     *
     * @return array
     * @throws \Exception
     */
    public function getShops(): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $cacheKey = "ghn_shops_{$this->token}";

        // Cache for 1 hour
        return Cache::remember($cacheKey, 3600, function () {
            $response = Http::withHeaders([
                'Token' => $this->token,
                'Content-Type' => 'application/json',
            ])->get('https://' . API::GET_SHOP_ALL->value);

            if (!$response->successful()) {
                Log::error('GHN API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception(
                    __('filament.shipping.api_request_failed', [
                        'status' => $response->status()
                    ])
                );
            }

            $data = $response->json();

            if (!isset($data['code']) || $data['code'] != 200) {
                throw new \Exception(
                    $data['message'] ?? __('filament.shipping.unknown_error')
                );
            }

            return $data['data']['shops'] ?? [];
        });
    }

    /**
     * Get shop details by ID
     *
     * @param int $shopId
     * @return array|null
     */
    public function getShopById(int $shopId): ?array
    {
        try {
            $shops = $this->getShops();

            foreach ($shops as $shop) {
                if ($shop['_id'] == $shopId) {
                    return $shop;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get shop by ID', [
                'shop_id' => $shopId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate token
     *
     * @return bool
     */
    public function validateToken(): bool
    {
        try {
            $shops = $this->getShops();
            return !empty($shops);
        } catch (\Exception $e) {
            Log::warning('Token validation failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getProvinces(): array
    {
        // TODO: Implement province API
        // https://online-gateway.ghn.vn/shiip/public-api/master-data/province
        return [];
    }

    public function getDistricts(int $provinceId): array
    {
        // TODO: Implement district API
        // https://online-gateway.ghn.vn/shiip/public-api/master-data/district
        return [];
    }

    public function getWards(int $districtId): array
    {
        // TODO: Implement ward API
        // https://online-gateway.ghn.vn/shiip/public-api/master-data/ward
        return [];
    }

    public function calculateFee(array $params): array
    {
        // TODO: Implement fee calculation API
        // https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee
        return [];
    }

    public function createOrder(array $params): array
    {
        // TODO: Implement order creation API
        // https://online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create
        return [];
    }

    public function testConnection(array $data)
    {
        try {

            $token = $data['api_token'];

            // Call GHN API
            $response = Http::withHeaders([
                'Token' => $token,
                'Content-Type' => 'application/json',
            ])->get('https://' . API::GET_SHOP_ALL->value);

            if (!$response->successful()) {
                throw new \Exception(
                    __('filament.shipping.connection_failed') . ': ' . $response->json('message', 'Unknown error')
                );
            }

            $data = $response->json();

            if (!isset($data['code']) || $data['code'] != 200) {
                throw new \Exception(
                    __('filament.shipping.api_error') . ': ' . ($data['message'] ?? 'Unknown error')
                );
            }

            if (empty($data['data']['shops'])) {
                throw new \Exception(__('filament.shipping.no_shops_found'));
            }

            return ServiceReturn::success($data['data']);
        } catch (Throwable $thr) {
            Log::error($thr->getMessage());
            return ServiceReturn::error($thr->getMessage());
        }
    }
}
