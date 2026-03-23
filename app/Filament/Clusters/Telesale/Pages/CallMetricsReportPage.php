<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\TelesaleCluster;
use App\Models\CustomerInteraction;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CallMetricsReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';
    protected string $view = 'filament.clusters.telesale.pages.call-metrics-report-page';
    protected static ?int $navigationSort = 14;
    protected static string|null $cluster = TelesaleCluster::class;

    public ?array $data = [];
    public array $metrics = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
        ]);
        $this->generateReport();
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.reports.call_metrics_navigation');
    }

    public function getTitle(): string
    {
        return __('telesale.reports.call_metrics_title');
    }

    public static function canAccess(): bool
    {
        return in_array(Auth::user()->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.report.filter_section'))
                    ->schema([
                        DatePicker::make('from_date')->label(__('telesale.filters.from_date'))->native(false),
                        DatePicker::make('to_date')->label(__('telesale.filters.to_date'))->native(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $state = $this->form->getState();

        $query = CustomerInteraction::query()
            ->whereBetween('interacted_at', [
                ($state['from_date'] ?? now()->startOfMonth()->toDateString()) . ' 00:00:00',
                ($state['to_date'] ?? now()->toDateString()) . ' 23:59:59',
            ])
            ->where('type', 1);

        $totalCalls = (clone $query)->count();
        $totalDuration = (int) (clone $query)->sum('duration');
        $connectedCalls = (clone $query)->whereNotNull('duration')->where('duration', '>', 0)->count();

        $this->metrics = [
            'total_calls' => $totalCalls,
            'total_duration' => $totalDuration,
            'connected_calls' => $connectedCalls,
            'avg_duration' => $connectedCalls > 0 ? round($totalDuration / $connectedCalls, 2) : 0,
        ];
    }
}
