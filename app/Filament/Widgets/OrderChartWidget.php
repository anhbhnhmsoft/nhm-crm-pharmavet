<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class OrderChartWidget extends ChartWidget
{
    protected static ?int $sort = 5;
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

    public function getHeading(): string
    {
        return __('dashboard.order_chart.heading');
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $data = $service->getRevenueChartData($user->organization_id, $this->startDate, $this->endDate);

        if (empty($data['labels'])) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.order_chart.revenue'),
                    'data' => $data['revenues'],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => __('dashboard.order_chart.order_count'),
                    'data' => $data['orders'],
                    'backgroundColor' => 'rgba(234, 179, 8, 0.8)',
                    'borderColor' => 'rgb(234, 179, 8)',
                    'borderWidth' => 1,
                    'borderRadius' => 4,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'enabled' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => __('dashboard.order_chart.y_axis_revenue'),
                    ],
                    'ticks' => [
                        'callback' => "function(value) {
                            return new Intl.NumberFormat('vi-VN', {
                                notation: 'compact',
                                compactDisplay: 'short'
                            }).format(value);
                        }",
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => __('dashboard.order_chart.y_axis_orders'),
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
