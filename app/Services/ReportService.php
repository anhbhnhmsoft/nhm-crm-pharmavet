<?php

namespace App\Services;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Customer\CustomerType;
use App\Core\ServiceReturn;
use App\Core\Logging;
use App\Repositories\OrderRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\RevenueRepository;
use App\Repositories\CustomerRepository;
use Throwable;

class ReportService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ExpenseRepository $expenseRepository,
        protected RevenueRepository $revenueRepository,
        protected CustomerRepository $customerRepository,
    ) {
    }

    /**
     * Báo cáo kinh doanh theo tháng/ngày
     */
    public function getBusinessReport(int $organizationId, string $fromDate, string $toDate, string $type = 'day'): ServiceReturn
    {
        try {
            // Doanh thu từ đơn hàng
            $orders = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->get();

            $revenueFromOrders = $orders->where('status', OrderStatus::COMPLETED->value)->sum('total_amount');
            $revenueReturned = $orders->where('status', OrderStatus::CANCELLED->value)->sum('total_amount');
            $revenueShipping = $orders->where('status', OrderStatus::SHIPPING->value)->sum('total_amount');

            // Doanh thu khác
            $otherRevenues = $this->revenueRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('revenue_date', [$fromDate, $toDate])
                ->sum('amount');

            $totalRevenue = $revenueFromOrders + $otherRevenues;

            // Chi phí
            $expenses = $this->expenseRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('expense_date', [$fromDate, $toDate])
                ->get();

            $totalExpense = $expenses->sum('amount');

            $expenseByCategory = $expenses->groupBy('category')->map(fn($group) => $group->sum('amount'));
            $expenseByCategoryResult = [];
            foreach (ExpenseCategory::cases() as $category) {
                $expenseByCategoryResult[$category->value] = $expenseByCategory[$category->value] ?? 0;
            }

            // Lợi nhuận
            $profit = $totalRevenue - $totalExpense;
            $profitRate = $totalRevenue > 0 ? ($profit / $totalRevenue) * 100 : 0;

            $report = [
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'type' => $type,
                ],
                'revenue' => [
                    'from_orders' => $revenueFromOrders,
                    'from_orders_completed' => $revenueFromOrders,
                    'from_orders_returned' => $revenueReturned,
                    'from_orders_shipping' => $revenueShipping,
                    'other' => $otherRevenues,
                    'total' => $totalRevenue,
                ],
                'expense' => [
                    'total' => $totalExpense,
                    'by_category' => $expenseByCategoryResult,
                ],
                'profit' => [
                    'amount' => $profit,
                    'rate' => round($profitRate, 2),
                ],
            ];

            return ServiceReturn::success(data: $report);
        } catch (Throwable $e) {
            Logging::error('Get business report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.report.get_failed'));
        }
    }

    /**
     * Báo cáo doanh số Sale - breakdown + tỷ lệ + COD nghiệp vụ
     */
    public function getSalesReport(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $completedStatus = OrderStatus::COMPLETED->value;
            $shippingStatus = OrderStatus::SHIPPING->value;
            $cancelledStatus = OrderStatus::CANCELLED->value;

            // Lấy tất cả các đơn hàng thuộc organization trong khoảng thời gian
            $orders = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->with('createdBy:id,name')
                ->get();

            // Group theo nhân viên (Sale)
            $saleData = $orders->groupBy('created_by')->map(function ($staffOrders) use ($completedStatus, $shippingStatus, $cancelledStatus) {
                $total = $staffOrders->count();

                // Thành công
                $successOrders = $staffOrders->where('status', $completedStatus);
                $successCount = $successOrders->count();
                $successCod = $successOrders->sum(fn($o) => $o->total_amount - $o->deposit);

                // Hoàn (Đã gửi đi nhưng bị hủy)
                $returnedOrders = $staffOrders->where('status', $cancelledStatus)->filter(fn($o) => !empty($o->ghn_order_code));
                $returnedCount = $returnedOrders->count();
                $returnedCod = $returnedOrders->sum(fn($o) => $o->total_amount - $o->deposit);

                // Đang giao
                $deliveringOrders = $staffOrders->where('status', $shippingStatus);
                $deliveringCount = $deliveringOrders->count();
                $deliveringCod = $deliveringOrders->sum(fn($o) => $o->total_amount - $o->deposit);

                // Các đơn khác (mới tạo, chờ vận đơn)
                $otherCount = $total - ($successCount + $returnedCount + $deliveringCount);

                return [
                    'staff_name' => $staffOrders->first()->createdBy->name ?? 'N/A',
                    'total_count' => $total,
                    'success' => [
                        'count' => $successCount,
                        'cod' => $successCod,
                        'rate' => $total > 0 ? round(($successCount / $total) * 100, 2) : 0,
                    ],
                    'returned' => [
                        'count' => $returnedCount,
                        'cod' => $returnedCod,
                        'rate' => $total > 0 ? round(($returnedCount / $total) * 100, 2) : 0,
                    ],
                    'delivering' => [
                        'count' => $deliveringCount,
                        'cod' => $deliveringCod,
                        'rate' => $total > 0 ? round(($deliveringCount / $total) * 100, 2) : 0,
                    ],
                    'other_count' => $otherCount,
                ];
            })->values();

            // Tính tổng cộng (Summary)
            $summary = [
                'total_orders' => $orders->count(),
                'success' => [
                    'count' => $saleData->sum('success.count'),
                    'cod' => $saleData->sum('success.cod'),
                    'rate' => $orders->count() > 0 ? round(($saleData->sum('success.count') / $orders->count()) * 100, 2) : 0,
                ],
                'returned' => [
                    'count' => $saleData->sum('returned.count'),
                    'cod' => $saleData->sum('returned.cod'),
                    'rate' => $orders->count() > 0 ? round(($saleData->sum('returned.count') / $orders->count()) * 100, 2) : 0,
                ],
                'delivering' => [
                    'count' => $saleData->sum('delivering.count'),
                    'cod' => $saleData->sum('delivering.cod'),
                    'rate' => $orders->count() > 0 ? round(($saleData->sum('delivering.count') / $orders->count()) * 100, 2) : 0,
                ],
            ];

            return ServiceReturn::success(data: [
                'breakdown' => $saleData,
                'summary' => $summary,
            ]);
        } catch (Throwable $e) {
            Logging::error('Get sales report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error('Could not generate sales report');
        }
    }

    /**
     * Báo cáo Marketing - Theo nguồn (source)
     */
    public function getMarketingReport(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $completedStatus = OrderStatus::COMPLETED->value;
            $shippingStatus = OrderStatus::SHIPPING->value;
            $cancelledStatus = OrderStatus::CANCELLED->value;

            $orders = $this->orderRepository->query()
                ->where('orders.organization_id', $organizationId)
                ->whereBetween('orders.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->join('customers', 'orders.customer_id', '=', 'customers.id')
                ->select('orders.*', 'customers.source as customer_source')
                ->get();

            $marketingData = $orders->groupBy('customer_source')->map(function ($sourceOrders) use ($completedStatus, $shippingStatus, $cancelledStatus) {
                $total = $sourceOrders->count();

                // Thành công
                $successOrders = $sourceOrders->where('status', $completedStatus);
                $successCount = $successOrders->count();
                $successCod = $successOrders->sum(fn($o) => $o->total_amount - $o->deposit);

                // Hoàn (Đã gửi đi nhưng bị hủy)
                $returnedOrders = $sourceOrders->where('status', $cancelledStatus)->filter(fn($o) => !empty($o->ghn_order_code));
                $returnedCount = $returnedOrders->count();
                $returnedCod = $returnedOrders->sum(fn($o) => $o->total_amount - $o->deposit);

                // Đang giao
                $deliveringOrders = $sourceOrders->where('status', $shippingStatus);
                $deliveringCount = $deliveringOrders->count();
                $deliveringCod = $deliveringOrders->sum(fn($o) => $o->total_amount - $o->deposit);

                return [
                    'source' => $sourceOrders->first()->customer_source ?: 'Unknown',
                    'total_count' => $total,
                    'success' => [
                        'count' => $successCount,
                        'cod' => $successCod,
                        'rate' => $total > 0 ? round(($successCount / $total) * 100, 2) : 0,
                    ],
                    'returned' => [
                        'count' => $returnedCount,
                        'cod' => $returnedCod,
                        'rate' => $total > 0 ? round(($returnedCount / $total) * 100, 2) : 0,
                    ],
                    'delivering' => [
                        'count' => $deliveringCount,
                        'cod' => $deliveringCod,
                        'rate' => $total > 0 ? round(($deliveringCount / $total) * 100, 2) : 0,
                    ],
                ];
            })->values();

            return ServiceReturn::success(data: $marketingData);
        } catch (Throwable $e) {
            Logging::error('Get marketing report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error('Could not generate marketing report');
        }
    }

    /**
     * Báo cáo Fanpage
     */
    public function getFanpageReport(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $completedStatus = OrderStatus::COMPLETED->value;
            $shippingStatus = OrderStatus::SHIPPING->value;
            $cancelledStatus = OrderStatus::CANCELLED->value;

            $customers = $this->customerRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->get();

            $fanpageData = $customers->groupBy('source_detail')->map(function ($pageCustomers) use ($completedStatus, $shippingStatus, $cancelledStatus) {
                $pageName = $pageCustomers->first()->source_detail ?: 'Khác';
                $marketerName = $pageCustomers->first()->source ?: 'Hệ thống'; // Sử dụng source hoặc tạo cơ chế map với created_by sau

                $newCustomers = $pageCustomers->where('customer_type', CustomerType::NEW ->value)->count();
                $totalLeads = $pageCustomers->whereNotNull('phone')->count();

                $customerIds = $pageCustomers->pluck('id')->toArray();
                $orders = $this->orderRepository->query()
                    ->whereIn('customer_id', $customerIds)
                    ->whereNotIn('status', [$cancelledStatus])
                    ->get();

                $totalOrders = $orders->count();
                $revenue = $orders->sum(fn($o) => $o->total_amount - $o->deposit);

                $conversionRate = $totalLeads > 0 ? round(($totalOrders / $totalLeads) * 100, 2) : 0;

                return [
                    'page_name' => $pageName,
                    'mkt_name' => $marketerName,
                    'new_customers' => $newCustomers,
                    'total_leads' => $totalLeads,
                    'total_orders' => $totalOrders,
                    'conversion_rate' => $conversionRate,
                    'revenue' => $revenue,
                ];
            })->values();

            return ServiceReturn::success(data: $fanpageData);
        } catch (Throwable $e) {
            Logging::error('Get fanpage report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error('Could not generate fanpage report');
        }
    }


    /**
     * Báo cáo khách hàng - Mới vs Cũ
     */
    public function getCustomerReport(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $completedStatus = OrderStatus::COMPLETED->value;

            $orders = $this->orderRepository->query()
                ->where('orders.organization_id', $organizationId)
                ->whereBetween('orders.created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->join('customers', 'orders.customer_id', '=', 'customers.id')
                ->select('orders.*', 'customers.customer_type as type')
                ->get();

            $customerData = $orders->groupBy('type')->map(function ($typeOrders) use ($completedStatus) {
                $completedOrders = $typeOrders->where('status', $completedStatus);
                $revenue = $completedOrders->sum('total_amount');
                $count = $typeOrders->count();

                return [
                    'type_id' => $typeOrders->first()->type,
                    'count' => $count,
                    'revenue' => $revenue,
                ];
            })->values();

            return ServiceReturn::success(data: $customerData);
        } catch (Throwable $e) {
            Logging::error('Get customer report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error('Could not generate customer report');
        }
    }
}
