<?php

namespace App\Common\Constants\Order;

enum APIGHN: string
{
    // Shop Management
    case GET_SHOP_ALL = '/shiip/public-api/v2/shop/all';

    // Master Data
    case GET_PROVINCE = '/shiip/public-api/master-data/province';
    case GET_DISTRICT = '/shiip/public-api/master-data/district';
    case GET_WARD = '/shiip/public-api/master-data/ward';

    // Shipping Services
    case GET_SERVICE = '/shiip/public-api/v2/shipping-order/available-services';
    case CALCULATE_FEE = '/shiip/public-api/v2/shipping-order/fee';
    case CALCULATE_EXPECTED_DELIVERY = '/shiip/public-api/v2/shipping-order/leadtime';

    // Order Management
    case CREATE_ORDER = '/shiip/public-api/v2/shipping-order/create';
    case UPDATE_ORDER = '/shiip/public-api/v2/shipping-order/update';
    case UPDATE_COD = '/shiip/public-api/v2/shipping-order/updateCOD';
    case CANCEL_ORDER = '/shiip/public-api/v2/switch-status/cancel';
    case GET_ORDER_INFO = '/shiip/public-api/v2/shipping-order/detail';
    case GET_ORDER_STATUS = '/shiip/public-api/v2/shipping-order/detail-by-client-order-code';
    case SEARCH_ORDERS = '/shiip/public-api/v2/shipping-order/search';

    // Finance
    case GET_CASH_LOG = '/shiip/public-api/v2/finance/cash-log';

    // Print
    case PRINT_ORDER = '/shiip/public-api/v2/a5/gen-token';

    /**
     * Get full URL
     */
    public function url(): string
    {
        $baseUrl = rtrim(config('services.ghn.api_base_url', 'https://dev-online-gateway.ghn.vn'), '/');

        return $baseUrl . $this->value;
    }


    /**
     * Check if this is a GET request endpoint
     */
    public function isGetRequest(): bool
    {
        return in_array($this, [
            self::GET_SHOP_ALL,
            self::GET_PROVINCE,
            self::GET_DISTRICT,
            self::GET_WARD,
            self::GET_SERVICE,
            self::GET_ORDER_INFO,
            self::GET_ORDER_STATUS,
        ]);
    }

    /**
     * Check if this is a POST request endpoint
     */
    public function isPostRequest(): bool
    {
        return !$this->isGetRequest();
    }

    /**
     * Get HTTP method
     */
    public function method(): string
    {
        return $this->isGetRequest() ? 'GET' : 'POST';
    }


    /**
     * Get required headers
     */
    public function requiredHeaders(): array
    {
        return [
            'Token' => 'API Token',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get optional headers for specific endpoints
     */
    public function optionalHeaders(): array
    {
        return match ($this) {
            self::CREATE_ORDER,
            self::UPDATE_ORDER,
            self::UPDATE_COD,
            self::CALCULATE_FEE,
            self::GET_SERVICE,
            self::GET_CASH_LOG,
            self::SEARCH_ORDERS => [
                'ShopId' => 'Shop ID (optional, will use default if not provided)',
            ],
            default => [],
        };
    }

    /**
     * Check if requires shop ID
     */
    public function requiresShopId(): bool
    {
        return in_array($this, [
            self::CREATE_ORDER,
            self::UPDATE_ORDER,
            self::UPDATE_COD,
            self::CALCULATE_FEE,
            self::GET_SERVICE,
            self::GET_CASH_LOG,
            self::SEARCH_ORDERS,
            self::GET_ORDER_INFO,
        ]);
    }
}
