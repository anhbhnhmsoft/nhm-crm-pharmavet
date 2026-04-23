<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Common\Constants\User\UserRole;
use App\Models\User;
use App\Services\Marketing\MarketingKpiService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Throwable;

class MarketingKpiDashboardPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    protected string $view = 'filament.clusters.marketing.pages.marketing-kpi-dashboard-page';

    protected static ?int $navigationSort = 4;

    public ?array $data = [];

    public array $dashboard = ['cards' => [], 'rows' => []];

    protected MarketingKpiService $marketingKpiService;

    public function boot(MarketingKpiService $marketingKpiService): void
    {
        $this->marketingKpiService = $marketingKpiService;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('marketing.features.budget_kpi_v1', false);
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.kpi.navigation');
    }

    public function getTitle(): string
    {
        return __('marketing.kpi.title');
    }

    public static function canAccess(): bool
    {
        return config('marketing.features.budget_kpi_v1', false)
            && Auth::check()
            && in_array(Auth::user()->role, [
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
                UserRole::MARKETING->value,
                UserRole::SALE->value,
            ], true);
    }

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'channel' => '',
            'campaign' => '',
        ]);

        $this->generateReport(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.kpi.title'))
                    ->schema([
                        DatePicker::make('from_date')
                            ->label(__('marketing.kpi.filters.from_date'))
                            ->required()
                            ->native(false)
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('marketing.kpi.filters.from_date'),
                                ]),
                            ]),
                        DatePicker::make('to_date')
                            ->label(__('marketing.kpi.filters.to_date'))
                            ->required()
                            ->native(false)
                            ->afterOrEqual('from_date')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('marketing.kpi.filters.to_date'),
                                ]),
                                'after_or_equal' => __('marketing.kpi.validation.invalid_date_range'),
                            ]),
                        TextInput::make('channel')
                            ->label(__('marketing.kpi.filters.channel'))
                            ->maxLength(100),
                        TextInput::make('campaign')
                            ->label(__('marketing.kpi.filters.campaign'))
                            ->maxLength(150),
                    ])
                    ->columns(4),
            ])
            ->statePath('data');
    }

    public function generateReport(bool $shouldNotify = true): void
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            $this->dashboard = $this->marketingKpiService->buildDashboard($this->getValidatedFilters(), $user);

            if ($shouldNotify) {
                Notification::make()
                    ->success()
                    ->title(__('marketing.kpi.notifications.generate_success'))
                    ->send();
            }
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('filament.common.error'))
                ->body(__('marketing.kpi.notifications.generate_failed'))
                ->send();
        }
    }

    protected function getValidatedFilters(): array
    {
        $validated = $this->validate(
            [
                'data.from_date' => ['bail', 'required', 'date'],
                'data.to_date' => ['bail', 'required', 'date', 'after_or_equal:data.from_date'],
                'data.channel' => ['nullable', 'string', 'max:100'],
                'data.campaign' => ['nullable', 'string', 'max:150'],
            ],
            [
                'data.to_date.after_or_equal' => __('marketing.kpi.validation.invalid_date_range'),
            ],
            $this->getValidationAttributes(),
        );

        return $validated['data'];
    }

    protected function getValidationAttributes(): array
    {
        return [
            'data.from_date' => __('marketing.kpi.filters.from_date'),
            'data.to_date' => __('marketing.kpi.filters.to_date'),
            'data.channel' => __('marketing.kpi.filters.channel'),
            'data.campaign' => __('marketing.kpi.filters.campaign'),
        ];
    }
}
