<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

use Livewire\Attributes\On;
use Carbon\Carbon;

class FundStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public ?string $startDate = null;
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = session('dashboard_start_date', now()->startOfMonth()->format('Y-m-d'));
        $this->endDate = session('dashboard_end_date', now()->endOfMonth()->format('Y-m-d'));
    }

    #[On('dateRangeUpdated')]
    public function updateDateRange($start_date, $end_date): void
    {
        $this->startDate = $start_date;
        $this->endDate = $end_date;
    }

    protected function getStats(): array
    {
        /**
         * @var User $user
         */
        $user = Auth::user();
        $service = app(\App\Services\OrganizationService::class);
        $data = $service->getFundStats($user->organization_id, $this->startDate, $this->endDate);

        $fund = $data['fund'] ?? null;

        if (!$fund) {
            return [
                Stat::make(__('dashboard.stats.current_balance'), '0 đ')
                    ->description(__('dashboard.stats.no_fund'))
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('gray'),
            ];
        }

        $balanceChange = $data['filteredDeposit'] - $data['filteredWithdraw'];

        $days = Carbon::parse($this->startDate)->diffInDays(Carbon::parse($this->endDate)) + 1;
        $rangeLabel = $days . ' ngày';

        return [
            Stat::make(__('dashboard.stats.current_balance'), number_format($fund->balance, 0, ',', '.') . ' đ')
                ->description($balanceChange >= 0
                    ? __('dashboard.stats.increase') . ' ' . number_format(abs($balanceChange), 0, ',', '.') . ' đ (' . $rangeLabel . ')'
                    : __('dashboard.stats.decrease') . ' ' . number_format(abs($balanceChange), 0, ',', '.') . ' đ (' . $rangeLabel . ')')
                ->descriptionIcon($balanceChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($balanceChange >= 0 ? 'success' : 'danger')
                ->chart($data['balanceChart']),

            Stat::make(__('dashboard.stats.total_deposit'), number_format($data['totalDeposit'], 0, ',', '.') . ' đ')
                ->description($rangeLabel . ': ' . number_format($data['filteredDeposit'], 0, ',', '.') . ' đ')
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color('success')
                ->chart($data['depositChart']),

            Stat::make(__('dashboard.stats.total_withdraw'), number_format($data['totalWithdraw'], 0, ',', '.') . ' đ')
                ->description($rangeLabel . ': ' . number_format($data['filteredWithdraw'], 0, ',', '.') . ' đ')
                ->descriptionIcon('heroicon-m-arrow-down-circle')
                ->color('warning')
                ->chart($data['withdrawChart']),

            Stat::make(__('dashboard.stats.total_transactions'), $data['totalTransactions'])
                ->description($data['pendingTransactions'] > 0
                    ? $data['pendingTransactions'] . ' ' . __('dashboard.stats.pending_transactions')
                    : __('dashboard.stats.all_completed'))
                ->descriptionIcon($data['pendingTransactions'] > 0
                    ? 'heroicon-m-clock'
                    : 'heroicon-m-check-circle')
                ->color($data['pendingTransactions'] > 0 ? 'warning' : 'success'),
        ];
    }
}
