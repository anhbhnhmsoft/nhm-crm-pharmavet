<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class LeadStatsWidget extends BaseWidget
{
    protected static ?int $sort = 8;
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
        $user = Auth::user();
        $service = app(DashboardService::class);
        $data = $service->getLeadStats($user->organization_id, $this->startDate, $this->endDate);

        return [
            Stat::make(__('dashboard.lead_stats.total_leads'), $data['totalLeads'])
                ->description($data['newLeads'] . ' ' . __('dashboard.lead_stats.new_leads'))
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->chart($data['leadsChart']),

            Stat::make(__('dashboard.lead_stats.new_leads'), $data['newLeads'])
                ->description(__('dashboard.order_stats.in_period'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make(__('dashboard.lead_stats.conversion_rate'), $data['conversionRate'] . '%')
                ->description($data['leadsWithOrder'] . ' ' . __('dashboard.lead_stats.leads_with_order'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($data['conversionRate'] > 10 ? 'success' : 'warning'),

            Stat::make(__('dashboard.lead_stats.unassigned_leads'), $data['unassignedLeads'])
                ->description($data['unassignedLeads'] > 0
                    ? $data['unassignedLeads'] . ' ' . __('dashboard.lead_stats.unassigned_count')
                    : __('dashboard.lead_stats.no_unassigned'))
                ->descriptionIcon($data['unassignedLeads'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($data['unassignedLeads'] > 0 ? 'danger' : 'success'),
        ];
    }
}
