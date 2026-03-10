<?php

namespace App\Services;

use App\Common\Constants\Order\APIGHN;
use App\Core\ServiceReturn;
use App\Core\Logging;
use App\Repositories\ShippingConfigRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GHNService
{
    /**
     * Build GHN request with standard headers.
     */
    protected function request(APIGHN $endpoint)
    {
        $headers = [
            'Token' => $this->token,
            'Content-Type' => 'application/json',
        ];

        if ($endpoint->requiresShopId() && $this->shopId) {
            $headers['ShopId'] = $this->shopId;
        }

        return Http::withHeaders($headers);
    }
    protected ?string $token = null;
    protected ?int $shopId = null;

    /**
     * Initialize with organization ID
     */
    public function __construct(protected ShippingConfigRepository $shippingConfigRepository, ?int $organizationId = null)
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
        $config = $this->shippingConfigRepository->query()->where('organization_id', $organizationId)->first();

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
            ])->get(APIGHN::GET_SHOP_ALL->url());

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
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $response = Http::withHeaders([
            'Token' => $this->token,
            'ShopId' => $this->shopId,
            'Content-Type' => 'application/json',
        ])->post(APIGHN::CALCULATE_FEE->url(), $params);

        if (!$response->successful()) {
            Log::error('GHN Calculate Fee Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params
            ]);

            throw new \Exception(
                $response->json('message') ?? __('filament.shipping.api_request_failed')
            );
        }

        $data = $response->json();

        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        return $data['data'] ?? [];
    }

    public function createOrder(array $params): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $response = Http::withHeaders([
            'Token' => $this->token,
            'ShopId' => $this->shopId,
            'Content-Type' => 'application/json',
        ])->post(APIGHN::CREATE_ORDER->url(), $params);

        if (!$response->successful()) {
            Log::error('GHN Create Order Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params
            ]);

            throw new \Exception(
                $response->json('message') ?? __('filament.shipping.api_request_failed')
            );
        }

        $data = $response->json();
        
        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        return $data['data'] ?? [];
    }

    public function cancelOrder(string $orderCode): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $response = Http::withHeaders([
            'Token' => $this->token,
            'ShopId' => $this->shopId,
            'Content-Type' => 'application/json',
        ])->post(APIGHN::CANCEL_ORDER->url(), [
            'order_codes' => [$orderCode]
        ]);

        if (!$response->successful()) {
            Log::error('GHN Cancel Order Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception($response->json('message') ?? __('filament.shipping.api_request_failed'));
        }

        $data = $response->json();
        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        return $data['data'] ?? [];
    }

    /**
     * Update order (đổi địa chỉ, phí ship)
     */
    public function updateOrder(string $orderCode, array $params): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $params['order_code'] = $orderCode;

        $response = Http::withHeaders([
            'Token' => $this->token,
            'ShopId' => $this->shopId,
            'Content-Type' => 'application/json',
        ])->post(APIGHN::UPDATE_ORDER->url(), $params);

        if (!$response->successful()) {
            Log::error('GHN Update Order Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params
            ]);

            throw new \Exception(
                $response->json('message') ?? __('filament.shipping.api_request_failed')
            );
        }

        $data = $response->json();

        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        // API GHN trả về data: null khi update thành công
        // Theo tài liệu: {"code": 200, "message": "Success", "data": null}
        return $data['data'] ?? [];
    }

    /**
     * Update COD amount (đổi số tiền COD)
     */
    public function updateCOD(string $orderCode, float $codAmount): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $response = Http::withHeaders([
            'Token' => $this->token,
            'ShopId' => $this->shopId,
            'Content-Type' => 'application/json',
        ])->post(APIGHN::UPDATE_COD->url(), [
            'order_code' => $orderCode,
            'cod_amount' => $codAmount,
        ]);

        if (!$response->successful()) {
            Log::error('GHN Update COD Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_code' => $orderCode,
                'cod_amount' => $codAmount,
            ]);

            throw new \Exception(
                $response->json('message') ?? __('filament.shipping.api_request_failed')
            );
        }

        $data = $response->json();

        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        // API GHN trả về data: null khi update COD thành công
        // Theo tài liệu: {"code": 200, "message": "Success", "data": null}
        return $data['data'] ?? [];
    }

    /**
     * tìm kiếm danh sách đơn hàng
     */
    public function searchOrders(array $params = []): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $endpoint = APIGHN::SEARCH_ORDERS;
        $http = $this->request($endpoint);

        // API search có thể dùng GET hoặc POST, ưu tiên POST với body
        $response = $http->post($endpoint->url(), $params);

        if (!$response->successful()) {
            Logging::error('GHN Search Orders Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params,
                'url' => $endpoint->url(),
            ]);

            throw new \Exception(
                $response->json('message') ?? __('filament.shipping.api_request_failed')
            );
        }

        $data = $response->json();

        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        return $data['data'] ?? [];
    }

    /**
     * Get order detail (lấy chi tiết đơn hàng)
     */
    public function getOrderDetail(string $orderCode): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }

        $headers = [
            'Token' => $this->token,
            'Content-Type' => 'application/json',
        ];

        if (APIGHN::GET_ORDER_INFO->requiresShopId()) {
            $headers['ShopId'] = $this->shopId;
        }

        $url = APIGHN::GET_ORDER_INFO->url();
        $method = APIGHN::GET_ORDER_INFO->method();
        $params = ['order_code' => $orderCode];

        try {
            if ($method === 'GET') {
                $response = Http::withHeaders($headers)->get($url, $params);
            } else {
                $response = Http::withHeaders($headers)->post($url, $params);
            }

            if (!$response->successful()) {
                Logging::error('GHN Get Order Detail Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $url,
                    'method' => $method,
                    'order_code' => $orderCode,
                ]);

                throw new \Exception(
                    $response->json('message') ?? __('filament.shipping.api_request_failed')
                );
            }

            $data = $response->json();

            if (($data['code'] ?? 0) != 200) {
                throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
            }

            return $data['data'] ?? [];
        } catch (Throwable $e) {
            Logging::error('GHN Get Order Detail Exception', [
                'url' => $url,
                'method' => $method,
                'order_code' => $orderCode,
                'error' => $e->getMessage(),
            ], $e);
            throw $e;
        }
    }

    /**
     * Get cash log (lấy bảng đối soát)
     */
    public function getCashLog(array $params = []): array
    {
        if (!$this->token) {
            throw new \Exception(__('filament.shipping.token_required'));
        }
        $endpoint = APIGHN::GET_CASH_LOG;

        $http = $this->request($endpoint);
        $response = $endpoint->isGetRequest()
            ? $http->get($endpoint->url(), $params)
            : $http->post($endpoint->url(), $params);

        if (!$response->successful()) {
            Logging::error('GHN Get Cash Log Error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params,
                'url' => $endpoint->url(),
                'method' => $endpoint->method(),
            ]);

            throw new \Exception(
                $response->json('message') ?? __('filament.shipping.api_request_failed')
            );
        }

        $data = $response->json();

        if (($data['code'] ?? 0) != 200) {
            throw new \Exception($data['message'] ?? __('filament.shipping.unknown_error'));
        }

        return $data['data'] ?? [];
    }

    public function testConnection(array $data)
    {
        try {

            $token = $data['api_token'];

            // Call GHN API
            $response = Http::withHeaders([
                'Token' => $token,
                'Content-Type' => 'application/json',
            ])->get(APIGHN::GET_SHOP_ALL->url());

            if (!$response->successful()) {
                throw new \Exception(
                    __('filament.shipping.connection_failed') . ': ' . $response->json('message', __('messages.shipping.error.unknown'))
                );
            }

            $data = $response->json();

            if (!isset($data['code']) || $data['code'] != 200) {
                throw new \Exception(
                    __('filament.shipping.api_error') . ': ' . ($data['message'] ?? __('messages.shipping.error.unknown'))
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
