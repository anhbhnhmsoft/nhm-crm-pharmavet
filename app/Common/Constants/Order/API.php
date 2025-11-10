<?php

namespace App\Common\Constants\Order;

enum API: string
{
    // Shop Management
    case GET_SHOP_ALL = 'online-gateway.ghn.vn/shiip/public-api/v2/shop/all'; // production

        // Master Data
    case GET_PROVINCE = 'online-gateway.ghn.vn/shiip/public-api/master-data/province';
    case GET_DISTRICT = 'online-gateway.ghn.vn/shiip/public-api/master-data/district';
    case GET_WARD = 'online-gateway.ghn.vn/shiip/public-api/master-data/ward';

        // Shipping Services
    case GET_SERVICE = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/available-services';
    case CALCULATE_FEE = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/fee';
    case CALCULATE_EXPECTED_DELIVERY = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/leadtime';

        // Order Management
    case CREATE_ORDER = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create';
    case UPDATE_ORDER = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/update';
    case CANCEL_ORDER = 'online-gateway.ghn.vn/shiip/public-api/v2/switch-status/cancel';
    case GET_ORDER_INFO = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail';
    case GET_ORDER_STATUS = 'online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail-by-client-order-code';

        // Print
    case PRINT_ORDER = 'online-gateway.ghn.vn/shiip/public-api/v2/a5/gen-token';

    /**
     * Get full URL
     */
    public function url(): string
    {
        return 'https://' . $this->value;
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
     * Get endpoint description
     */
    public function description(): string
    {
        return match ($this) {
            self::GET_SHOP_ALL => 'Lấy danh sách cửa hàng',
            self::GET_PROVINCE => 'Lấy danh sách tỉnh/thành phố',
            self::GET_DISTRICT => 'Lấy danh sách quận/huyện',
            self::GET_WARD => 'Lấy danh sách phường/xã',
            self::GET_SERVICE => 'Lấy danh sách dịch vụ vận chuyển',
            self::CALCULATE_FEE => 'Tính phí vận chuyển',
            self::CALCULATE_EXPECTED_DELIVERY => 'Tính thời gian giao hàng dự kiến',
            self::CREATE_ORDER => 'Tạo đơn hàng vận chuyển',
            self::UPDATE_ORDER => 'Cập nhật đơn hàng',
            self::CANCEL_ORDER => 'Hủy đơn hàng',
            self::GET_ORDER_INFO => 'Lấy thông tin đơn hàng',
            self::GET_ORDER_STATUS => 'Lấy trạng thái đơn hàng',
            self::PRINT_ORDER => 'In phiếu giao hàng',
        };
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
            self::CALCULATE_FEE,
            self::GET_SERVICE => [
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
            self::CALCULATE_FEE,
            self::GET_SERVICE,
        ]);
    }

    /**
     * Get all endpoints as options
     */
    public static function toOptions(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->description();
        }
        return $options;
    }
}
