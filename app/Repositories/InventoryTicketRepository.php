<?php

namespace App\Repositories;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Core\BaseRepository;
use App\Models\InventoryTicket;
use Illuminate\Database\Eloquent\Model;

class InventoryTicketRepository extends BaseRepository
{
    public function model() : Model
    {
        return new InventoryTicket();
    }

    /**
     * Tính tổng giá trị hàng hoàn trong khoảng thời gian
     */
    public function sumCompletedSalesReturnsByDate(int $organizationId, string $startDate, string $endDate): float
    {
        return (float) $this->query()
            ->where('organization_id', $organizationId)
            ->where('is_sales_return', true)
            ->where('status', StatusTicket::COMPLETED->value)
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->with(['order' => function($q) {
                $q->select('id', 'total_amount');
            }])
            ->get()
            ->sum(fn($t) => $t->order->total_amount ?? 0);
    }
}
