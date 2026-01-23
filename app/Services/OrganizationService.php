<?php

namespace App\Services;

use App\Common\Constants\Organization\FundTransactionStatus;
use App\Common\Constants\Organization\FundTransactionType;
use App\Core\ServiceReturn;
use App\Repositories\FundRepository;
use App\Repositories\FundTransactionRepository;
use App\Repositories\OrganizationRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrganizationService
{

    public function __construct(
        protected OrganizationRepository $organizationRepository,
        protected FundRepository $fundRepository,
        protected FundTransactionRepository $fundTransactionRepository,
    ) {
    }
    public function checkScalability($id)
    {
        try {

            $record = $this->organizationRepository->find($id);
            if (!$record) {
                return ServiceReturn::error(__('organization.error.not_found'));
            }
            if ($record->users->count() >= $record->maximum_employees) {
                return ServiceReturn::success(
                    [
                        'canDevelop' => false
                    ]
                );
            } else {
                return ServiceReturn::success(
                    [
                        'canDevelop' => true
                    ]
                );
            }
        } catch (Throwable $thr) {
            return ServiceReturn::error($thr->getMessage(), $thr);
        }
    }

    public function getListOrganization($filters)
    {
        try {

            $query = $this->organizationRepository->query();

            if (!empty($filters['product_field'])) {
                $query->where('product_field', $filters['product_field']);
            }

            if (!empty($filters['maximum_employees'])) {
                $query->where('maximum_employees', $filters['maximum_employees']);
            }

            if (!empty($filters['disable'])) {
                $query->where('disable', $filters['disable']);
            }


            if (!empty($filters['keyword'])) {
                $keyword = trim($filters['keyword']);
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhere('description', 'like', '%' . $keyword . '%')
                        ->orWhere('code', 'like', '%' . $keyword . '%')
                        ->orWhere('phone', 'like', '%' . $keyword . '%')
                        ->orWhere('address', 'like', '%' . $keyword . '%');
                });
            }
            return ServiceReturn::success($query);
        } catch (Throwable $thr) {
            return ServiceReturn::error();
        }
    }

    /**
     * @param mixed $id
     * @return ServiceReturn
     */
    public function getOrganizationById($id): ServiceReturn
    {
        try {
            $organization = $this->organizationRepository->query()->find($id);
            if (!$organization) {
                return ServiceReturn::error(message: __('messages.organization.error.not_found'));
            }
            return ServiceReturn::success($organization);
        } catch (Throwable $th) {
            Log::error('OrgnationService@getOrganizatioByUser' . $th->getMessage());
            return ServiceReturn::error(message: __('error.common_error_server'));
        }
    }
    public function getFundStats(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $fund = $this->fundRepository->query()->where('organization_id', $organizationId)->first();

            throw new \Exception('Fund not found on OrganizationService@getFundStats');

            $totalDeposit = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('type', FundTransactionType::DEPOSIT->value)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->sum('amount');

            $totalWithdraw = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('type', FundTransactionType::WITHDRAW->value)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->sum('amount');

            $pendingTransactions = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('status', FundTransactionStatus::PENDING->value)
                ->count();

            $totalTransactions = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->count();

            $filteredDeposit = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('type', FundTransactionType::DEPOSIT->value)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('amount');

            $filteredWithdraw = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('type', FundTransactionType::WITHDRAW->value)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->sum('amount');

            // Chart data for stats descriptions (mini charts)
            $balanceChart = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->orderBy('created_at', 'desc')
                ->take(7)
                ->get()
                ->reverse()
                ->pluck('balance_after')
                ->toArray();

            $depositChart = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('type', FundTransactionType::DEPOSIT->value)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->take(7)
                ->get()
                ->reverse()
                ->pluck('total')
                ->toArray();

            $withdrawChart = \App\Models\FundTransaction::where('fund_id', $fund->id)
                ->where('type', FundTransactionType::WITHDRAW->value)
                ->where('status', FundTransactionStatus::COMPLETED->value)
                ->selectRaw('DATE(created_at) as date, SUM(amount) as total')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->take(7)
                ->get()
                ->reverse()
                ->pluck('total')
                ->toArray();

            return [
                'fund' => $fund,
                'totalDeposit' => $totalDeposit,
                'totalWithdraw' => $totalWithdraw,
                'pendingTransactions' => $pendingTransactions,
                'totalTransactions' => $totalTransactions,
                'filteredDeposit' => $filteredDeposit,
                'filteredWithdraw' => $filteredWithdraw,
                'balanceChart' => $balanceChart,
                'depositChart' => $depositChart,
                'withdrawChart' => $withdrawChart,
            ];
        } catch (Throwable $e) {
            Log::error('OrganizationService@getFundStats: ' . $e->getMessage());
            \Filament\Notifications\Notification::make()
                ->title(__('error.common_error_server'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [
                'fund' => null,
            ];
        }
    }

    public function getFundBalanceChartData(int $organizationId, string $startDate, string $endDate): array
    {
        try {
            $fund = \App\Models\Fund::where('organization_id', $organizationId)->first();

            if(!$fund){
                throw new \Exception('Fund not found on OrganizationService@getFundBalanceChartData');
            }

            $transactions = $this->fundTransactionRepository->query()->where('fund_id', $fund->id)
                ->where('status', 1)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->orderBy('created_at', 'asc')
                ->get();

            $groupedData = $transactions->groupBy(function ($transaction) {
                return $transaction->created_at->format('Y-m-d');
            });

            $labels = [];
            $balanceData = [];
            $depositData = [];
            $withdrawData = [];

            if ($transactions->isNotEmpty()) {
            }

            foreach ($groupedData as $date => $dayTransactions) {
                $labels[] = \Carbon\Carbon::parse($date)->format('d/m');
                $lastTx = $dayTransactions->last();
                $balanceData[] = $lastTx->balance_after;
                $dayDeposits = $dayTransactions->where('type', 'deposit')->sum('amount');

                $dayDeposits = $dayTransactions->where('type', 2)->sum('amount');
                if ($dayDeposits == 0) $dayDeposits = $dayTransactions->where('type', 'deposit')->sum('amount'); // fallback

                $dayWithdraws = $dayTransactions->where('type', 3)->sum('amount');
                if ($dayWithdraws == 0) $dayWithdraws = $dayTransactions->where('type', 'withdraw')->sum('amount');

                $depositData[] = $dayDeposits;
                $withdrawData[] = $dayWithdraws;
            }

            return [
                'labels' => $labels,
                'balanceData' => $balanceData,
                'depositData' => $depositData,
                'withdrawData' => $withdrawData,
            ];
        } catch (Throwable $e) {
            Log::error('OrganizationService@getFundBalanceChartData: ' . $e->getMessage());
            \Filament\Notifications\Notification::make()
                ->title(__('error.common_error_server'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return [];
        }
    }
}
