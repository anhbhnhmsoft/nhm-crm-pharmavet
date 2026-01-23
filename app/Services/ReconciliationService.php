<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Core\Logging;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Repositories\OrderRepository;
use App\Repositories\ReconciliationRepository;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\ShippingConfigRepository;
use App\Services\GHNService;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ReconciliationService
{
    public function __construct(
        protected ReconciliationRepository $reconciliationRepository,
        protected OrderRepository $orderRepository,
        protected ExchangeRateRepository $exchangeRateRepository,
        protected ShippingConfigRepository $shippingConfigRepository,
        protected GHNService $ghnService,
    ) {}

    /**
     * Đồng bộ đối soát từ GHN
     * Sử dụng API search để lấy danh sách đơn hàng, sau đó gọi detail để lấy thông tin chi tiết
     */
    public function syncReconciliationFromGHN(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            // Load config GHN và init service
            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $organizationId)
                ->first();

            if (!$config) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            // Kiểm tra config có đủ thông tin không
            if (empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_incomplete'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            // Gọi API search để lấy danh sách đơn hàng
            $offset = 0;
            $limit = 50;
            $created = 0;
            $updated = 0;

            do {
                $orders = $this->ghnService->searchOrders([
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'offset' => $offset,
                    'limit' => $limit,
                ]);

                if (isset($orders['orders']) && is_array($orders['orders'])) {
                    $orderList = $orders['orders'];
                } elseif (isset($orders['data']) && is_array($orders['data'])) {
                    $orderList = $orders['data'];
                } elseif (is_array($orders) && !empty($orders) && (isset($orders[0]) || array_is_list($orders))) {
                    $orderList = $orders;
                } else {
                    $orderList = [];
                }
                
                foreach ($orderList as $orderData) {
                    $ghnOrderCode = $orderData['order_code'] ?? null;
                    if (!$ghnOrderCode) {
                        continue;
                    }

                    $order = $this->orderRepository->query()
                        ->where('ghn_order_code', $ghnOrderCode)
                        ->first();

                    $reconciliation = $this->reconciliationRepository->query()
                        ->where('organization_id', $organizationId)
                        ->where('ghn_order_code', $ghnOrderCode)
                        ->first();

                    // Gọi API detail để lấy thông tin chi tiết
                    try {
                        $orderDetail = $this->ghnService->getOrderDetail($ghnOrderCode);
                        
                        $fee = $orderDetail['fee'] ?? [];
                        $codAmount = $orderDetail['cod_amount'] ?? $orderData['cod_amount'] ?? 0;
                        $shippingFee = $fee['main_service'] ?? $orderData['total_fee'] ?? 0;
                        $storageFee = $fee['station_do'] ?? 0;
                        $totalFee = ($fee['main_service'] ?? 0) + ($fee['cod_fee'] ?? 0) + ($fee['station_do'] ?? 0) + ($fee['insurance'] ?? 0);

                        $reconciliationData = [
                            'organization_id' => $organizationId,
                            'reconciliation_date' => $orderDetail['created_date'] ?? $orderData['created_date'] ?? now()->toDateString(),
                            'order_id' => $order?->id,
                            'ghn_order_code' => $ghnOrderCode,
                            'cod_amount' => $codAmount,
                            'shipping_fee' => $shippingFee,
                            'storage_fee' => $storageFee,
                            'total_fee' => $totalFee,
                        ];

                        if ($reconciliation) {
                            if ($reconciliation->status === ReconciliationStatus::CONFIRMED->value 
                                || $reconciliation->status === ReconciliationStatus::CANCELLED->value) {
                            } else {
                                $reconciliationData['status'] = $reconciliation->status ?? ReconciliationStatus::PENDING->value;
                            }
                            $reconciliation->update($reconciliationData);
                            $updated++;
                        } else {
                            // Tạo mới thì set status = PENDING
                            $reconciliationData['status'] = ReconciliationStatus::PENDING->value;
                            $reconciliationData['created_by'] = Auth::id();
                            $this->reconciliationRepository->create($reconciliationData);
                            $created++;
                        }
                    } catch (Throwable $e) {
                        Logging::error('Failed to get order detail for reconciliation', [
                            'ghn_order_code' => $ghnOrderCode,
                            'error' => $e->getMessage(),
                        ]);
                            $this->reconciliationRepository->create([
                                'organization_id' => $organizationId,
                                'reconciliation_date' => $orderData['created_date'] ?? now()->toDateString(),
                                'order_id' => $order?->id,
                                'ghn_order_code' => $ghnOrderCode,
                                'cod_amount' => $orderData['cod_amount'] ?? 0,
                                'shipping_fee' => $orderData['total_fee'] ?? 0,
                                'storage_fee' => 0,
                                'total_fee' => $orderData['total_fee'] ?? 0,
                                'status' => ReconciliationStatus::PENDING->value,
                                'created_by' => Auth::id(),
                            ]);
                            $created++;
                    }
                }

                $offset += $limit;
                $total = $orders['total'] ?? count($orderList);
                $hasMore = count($orderList) >= $limit && ($offset < $total);
            } while ($hasMore);

            return ServiceReturn::success(
                data: ['created' => $created, 'updated' => $updated],
                message: __('accounting.reconciliation.synced', ['count' => $created + $updated])
            );
        } catch (Throwable $e) {
            Logging::error('Sync reconciliation from GHN error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.reconciliation.sync_failed'));
        }
    }

    /**
     * Lấy chi tiết đơn hàng từ GHN
     */
    public function getOrderDetailFromGHN(int $reconciliationId): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            if (empty($reconciliation->ghn_order_code)) {
                return ServiceReturn::error(__('accounting.reconciliation.no_ghn_order_code'));
            }

            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $reconciliation->organization_id)
                ->first();

            if (!$config || empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            // Gọi API detail để lấy thông tin chi tiết
            $orderDetail = $this->ghnService->getOrderDetail($reconciliation->ghn_order_code);

            return ServiceReturn::success(
                data: $orderDetail,
                message: __('accounting.reconciliation.detail_loaded')
            );
        } catch (Throwable $e) {
            Logging::error('Get order detail from GHN error', [
                'reconciliation_id' => $reconciliationId,
                'error' => $e->getMessage(),
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.detail_load_failed') . ': ' . $e->getMessage(), $e);
        }
    }

    /**
     * Đồng bộ chi tiết đơn hàng từ GHN
     */
    public function syncOrderDetailFromGHN(int $reconciliationId): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            if (empty($reconciliation->ghn_order_code)) {
                return ServiceReturn::error(__('accounting.reconciliation.no_ghn_order_code'));
            }

            // Load config GHN
            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $reconciliation->organization_id)
                ->first();

            if (!$config || empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            // Gọi API detail để lấy thông tin chi tiết
            $orderDetail = $this->ghnService->getOrderDetail($reconciliation->ghn_order_code);

            $fee = $orderDetail['fee'] ?? [];
            $codAmount = $orderDetail['cod_amount'] ?? $reconciliation->cod_amount;
            $shippingFee = $fee['main_service'] ?? $reconciliation->shipping_fee;
            $storageFee = $fee['station_do'] ?? $reconciliation->storage_fee;
            $totalFee = ($fee['main_service'] ?? 0) + ($fee['cod_fee'] ?? 0) + ($fee['station_do'] ?? 0) + ($fee['insurance'] ?? 0);

            $reconciliation->update([
                'cod_amount' => $codAmount,
                'shipping_fee' => $shippingFee,
                'storage_fee' => $storageFee,
                'total_fee' => $totalFee,
                'reconciliation_date' => $orderDetail['created_date'] ?? $reconciliation->reconciliation_date,
            ]);

            return ServiceReturn::success(
                data: $reconciliation,
                message: __('accounting.reconciliation.detail_synced')
            );
        } catch (Throwable $e) {
            Logging::error('Sync order detail from GHN error', [
                'reconciliation_id' => $reconciliationId,
                'error' => $e->getMessage(),
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.detail_sync_failed') . ': ' . $e->getMessage(), $e);
        }
    }

    /**
     * Cập nhật đơn hàng trên GHN
     */
    public function updateOrderOnGHN(int $reconciliationId, array $updateData): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            if (empty($reconciliation->ghn_order_code)) {
                return ServiceReturn::error(__('accounting.reconciliation.no_ghn_order_code'));
            }

            $config = $this->shippingConfigRepository->query()
                ->where('organization_id', $reconciliation->organization_id)
                ->first();

            if (!$config || empty($config->api_token) || empty($config->default_store_id)) {
                return ServiceReturn::error(__('accounting.reconciliation.config_not_found'));
            }

            $this->ghnService->setToken($config->api_token)->setShopId($config->default_store_id);

            $updatedFields = [];

            // Cập nhật COD nếu có
            if (array_key_exists('cod_amount', $updateData)) {
                try {
                    Logging::web('Updating COD amount on GHN', [
                        'reconciliation_id' => $reconciliationId,
                        'ghn_order_code' => $reconciliation->ghn_order_code,
                        'cod_amount' => $updateData['cod_amount'],
                    ]);
                    // API GHN có thể trả về data: null khi thành công, nhưng vẫn là success
                    $result = $this->ghnService->updateCOD($reconciliation->ghn_order_code, $updateData['cod_amount']);
                    $updatedFields['cod_amount'] = $updateData['cod_amount'];
                } catch (Throwable $e) {
                    Logging::error('Failed to update COD on GHN', [
                        'reconciliation_id' => $reconciliationId,
                        'ghn_order_code' => $reconciliation->ghn_order_code,
                        'cod_amount' => $updateData['cod_amount'],
                        'error' => $e->getMessage(),
                    ], $e);
                    throw $e;
                }
            }

            // Các trường thông tin đơn hàng cần cập nhật qua API
            $orderUpdateFields = [
                'to_name',
                'to_phone',
                'to_address',
                'to_ward_code',
                'to_district_id',
                'payment_type_id',
                'weight',
                'length',
                'width',
                'height',
                'note',
                'content',
                'required_note',
            ];

            $orderFieldsToUpdate = array_intersect_key($updateData, array_flip($orderUpdateFields));

            if (!empty($orderFieldsToUpdate)) {
                try {
                    Logging::web('Updating order details on GHN', [
                        'reconciliation_id' => $reconciliationId,
                        'ghn_order_code' => $reconciliation->ghn_order_code,
                        'fields' => array_keys($orderFieldsToUpdate),
                    ]);
                    // API GHN có thể trả về data: null khi thành công, nhưng vẫn là success
                    $result = $this->ghnService->updateOrder($reconciliation->ghn_order_code, $orderFieldsToUpdate);
                    $updatedFields = array_merge($updatedFields, $orderFieldsToUpdate);
                } catch (Throwable $e) {
                    Logging::error('Failed to update order on GHN', [
                        'reconciliation_id' => $reconciliationId,
                        'ghn_order_code' => $reconciliation->ghn_order_code,
                        'fields' => array_keys($orderFieldsToUpdate),
                        'error' => $e->getMessage(),
                    ], $e);
                    throw $e;
                }
            }

            // Sau khi update GHN, lấy lại detail để sync về reconciliation
            try {
                $orderDetail = $this->ghnService->getOrderDetail($reconciliation->ghn_order_code);
            } catch (Throwable $e) {
                Logging::error('Failed to get order detail after update', [
                    'reconciliation_id' => $reconciliationId,
                    'ghn_order_code' => $reconciliation->ghn_order_code,
                    'error' => $e->getMessage(),
                ], $e);
                throw $e;
            }
            $fee = $orderDetail['fee'] ?? [];
            $codAmount = $orderDetail['cod_amount'] ?? $reconciliation->cod_amount;
            $shippingFee = $fee['main_service'] ?? $reconciliation->shipping_fee;
            $storageFee = $fee['station_do'] ?? $reconciliation->storage_fee;
            $totalFee = ($fee['main_service'] ?? 0) + ($fee['cod_fee'] ?? 0) + ($fee['station_do'] ?? 0) + ($fee['insurance'] ?? 0);

            $reconciliation->update([
                'cod_amount' => $codAmount,
                'shipping_fee' => $shippingFee,
                'storage_fee' => $storageFee,
                'total_fee' => $totalFee,
            ]);

            return ServiceReturn::success(
                data: ['reconciliation' => $reconciliation, 'updated_fields' => $updatedFields],
                message: __('accounting.reconciliation.order_updated')
            );
        } catch (Throwable $e) {
            Logging::error('Update order on GHN error', [
                'reconciliation_id' => $reconciliationId,
                'update_data' => $updateData,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], $e);
            return ServiceReturn::error(__('accounting.reconciliation.order_update_failed'));
        }
    }

    /**
     * Xác nhận đối soát
     */
    public function confirmReconciliation(int $reconciliationId): ServiceReturn
    {
        try {
            $reconciliation = $this->reconciliationRepository->find($reconciliationId);

            if (!$reconciliation) {
                return ServiceReturn::error(__('accounting.reconciliation.not_found'));
            }

            $reconciliation->update([
                'status' => ReconciliationStatus::CONFIRMED->value,
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            return ServiceReturn::success(data: $reconciliation, message: __('accounting.reconciliation.confirmed'));
        } catch (Throwable $e) {
            Logging::error('Confirm reconciliation error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.reconciliation.confirm_failed'));
        }
    }
}

