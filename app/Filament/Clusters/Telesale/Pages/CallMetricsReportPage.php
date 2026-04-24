<?php

namespace App\Filament\Clusters\Telesale\Pages;

use App\Common\Constants\User\UserRole;
use App\Models\CustomerInteraction;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CallMetricsReportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-phone';
    protected string $view = 'filament.clusters.telesale.pages.call-metrics-report-page';
    protected static ?int $navigationSort = 14;
    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

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
                        DatePicker::make('from_date')
                            ->label(__('telesale.filters.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('telesale.filters.from_date'),
                                ]),
                            ]),
                        DatePicker::make('to_date')
                            ->label(__('telesale.filters.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('telesale.filters.to_date'),
                                ]),
                                'after_or_equal' => __('validation.after_or_equal', [
                                    'attribute' => __('telesale.filters.to_date'),
                                    'date' => __('telesale.filters.from_date'),
                                ]),
                            ]),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function generateReport(): void
    {
        $state = $this->getValidatedFilters();
        $from = $this->resolveDateBoundary($state['from_date'] ?? now()->startOfMonth()->toDateString(), true);
        $to = $this->resolveDateBoundary($state['to_date'] ?? now()->toDateString(), false);

        $query = CustomerInteraction::query()
            ->whereBetween('interacted_at', [$from, $to])
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

    protected function getValidationAttributes(): array
    {
        return [
            'data.from_date' => __('telesale.filters.from_date'),
            'data.to_date' => __('telesale.filters.to_date'),
        ];
    }

    protected function getValidatedFilters(): array
    {
        $validated = $this->validate(
            [
                'data.from_date' => ['bail', 'required', 'date'],
                'data.to_date' => ['bail', 'required', 'date', 'after_or_equal:data.from_date'],
            ],
            [
                'data.to_date.after_or_equal' => __('validation.after_or_equal', [
                    'attribute' => __('telesale.filters.to_date'),
                    'date' => __('telesale.filters.from_date'),
                ]),
            ],
            $this->getValidationAttributes(),
        );

        return $validated['data'];
    }

    protected function resolveDateBoundary(mixed $value, bool $isStart): string
    {
        $date = Carbon::parse($value);

        return $isStart
            ? $date->startOfDay()->toDateTimeString()
            : $date->endOfDay()->toDateTimeString();
    }
}
