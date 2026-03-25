<?php

namespace App\Services;

use App\Common\Constants\Accounting\ExpenseCategory;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Customer\CustomerType;
use App\Core\ServiceReturn;
use App\Core\Logging;
use App\Repositories\OrderRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\RevenueRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\InventoryTicketRepository;
use App\Repositories\FinancialSummaryRepository;
use App\Repositories\FundRepository;
use App\Repositories\FundTransactionRepository;
use App\Common\Constants\Organization\FundTransactionType;
use Throwable;

class ReportService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ExpenseRepository $expenseRepository,
        protected RevenueRepository $revenueRepository,
        protected CustomerRepository $customerRepository,
        protected InventoryTicketRepository $inventoryTicketRepository,
        protected FinancialSummaryRepository $financialSummaryRepository,
        protected FundRepository $fundRepository,
        protected FundTransactionRepository $fundTransactionRepository,
    ) {
    }

    /**
     * Báo cáo kinh doanh theo tháng/ngày
     */
    public function getBusinessReport(int $organizationId, string $fromDate, string $toDate, string $type = 'day'): ServiceReturn
    {
        try {
            $stats = $this->financialSummaryRepository->getSummaryByDateRange($organizationId, $fromDate, $toDate);

            $grossRevenue = (float) $stats->sum('gross_revenue');
            $totalDiscounts = (float) $stats->sum('discounts');
            $returns = (float) $stats->sum('returns_value');
            $netRevenue = (float) $stats->sum('net_revenue');
            $otherRevenues = (float) $stats->sum('other_revenues');
            $totalExpense = (float) $stats->sum('total_expenses');
            $cogs = (float) $stats->sum('cogs');
            $grossProfit = (float) $stats->sum('gross_profit');
            $netProfit = (float) $stats->sum('net_profit');

            $profitRate = ($netRevenue + $otherRevenues) > 0 ? ($netProfit / ($netRevenue + $otherRevenues)) * 100 : 0;

            $report = [
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                    'type' => $type,
                ],
                'revenue' => [
                    'gross' => $grossRevenue,
                    'discounts' => $totalDiscounts,
                    'returns' => $returns,
                    'net' => $netRevenue,
                    'other' => $otherRevenues,
                    'total' => $netRevenue + $otherRevenues,
                ],
                'expense' => [
                    'total' => $totalExpense,
                    'cogs' => $cogs,
                ],
                'profit' => [
                    'gross' => $grossProfit,
                    'net' => $netProfit,
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

    /**
     * Bác cáo khoản phải thu và Tuổi nợ
     */
    public function getReceivableReport(int $organizationId): ServiceReturn
    {
        try {
            // 1. Phải thu PTGH (Logistics Partner - GHN)
            // Đơn COMPLETED, có COD > 0, chưa có đối soát status=PAID
            $logisticsReceivables = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->where('total_amount', '>', 0)
                ->whereDoesntHave('reconciliation', function ($query) {
                    $query->where('status', ReconciliationStatus::PAID->value);
                })
                ->with(['createdBy', 'customer'])
                ->get()
                ->map(function ($order) {
                    $age = now()->diffInDays($order->ghn_posted_at ?? $order->updated_at, true);
                    return [
                        'order_id' => $order->id,
                        'order_code' => $order->code,
                        'customer_name' => $order->customer?->name,
                        'amount' => (float)($order->total_amount - ($order->deposit ?? 0)),
                        'sale_name' => $order->createdBy?->name,
                        'debt_age' => (int) $age,
                        'type' => 'Logistics',
                    ];
                });

            // 2. Phải thu KH (Customer Direct)
            // Đơn COMPLETED, tiền nhận từ khách < tổng tiền
            $customerReceivables = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereRaw('total_amount > amount_recived_from_customer')
                ->with(['createdBy', 'customer'])
                ->get()
                ->map(function ($order) {
                    $age = now()->diffInDays($order->updated_at, true);
                    return [
                        'order_id' => $order->id,
                        'order_code' => $order->code,
                        'customer_name' => $order->customer?->name,
                        'amount' => (float)($order->total_amount - ($order->amount_recived_from_customer ?? 0)),
                        'sale_name' => $order->createdBy?->name,
                        'debt_age' => (int) $age,
                        'type' => 'Customer',
                    ];
                });

            return ServiceReturn::success(data: [
                'logistics' => $logisticsReceivables,
                'customers' => $customerReceivables,
                'summary' => [
                    'total_logistics' => $logisticsReceivables->sum('amount'),
                    'total_customers' => $customerReceivables->sum('amount'),
                    'grand_total' => $logisticsReceivables->sum('amount') + $customerReceivables->sum('amount'),
                ]
            ]);
        } catch (Throwable $e) {
            Logging::error('Get receivable report error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error('Could not generate receivable report');
        }
    }

    /**
     * BCTC: Bảng cân đối và Lưu chuyển tiền tệ
     */
    public function getFinancialStatementReport(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            // Kết quả kinh doanh cơ bản (Net Revenue, COGS, Gross Profit)
            $stats = $this->financialSummaryRepository->getSummaryByDateRange($organizationId, $fromDate, $toDate);
            
            $netRevenue = (float) $stats->sum('net_revenue');
            $cogs = (float) $stats->sum('cogs');
            $grossProfit = (float) $stats->sum('gross_profit');
            
            // Chi phí vận hành bóc tách
            $expenseBreakdown = $this->expenseRepository->sumByCategoryDateRange($organizationId, $fromDate, $toDate);
            $totalExpenses = (float) $expenseBreakdown->sum();
            
            // Tài sản
            $cashBalance = $this->fundRepository->sumTotalBalanceByOrganization($organizationId);
            $receivableSummary = $this->getReceivableReport($organizationId)->getData()['summary'] ?? [];
            $totalReceivables = (float) ($receivableSummary['grand_total'] ?? 0);
            
            // Lưu chuyển tiền tệ
            $fundFlow = $this->fundTransactionRepository->sumByTypeDateRange($organizationId, $fromDate, $toDate);
            $inflow = (float) ($fundFlow[FundTransactionType::DEPOSIT->value] ?? 0);
            $outflow = (float) ($fundFlow[FundTransactionType::WITHDRAW->value] ?? 0);
            
            $report = [
                'income_statement' => [
                    'net_revenue' => $netRevenue,
                    'cogs' => $cogs,
                    'gross_profit' => $grossProfit,
                    'operating_expenses' => [
                        'total' => $totalExpenses,
                        'details' => $expenseBreakdown,
                    ],
                    'net_income' => $grossProfit - $totalExpenses + (float) $stats->sum('other_revenues'),
                ],
                'balance_sheet_frame' => [
                    'current_assets' => [
                        'cash_and_bank' => $cashBalance,
                        'accounts_receivable' => $totalReceivables,
                    ],
                    'total_assets_estimate' => $cashBalance + $totalReceivables,
                ],
                'cash_flow_frame' => [
                    'inflow' => $inflow,
                    'outflow' => $outflow,
                    'net_cash_flow' => $inflow - $outflow,
                ]
            ];

            return ServiceReturn::success(data: $report);
        } catch (Throwable $e) {
            Logging::error('Get financial statements error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error('Could not generate financial statements');
        }
    }
}
