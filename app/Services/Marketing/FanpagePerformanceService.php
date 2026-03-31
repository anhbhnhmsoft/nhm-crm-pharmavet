<?php

namespace App\Services\Marketing;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Order\OrderStatus;
use App\Core\ServiceReturn;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderRepository;
use Throwable;

class FanpagePerformanceService
{
    public function __construct(
        protected CustomerRepository $customerRepository,
        protected OrderRepository $orderRepository,
    ) {
    }

    public function getReport(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $cancelledStatus = OrderStatus::CANCELLED->value;

            $customers = $this->customerRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->get();

            $rows = $customers->groupBy('source_detail')->map(function ($pageCustomers) use ($cancelledStatus) {
                $customerIds = $pageCustomers->pluck('id')->toArray();
                $orders = $this->orderRepository->query()
                    ->whereIn('customer_id', $customerIds)
                    ->whereNotIn('status', [$cancelledStatus])
                    ->get();

                $totalLeads = $pageCustomers->whereNotNull('phone')->count();
                $totalOrders = $orders->count();
                $revenue = (float) $orders->sum(fn($order) => (float) $order->total_amount - (float) $order->deposit);

                return [
                    'page_name' => $pageCustomers->first()->source_detail ?: __('marketing.report.unknown_page'),
                    'mkt_name' => $pageCustomers->first()->source ?: __('marketing.report.unknown_source'),
                    'new_customers' => $pageCustomers->where('customer_type', CustomerType::NEW->value)->count(),
                    'total_leads' => $totalLeads,
                    'total_orders' => $totalOrders,
                    'conversion_rate' => $totalLeads > 0 ? round(($totalOrders / $totalLeads) * 100, 2) : 0,
                    'revenue' => $revenue,
                ];
            })->values();

            return ServiceReturn::success($rows);
        } catch (Throwable $exception) {
            return ServiceReturn::error($exception->getMessage());
        }
    }
}
