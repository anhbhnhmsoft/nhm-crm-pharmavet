<?php

namespace App\Repositories;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Warehouse\StatusTicket;
use App\Common\Constants\Warehouse\TypeTicket;
use App\Core\BaseRepository;
use App\Models\InventoryTicket;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class DiscrepancyReportRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new Order();
    }

    /**
     * Lấy dữ liệu đối soát chênh lệch
     */
    public function getDiscrepancyData(int $organizationId, string $startDate, string $endDate)
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->with([
                'items',
                'reconciliation',
                'createdBy:id,name',
                'inventoryTickets.details',
            ])
            ->get()
            ->map(function (Order $order) {
                $systemRevenue = $this->resolveSystemValue($order);
                $warehouseRevenue = $this->resolveWarehouseValue($order);
                $actualPayment = $this->resolveActualPayment($order);

                return [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'sale_name' => $order->createdBy?->name ?? 'N/A',
                    'created_at' => $order->created_at->toDateTimeString(),
                    'system_revenue' => $systemRevenue,
                    'warehouse_revenue' => $warehouseRevenue,
                    'actual_payment' => $actualPayment,
                    'is_discrepant' => $this->valuesDifferent($systemRevenue, $warehouseRevenue)
                        || $this->valuesDifferent($warehouseRevenue, $actualPayment),
                    'diff_system_warehouse' => $systemRevenue - $warehouseRevenue,
                    'diff_warehouse_payment' => $warehouseRevenue - $actualPayment,
                ];
            });
    }

    public function resolveSystemValue(Order $order): float
    {
        return (float) $order->total_amount;
    }

    public function resolveWarehouseValue(Order $order): float
    {
        $tickets = $this->resolveCompletedExportTickets($order);

        if ($tickets->isNotEmpty()) {
            return $this->resolveWarehouseValueFromTickets($order, $tickets);
        }

        if ($this->canUseOrderItemsAsWarehouseFallback($order)) {
            $itemsValue = $this->resolveWarehouseValueFromOrderItems($order);

            return $itemsValue > 0 ? $itemsValue : $this->resolveSystemValue($order);
        }

        return 0.0;
    }

    public function resolveActualPayment(Order $order): float
    {
        $reconciliationTotal = $order->relationLoaded('reconciliation')
            ? (float) $order->reconciliation->sum(fn ($reconciliation): float => (float) $reconciliation->cod_amount)
            : (float) $order->reconciliation()->sum('cod_amount');

        return $reconciliationTotal + (float) $order->amount_recived_from_customer;
    }

    public function resolveDiscrepancyNote(Order $order): string
    {
        $systemValue = $this->resolveSystemValue($order);
        $warehouseValue = $this->resolveWarehouseValue($order);
        $actualPayment = $this->resolveActualPayment($order);

        if ($this->valuesDifferent($systemValue, $warehouseValue)) {
            return __('accounting.report.discrepancy_system_warehouse_diff');
        }

        if ($this->valuesDifferent($warehouseValue, $actualPayment)) {
            return __('accounting.report.discrepancy_warehouse_payment_diff');
        }

        return __('accounting.report.discrepancy_matched');
    }

    public function valuesDifferent(float $left, float $right): bool
    {
        return abs($left - $right) > 0.1;
    }

    protected function resolveCompletedExportTickets(Order $order): EloquentCollection
    {
        if ($order->relationLoaded('inventoryTickets')) {
            return $order->inventoryTickets
                ->filter(fn (InventoryTicket $ticket): bool => (int) $ticket->status === StatusTicket::COMPLETED->value
                    && (int) $ticket->type === TypeTicket::EXPORT->value)
                ->values();
        }

        return InventoryTicket::query()
            ->where('order_id', $order->id)
            ->where('status', StatusTicket::COMPLETED->value)
            ->where('type', TypeTicket::EXPORT->value)
            ->with('details')
            ->get();
    }

    protected function resolveWarehouseValueFromTickets(Order $order, EloquentCollection $tickets): float
    {
        $orderItems = ($order->relationLoaded('items') ? $order->items : $order->items()->get())
            ->keyBy('product_id');

        $value = 0.0;

        foreach ($tickets as $ticket) {
            $ticket->loadMissing('details');

            foreach ($ticket->details as $detail) {
                $orderItem = $orderItems->get($detail->product_id);
                $price = $orderItem ? (float) $orderItem->price : 0.0;
                $value += ((float) $detail->quantity * $price);
            }
        }

        return $value;
    }

    protected function resolveWarehouseValueFromOrderItems(Order $order): float
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        return (float) $items->sum(
            fn ($item): float => ((float) $item->quantity * (float) $item->price)
        );
    }

    protected function canUseOrderItemsAsWarehouseFallback(Order $order): bool
    {
        return in_array((int) $order->status, [
            OrderStatus::SHIPPING->value,
            OrderStatus::COMPLETED->value,
        ], true);
    }
}
