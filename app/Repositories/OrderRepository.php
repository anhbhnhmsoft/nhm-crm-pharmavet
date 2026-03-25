<?php

namespace App\Repositories;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Core\BaseRepository;
use App\Models\Order;
use App\Common\Constants\Order\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class OrderRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Order();
    }

    /**
     * Tìm các đơn hàng có tuổi nợ đúng X ngày
     * Hỗ trợ cho Cronjob gửi thông báo định kỳ tại mốc 3, 15, 30 ngày
     */
    public function findOrdersByDebtAge(int $days, bool $isLogistic = true): Collection
    {
        $query = $this->query()
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereDate('updated_at', now()->subDays($days)->toDateString());

        if ($isLogistic) {
            // Nợ từ đơn vị vận chuyển (PTGH)
            return $query->where('total_amount', '>', 0)
                ->whereDoesntHave('reconciliation', function ($q) {
                    $q->where('status', ReconciliationStatus::PAID->value);
                })
                ->get();
        }

        // Nợ từ khách hàng
        return $query->whereRaw('total_amount > amount_recived_from_customer')
            ->get();
    }

    /**
     * Tìm các đơn hàng chưa nhận được tiền từ đơn vị vận chuyển (PTGH) sau X ngày (Lấy tất cả nợ >= X ngày)
     */
    public function findLogisticOrdersOverdue(int $thresholdDays): Collection
    {
        return $this->query()
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('total_amount', '>', 0)
            ->whereDoesntHave('reconciliation', function ($q) {
                $q->where('status', ReconciliationStatus::PAID->value);
            })
            ->whereDate('updated_at', '<=', now()->subDays($thresholdDays)->toDateString())
            ->get();
    }

    /**
     * Tìm các đơn hàng khách hàng còn nợ tiền sau X ngày hoàn thành (Lấy tất cả nợ >= X ngày)
     */
    public function findCustomerOrdersOverdue(int $thresholdDays): Collection
    {
        return $this->query()
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereRaw('total_amount > amount_recived_from_customer')
            ->whereDate('updated_at', '<=', now()->subDays($thresholdDays)->toDateString())
            ->get();
    }

    /**
     * Lấy các đơn hàng hoàn thành trong khoảng thời gian
     */
    public function findCompletedOrdersByDateRange(int $organizationId, string $startDate, string $endDate): Collection
    {
        return $this->query()
            ->where('organization_id', $organizationId)
            ->where('status', OrderStatus::COMPLETED->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('items')
            ->get();
    }
}