<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class OrderStatusChartWidget extends ChartWidget
{
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 1;

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

    public function getHeading(): string
    {
        return __('dashboard.order_status.heading');
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $data = $service->getOrderStatusDistribution($user->organization_id, $this->startDate, $this->endDate);

        if (empty($data['labels'])) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [
                [
                    'data' => $data['data'],
                    'backgroundColor' => $data['colors'],
                    'borderWidth' => 0,
                    'hoverOffset' => 8,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
