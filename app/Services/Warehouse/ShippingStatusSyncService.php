<?php

namespace App\Services\Warehouse;

use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Core\ServiceReturn;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusLogRepository;
use App\Services\GHNService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ShippingStatusSyncService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderStatusLogRepository $orderStatusLogRepository,
        protected GHNService $ghnService,
        protected InventoryMovementService $inventoryMovementService,
    ) {
    }

    public function handleWebhook(array $payload): ServiceReturn
    {
        $token = config('warehouse.shipping.webhook_token');
        if (!empty($token) && ($payload['token'] ?? null) !== $token) {
            return ServiceReturn::error('Unauthorized webhook');
        }

        $orderCode = (string) (Arr::get($payload, 'order_code') ?? Arr::get($payload, 'OrderCode') ?? '');
        $status = (string) (Arr::get($payload, 'status') ?? Arr::get($payload, 'Status') ?? '');

        if ($orderCode === '' || $status === '') {
            return ServiceReturn::error('Missing order_code or status');
        }

        $order = $this->orderRepository->query()
            ->where(function ($query) use ($orderCode) {
                $query->where('ghn_order_code', $orderCode)
                    ->orWhere('code', $orderCode);
            })
            ->latest('id')
            ->first();

        if (!$order) {
            return ServiceReturn::error('Order not found');
        }

        $this->applyShippingStatus($order, $status, $payload);

        return ServiceReturn::success();
    }

    public function syncOrderStatus(Order $order): ServiceReturn
    {
        try {
            if (empty($order->ghn_order_code)) {
                return ServiceReturn::error(__('order.table.not_posted'));
            }

            $detail = $this->ghnService->getOrderDetail($order->ghn_order_code);
            $status = (string) ($detail['status'] ?? '');
            if ($status === '') {
                return ServiceReturn::error('GHN status empty');
            }

            $this->applyShippingStatus($order, $status, $detail);

            return ServiceReturn::success();
        } catch (\Throwable $exception) {
            return ServiceReturn::error($exception->getMessage());
        }
    }

    public function mapGhnToOrderStatus(string $ghnStatus): ?int
    {
        return match ($ghnStatus) {
            GhnOrderStatus::READY_TO_PICK->value,
            GhnOrderStatus::PICKING->value,
            GhnOrderStatus::PICKED->value,
            GhnOrderStatus::STORING->value,
            GhnOrderStatus::TRANSPORTING->value,
            GhnOrderStatus::SORTING->value,
            GhnOrderStatus::DELIVERING->value,
            GhnOrderStatus::MONEY_COLLECT_PICKING->value,
            GhnOrderStatus::MONEY_COLLECT_DELIVERING->value => OrderStatus::SHIPPING->value,

            GhnOrderStatus::DELIVERED->value => OrderStatus::COMPLETED->value,

            GhnOrderStatus::CANCEL->value,
            GhnOrderStatus::DELIVERY_FAIL->value,
            GhnOrderStatus::WAITING_TO_RETURN->value,
            GhnOrderStatus::RETURN->value,
            GhnOrderStatus::RETURN_TRANSPORTING->value,
            GhnOrderStatus::RETURN_SORTING->value,
            GhnOrderStatus::RETURNING->value,
            GhnOrderStatus::RETURN_FAIL->value,
            GhnOrderStatus::RETURNED->value,
            GhnOrderStatus::EXCEPTION->value,
            GhnOrderStatus::DAMAGE->value,
            GhnOrderStatus::LOST->value => OrderStatus::CANCELLED->value,

            default => null,
        };
    }

    protected function applyShippingStatus(Order $order, string $ghnStatus, array $payload): void
    {
        DB::transaction(function () use ($order, $ghnStatus, $payload) {
            $oldStatus = (int) $order->status;
            $newStatus = $this->mapGhnToOrderStatus($ghnStatus) ?? $oldStatus;

            $order->update([
                'status' => $newStatus,
                'ghn_status' => $ghnStatus,
                'ghn_response' => json_encode($payload),
            ]);

            if ($oldStatus !== $newStatus) {
                $this->orderStatusLogRepository->create([
                    'order_id' => $order->id,
                    'user_id' => auth()->id(),
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'note' => 'shipping_sync:' . $ghnStatus,
                ]);
            }
        });
    }
}
