<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Order;
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
                'createdBy:id,name'
            ])
            ->get()
            ->map(function ($order) {
                // 1. Doanh thu hệ thống (Sale chốt)
                $systemRevenue = (float) $order->total_amount;

                // 2. Doanh thu thực tế (Kho xuất)
                // Lấy từ InventoryTicket liên kết với Order
                $warehouseRevenue = 0;
                $orderItems = $order->items->keyBy('product_id');
                
                $tickets = \App\Models\InventoryTicket::where('order_id', $order->id)
                    ->where('status', \App\Common\Constants\Warehouse\StatusTicket::COMPLETED->value)
                    ->where('type', \App\Common\Constants\Warehouse\TypeTicket::EXPORT->value)
                    ->with('details')
                    ->get();

                foreach ($tickets as $ticket) {
                    foreach ($ticket->details as $detail) {
                        $orderItem = $orderItems->get($detail->product_id);
                        $price = $orderItem ? (float) $orderItem->price : 0;
                        $warehouseRevenue += ($detail->quantity * $price);
                    }
                }

                // 3. Thanh toán thực tế (Ngân hàng/PTGH trả)
                $actualPayment = (float) $order->reconciliation->sum('cod_amount') + (float) $order->amount_recived_from_customer;

                return [
                    'order_id' => $order->id,
                    'order_code' => $order->code,
                    'sale_name' => $order->createdBy?->name ?? 'N/A',
                    'created_at' => $order->created_at->toDateTimeString(),
                    'system_revenue' => $systemRevenue,
                    'warehouse_revenue' => $warehouseRevenue,
                    'actual_payment' => $actualPayment,
                    'is_discrepant' => ($systemRevenue != $warehouseRevenue) || ($warehouseRevenue != $actualPayment),
                    'diff_system_warehouse' => $systemRevenue - $warehouseRevenue,
                    'diff_warehouse_payment' => $warehouseRevenue - $actualPayment,
                ];
            });
    }
}
