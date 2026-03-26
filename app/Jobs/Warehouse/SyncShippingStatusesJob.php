<?php

namespace App\Jobs\Warehouse;

use App\Common\Constants\Order\OrderStatus;
use App\Models\Order;
use App\Services\Warehouse\ShippingStatusSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncShippingStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;

    public function __construct(public ?int $organizationId = null)
    {
    }

    public function handle(ShippingStatusSyncService $shippingStatusSyncService): void
    {
        if (!config('warehouse.features.shipping_sync_v1', true)) {
            return;
        }

        $query = Order::query()
            ->where('status', OrderStatus::SHIPPING->value)
            ->whereNotNull('ghn_order_code')
            ->latest('id')
            ->limit(200);

        if (!empty($this->organizationId)) {
            $query->where('organization_id', $this->organizationId);
        }

        $query->get()->each(function (Order $order) use ($shippingStatusSyncService) {
            $shippingStatusSyncService->syncOrderStatus($order);
        });
    }
}
