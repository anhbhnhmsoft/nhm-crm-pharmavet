<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class CustomerGrowthChartWidget extends ChartWidget
{
    protected static ?int $sort = 9;
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
        return __('dashboard.customer_growth.heading');
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $data = $service->getCustomerGrowthData($user->organization_id, $this->startDate, $this->endDate);

        if (empty($data['labels'])) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.customer_growth.new_customers'),
                    'data' => $data['newCustomers'],
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => __('dashboard.customer_growth.duplicate_customers'),
                    'data' => $data['duplicateCustomers'],
                    'borderColor' => 'rgb(234, 179, 8)',
                    'backgroundColor' => 'rgba(234, 179, 8, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => __('dashboard.customer_growth.old_customers'),
                    'data' => $data['oldCustomers'],
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'title' => [
                        'display' => true,
                        'text' => __('dashboard.customer_growth.y_axis'),
                    ],
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
