<?php

namespace App\Services;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Order\OrderStatus;
use App\Repositories\CustomerRepository;
use App\Repositories\OrderItemRepository;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardService
{

    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderItemRepository $orderItemRepository,
        protected ProductRepository $productRepository,
        protected CustomerRepository $customerRepository,
    ) {
    }

    /**
     * Get order statistics for the dashboard
     */
    public function getOrderStats(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $baseQuery = $this->orderRepository->query()->where('organization_id', $organizationId);
            $filteredQuery = (clone $baseQuery)->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            $totalRevenue = (clone $filteredQuery)
                ->where('status', OrderStatus::COMPLETED->value)
                ->sum('total_amount');

            $totalOrders = (clone $filteredQuery)->count();

            $pendingOrders = (clone $filteredQuery)
                ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])
                ->count();

            $completedOrders = (clone $filteredQuery)
                ->where('status', OrderStatus::COMPLETED->value)
                ->count();

            $cancelledOrders = (clone $filteredQuery)
                ->where('status', OrderStatus::CANCELLED->value)
                ->count();

            $shippingOrders = (clone $filteredQuery)
                ->where('status', OrderStatus::SHIPPING->value)
                ->count();

            // Mini-charts: last 7 data points by day
            $revenueChart = $this->orderRepository->query()->where('organization_id', $organizationId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->take(7)
                ->get()
                ->reverse()
                ->pluck('total')
                ->toArray();

            $ordersChart = $this->orderRepository->query()->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->take(7)
                ->get()
                ->reverse()
                ->pluck('total')
                ->toArray();

            return [
                'totalRevenue' => $totalRevenue,
                'totalOrders' => $totalOrders,
                'pendingOrders' => $pendingOrders,
                'completedOrders' => $completedOrders,
                'cancelledOrders' => $cancelledOrders,
                'shippingOrders' => $shippingOrders,
                'revenueChart' => $revenueChart,
                'ordersChart' => $ordersChart,
            ];
        } catch (Throwable $e) {
            Log::error('DashboardService@getOrderStats: ' . $e->getMessage());
            return [
                'totalRevenue' => 0,
                'totalOrders' => 0,
                'pendingOrders' => 0,
                'completedOrders' => 0,
                'cancelledOrders' => 0,
                'shippingOrders' => 0,
                'revenueChart' => [],
                'ordersChart' => [],
            ];
        }
    }

    /**
     * Get revenue chart data by day
     */
    public function getRevenueChartData(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $revenueData = $this->orderRepository->query()->where('organization_id', $organizationId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, SUM(total_amount) as total_revenue')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $orderCountData = $this->orderRepository->query()->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->whereNotIn('status', [OrderStatus::CANCELLED->value])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total_orders')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Merge by date
            $allDates = $revenueData->pluck('date')
                ->merge($orderCountData->pluck('date'))
                ->unique()
                ->sort()
                ->values();

            $revenueMap = $revenueData->pluck('total_revenue', 'date');
            $orderMap = $orderCountData->pluck('total_orders', 'date');

            $labels = [];
            $revenues = [];
            $orders = [];

            foreach ($allDates as $date) {
                $labels[] = \Carbon\Carbon::parse((string) $date)->format('d/m');
                $revenues[] = (float) ($revenueMap[$date] ?? 0);
                $orders[] = (int) ($orderMap[$date] ?? 0);
            }

            return [
                'labels' => $labels,
                'revenues' => $revenues,
                'orders' => $orders,
            ];
        } catch (Throwable $e) {
            Log::error('DashboardService@getRevenueChartData: ' . $e->getMessage());
            return ['labels' => [], 'revenues' => [], 'orders' => []];
        }
    }

    /**
     * Get top selling products
     */
    public function getTopProducts(int $organizationId, string $startDate, string $endDate, int $limit = 10): array
    {
        try {
            $products = $this->orderItemRepository->query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('orders.organization_id', $organizationId)
                ->where('orders.status', OrderStatus::COMPLETED->value)
                ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('products.name, SUM(order_items.quantity) as total_quantity, SUM(order_items.total) as total_revenue')
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('total_quantity')
                ->limit($limit)
                ->get();

            return [
                'labels' => $products->pluck('name')->toArray(),
                'quantities' => $products->pluck('total_quantity')->toArray(),
                'revenues' => $products->pluck('total_revenue')->toArray(),
            ];
        } catch (Throwable $e) {
            Log::error('DashboardService@getTopProducts: ' . $e->getMessage());
            return ['labels' => [], 'quantities' => [], 'revenues' => []];
        }
    }

    /**
     * Get order status distribution for pie/doughnut chart
     */
    public function getOrderStatusDistribution(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $distribution = $this->orderRepository->query()->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get();

            $labels = [];
            $data = [];
            $colors = [];

            $colorMap = [
                OrderStatus::PENDING->value => 'rgb(156, 163, 175)',    // gray
                OrderStatus::CONFIRMED->value => 'rgb(234, 179, 8)',    // yellow
                OrderStatus::SHIPPING->value => 'rgb(59, 130, 246)',    // blue
                OrderStatus::COMPLETED->value => 'rgb(34, 197, 94)',    // green
                OrderStatus::CANCELLED->value => 'rgb(239, 68, 68)',    // red
            ];

            foreach ($distribution as $item) {
                $status = OrderStatus::tryFrom($item->status);
                $labels[] = $status ? $status->label() : __('dashboard.order_status.unknown');
                $data[] = $item->count;
                $colors[] = $colorMap[$item->status] ?? 'rgb(107, 114, 128)';
            }

            return [
                'labels' => $labels,
                'data' => $data,
                'colors' => $colors,
            ];
        } catch (Throwable $e) {
            Log::error('DashboardService@getOrderStatusDistribution: ' . $e->getMessage());
            return ['labels' => [], 'data' => [], 'colors' => []];
        }
    }

    /**
     * Get lead/customer statistics
     */
    public function getLeadStats(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $baseQuery = $this->customerRepository->query()->where('organization_id', $organizationId);
            $filteredQuery = (clone $baseQuery)->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

            $totalLeads = (clone $filteredQuery)->count();

            $newLeads = (clone $filteredQuery)
                ->where('customer_type', CustomerType::NEW->value)
                ->count();

            $duplicateLeads = (clone $filteredQuery)
                ->where('customer_type', CustomerType::NEW_DUPLICATE->value)
                ->count();

            $oldCustomers = (clone $filteredQuery)
                ->where('customer_type', CustomerType::OLD_CUSTOMER->value)
                ->count();

            $unassignedLeads = (clone $filteredQuery)
                ->whereNull('assigned_staff_id')
                ->count();

            // Conversion rate: leads that have at least one completed order
            $leadsWithOrder = (clone $filteredQuery)
                ->whereHas('orders', function ($q) {
                    $q->where('status', OrderStatus::COMPLETED->value);
                })
                ->count();

            $conversionRate = $totalLeads > 0 ? round(($leadsWithOrder / $totalLeads) * 100, 1) : 0;

            // Mini-charts
            $leadsChart = $this->customerRepository->query()->where('organization_id', $organizationId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->take(7)
                ->get()
                ->reverse()
                ->pluck('total')
                ->toArray();

            return [
                'totalLeads' => $totalLeads,
                'newLeads' => $newLeads,
                'duplicateLeads' => $duplicateLeads,
                'oldCustomers' => $oldCustomers,
                'unassignedLeads' => $unassignedLeads,
                'conversionRate' => $conversionRate,
                'leadsWithOrder' => $leadsWithOrder,
                'leadsChart' => $leadsChart,
            ];
        } catch (Throwable $e) {
            Log::error('DashboardService@getLeadStats: ' . $e->getMessage());
            return [
                'totalLeads' => 0,
                'newLeads' => 0,
                'duplicateLeads' => 0,
                'oldCustomers' => 0,
                'unassignedLeads' => 0,
                'conversionRate' => 0,
                'leadsWithOrder' => 0,
                'leadsChart' => [],
            ];
        }
    }

    /**
     * Get customer growth chart data by day, segmented by type
     */
    public function getCustomerGrowthData(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $newData = $this->customerRepository->query()->where('organization_id', $organizationId)
                ->where('customer_type', CustomerType::NEW->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $duplicateData = $this->customerRepository->query()->where('organization_id', $organizationId)
                ->where('customer_type', CustomerType::NEW_DUPLICATE->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $oldData = $this->customerRepository->query()->where('organization_id', $organizationId)
                ->where('customer_type', CustomerType::OLD_CUSTOMER->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            // Merge all dates
            $allDates = $newData->pluck('date')
                ->merge($duplicateData->pluck('date'))
                ->merge($oldData->pluck('date'))
                ->unique()
                ->sort()
                ->values();

            $newMap = $newData->pluck('total', 'date');
            $dupMap = $duplicateData->pluck('total', 'date');
            $oldMap = $oldData->pluck('total', 'date');

            $labels = [];
            $newCounts = [];
            $dupCounts = [];
            $oldCounts = [];

            foreach ($allDates as $date) {
                $labels[] = \Carbon\Carbon::parse((string) $date)->format('d/m');
                $newCounts[] = (int) ($newMap[$date] ?? 0);
                $dupCounts[] = (int) ($dupMap[$date] ?? 0);
                $oldCounts[] = (int) ($oldMap[$date] ?? 0);
            }

            return [
                'labels' => $labels,
                'newCustomers' => $newCounts,
                'duplicateCustomers' => $dupCounts,
                'oldCustomers' => $oldCounts,
            ];
        } catch (Throwable $e) {
            Log::error('DashboardService@getCustomerGrowthData: ' . $e->getMessage());
            return ['labels' => [], 'newCustomers' => [], 'duplicateCustomers' => [], 'oldCustomers' => []];
        }
    }
}
