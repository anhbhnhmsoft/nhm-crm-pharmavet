<?php

namespace App\Services\Accounting;

use App\Common\Constants\Accounting\DebtPartnerType;
use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\Accounting\DebtTransactionType;
use App\Common\Constants\Order\OrderStatus;
use App\Core\ServiceReturn;
use App\Core\Logging;
use App\Repositories\OrderRepository;
use App\Repositories\ReconciliationRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OrganizationRepository;
use Throwable;

class DebtReconciliationService
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected ReconciliationRepository $reconciliationRepository,
        protected CustomerRepository $customerRepository,
        protected OrganizationRepository $organizationRepository,
    ) {
    }

    /**
     * Lấy danh sách khách hàng
     */
    public function getCustomersForSelect(int $organizationId, ?string $search = null): array
    {
        return $this->customerRepository->getForSelect($organizationId, $search);
    }

    /**
     * Lấy tên khách hàng theo ID
     */
    public function getCustomerNameById(?int $id): ?string
    {
        if (!$id) return null;
        return $this->customerRepository->find($id)?->username;
    }

    /**
     * Lấy dữ liệu đối chiếu cho Khách hàng
     */
    public function getCustomerReconciliation(int $customerId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $customer = $this->customerRepository->find($customerId);
            if (!$customer) {
                return ServiceReturn::error(__('accounting.customer.not_found'));
            }

            $openingBalance = $this->orderRepository->getCustomerBalanceBefore($customerId, $fromDate . ' 00:00:00');
            $orders = $this->orderRepository->getCustomerReconciliationOrders($customerId, $fromDate . ' 00:00:00', $toDate . ' 23:59:59');

            $transactions = [];
            $currentBalance = $openingBalance;

            foreach ($orders as $order) {
                $debit = (float) $order->total_amount;
                $credit = (float)($order->deposit ?? 0) + (float)($order->amount_recived_from_customer ?? 0);
                
                $currentBalance += ($debit - $credit);

                $transactions[] = [
                    'date' => $order->created_at->toDateString(),
                    'code' => $order->code,
                    'description' => __('accounting.debt_reconciliation.order_revenue'),
                    'debit' => $debit,
                    'credit' => $credit,
                    'remaining' => $debit - $credit,
                    'balance' => (float) $currentBalance,
                ];
            }

            return ServiceReturn::success(data: [
                'partner' => [
                    'name' => $customer->username,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                ],
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'opening_balance' => (float) $openingBalance,
                'transactions' => $transactions,
                'closing_balance' => (float) $currentBalance,
                'total_debit' => (float) collect($transactions)->sum('debit'),
                'total_credit' => (float) collect($transactions)->sum('credit'),
            ]);
        } catch (Throwable $e) {
            Logging::error('Get customer reconciliation data error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.debt_reconciliation.get_failed'));
        }
    }

    /**
     * Lấy dữ liệu đối chiếu cho Đơn vị vận chuyển (GHN)
     */
    public function getLogisticsReconciliation(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            // Tính số dư đầu kỳ
            $debitBefore = $this->orderRepository->getLogisticBalanceBefore($organizationId, $fromDate . ' 00:00:00');
            $creditBefore = $this->reconciliationRepository->getPaidCodBefore($organizationId, $fromDate);
            $openingBalance = $debitBefore - $creditBefore;

            // Lấy phát sinh trong kỳ
            // Tăng nợ: Các đơn hàng hoàn thành trong kỳ
            $orders = $this->orderRepository->query()
                ->where('organization_id', $organizationId)
                ->where('status', OrderStatus::COMPLETED->value)
                ->whereBetween('created_at', [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'])
                ->whereNotNull('ghn_order_code')
                ->get();

            // Giảm nợ: Các bản đối soát đã thanh toán trong kỳ
            $reconciliations = $this->reconciliationRepository->getReconciliationsByDateRange($organizationId, $fromDate, $toDate);

            $transactions = [];
            
            foreach ($orders as $order) {
                $transactions[] = [
                    'date' => $order->created_at->toDateString(),
                    'code' => $order->code,
                    'type' => DebtTransactionType::DEBIT,
                    'amount' => (float) $order->total_amount,
                    'description' => __('accounting.debt_reconciliation.delivered_order', ['code' => $order->ghn_order_code]),
                ];
            }

            foreach ($reconciliations as $rec) {
                if ($rec->status === ReconciliationStatus::PAID->value) {
                    $transactions[] = [
                        'date' => $rec->reconciliation_date->toDateString(),
                        'code' => $rec->ghn_order_code,
                        'type' => DebtTransactionType::CREDIT,
                        'amount' => (float) $rec->cod_amount,
                        'description' => __('accounting.debt_reconciliation.received_payment'),
                    ];
                }
            }

            // Sắp xếp theo ngày
            usort($transactions, fn($a, $b) => strcmp($a['date'], $b['date']));

            $closingBalance = $openingBalance;
            $processedTransactions = [];

            foreach ($transactions as $t) {
                if ($t['type'] === DebtTransactionType::DEBIT) {
                    $closingBalance += $t['amount'];
                    $t['debit'] = $t['amount'];
                    $t['credit'] = 0;
                } else {
                    $closingBalance -= $t['amount'];
                    $t['debit'] = 0;
                    $t['credit'] = $t['amount'];
                }
                $t['balance'] = $closingBalance;
                $processedTransactions[] = $t;
            }

            return ServiceReturn::success(data: [
                'partner' => [
                    'name' => 'Giao Hàng Nhanh (GHN)',
                    'code' => 'GHN',
                ],
                'period' => [
                    'from' => $fromDate,
                    'to' => $toDate,
                ],
                'opening_balance' => (float) $openingBalance,
                'transactions' => $processedTransactions,
                'closing_balance' => (float) $closingBalance,
                'total_debit' => (float) collect($processedTransactions)->sum('debit'),
                'total_credit' => (float) collect($processedTransactions)->sum('credit'),
            ]);
        } catch (Throwable $e) {
            Logging::error('Get logistics reconciliation data error', ['error' => $e->getMessage()], $e);
            return ServiceReturn::error(__('accounting.debt_reconciliation.get_failed'));
        }
    }
    /**
     * Lấy danh sách tổng hợp công nợ cho tất cả đối tác
     */
    public function getDebtSummaryList(int $organizationId, string $fromDate, string $toDate): array
    {
        $logisticsData = $this->getLogisticsReconciliation($organizationId, $fromDate, $toDate)->getData();
        $summary = [
            [
                'partner_id' => 0,
                'partner_type' => DebtPartnerType::LOGISTICS->value,
                'partner_name' => 'Giao Hàng Nhanh (GHN)',
                'opening_balance' => $logisticsData['opening_balance'] ?? 0,
                'total_debit' => $logisticsData['total_debit'] ?? 0,
                'total_credit' => $logisticsData['total_credit'] ?? 0,
                'closing_balance' => $logisticsData['closing_balance'] ?? 0,
            ]
        ];

        // Tất cả khách hàng từng có phát sinh (để tính số dư đầu kỳ và nợ tồn)
        $customers = $this->customerRepository->query()
            ->where('organization_id', $organizationId)
            ->whereHas('orders', function($query) use ($toDate) {
                $query->where('created_at', '<=', $toDate . ' 23:59:59');
            })
            ->select('id', 'username as name')
            ->get();

        foreach ($customers as $customer) {
            $cData = $this->getCustomerReconciliation($customer->id, $fromDate, $toDate)->getData();
            if ($cData && (
                abs($cData['opening_balance']) > 0 || 
                abs($cData['total_debit']) > 0 || 
                abs($cData['total_credit']) > 0 || 
                abs($cData['closing_balance']) > 0
            )) {
                $summary[] = [
                    'partner_id' => $customer->id,
                    'partner_type' => DebtPartnerType::CUSTOMER->value,
                    'partner_name' => $customer->name,
                    'opening_balance' => $cData['opening_balance'],
                    'total_debit' => $cData['total_debit'],
                    'total_credit' => $cData['total_credit'],
                    'closing_balance' => $cData['closing_balance'],
                ];
            }
        }

        return $summary;
    }
}
