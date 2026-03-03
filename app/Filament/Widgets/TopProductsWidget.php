<?php

namespace App\Filament\Widgets;

use App\Services\DashboardService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class TopProductsWidget extends ChartWidget
{
    protected static ?int $sort = 6;
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
        return __('dashboard.top_products.heading');
    }

    protected function getData(): array
    {
        $user = Auth::user();
        $service = app(DashboardService::class);
        $data = $service->getTopProducts($user->organization_id, $this->startDate, $this->endDate);

        if (empty($data['labels'])) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.top_products.quantity'),
                    'data' => $data['quantities'],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(168, 85, 247, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(14, 165, 233, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(20, 184, 166, 0.8)',
                        'rgba(107, 114, 128, 0.8)',
                    ],
                    'borderRadius' => 4,
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
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => __('dashboard.top_products.quantity'),
                    ],
                ],
            ],
        ];
    }
}
