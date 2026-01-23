<?php

namespace App\Services;

use App\Core\ServiceReturn;
use App\Repositories\ExchangeRateRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\RevenueRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountingService
{
    public function __construct(
        protected ExchangeRateRepository $exchangeRateRepository,
        protected ExpenseRepository $expenseRepository,
        protected RevenueRepository $revenueRepository,
    ) {}

    /**
     * Tạo tỉ giá mới
     */
    public function createExchangeRate(array $data): ServiceReturn
    {
        try {
            $data['organization_id'] = Auth::user()->organization_id;
            $data['created_by'] = Auth::id();

            $exchangeRate = $this->exchangeRateRepository->create($data);

            return ServiceReturn::success(data: $exchangeRate, message: __('accounting.exchange_rate.created'));
        } catch (Throwable $e) {
            Log::error('Create exchange rate error', ['error' => $e->getMessage(), 'data' => $data]);
            return ServiceReturn::error(__('accounting.exchange_rate.create_failed'));
        }
    }

    /**
     * Tạo chi phí mới
     */
    public function createExpense(array $data): ServiceReturn
    {
        try {
            $data['organization_id'] = Auth::user()->organization_id;
            $data['created_by'] = Auth::id();

            $expense = $this->expenseRepository->create($data);

            return ServiceReturn::success(data: $expense, message: __('accounting.expense.created'));
        } catch (Throwable $e) {
            Log::error('Create expense error', ['error' => $e->getMessage(), 'data' => $data]);
            return ServiceReturn::error(__('accounting.expense.create_failed'));
        }
    }

    /**
     * Tạo doanh thu khác
     */
    public function createRevenue(array $data): ServiceReturn
    {
        try {
            $data['organization_id'] = Auth::user()->organization_id;
            $data['created_by'] = Auth::id();

            $revenue = $this->revenueRepository->create($data);

            return ServiceReturn::success(data: $revenue, message: __('accounting.revenue.created'));
        } catch (Throwable $e) {
            Log::error('Create revenue error', ['error' => $e->getMessage(), 'data' => $data]);
            return ServiceReturn::error(__('accounting.revenue.create_failed'));
        }
    }

    /**
     * Lấy tổng hợp chi phí theo khoảng thời gian
     */
    public function getExpenseSummary(int $organizationId, string $fromDate, string $toDate): ServiceReturn
    {
        try {
            $expenses = $this->expenseRepository->query()
                ->where('organization_id', $organizationId)
                ->whereBetween('expense_date', [$fromDate, $toDate])
                ->get();

            $summary = [
                'total' => $expenses->sum('amount'),
                'by_category' => $expenses->groupBy('category')->map(fn($group) => $group->sum('amount')),
            ];

            return ServiceReturn::success(data: $summary);
        } catch (Throwable $e) {
            Log::error('Get expense summary error', ['error' => $e->getMessage()]);
            return ServiceReturn::error(__('accounting.expense.summary_failed'));
        }
    }
}

