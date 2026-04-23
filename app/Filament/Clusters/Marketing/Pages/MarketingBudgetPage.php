<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Common\Constants\User\UserRole;
use App\Models\User;
use App\Services\Marketing\MarketingBudgetService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

class MarketingBudgetPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.clusters.marketing.pages.marketing-budget-page';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public array $report = ['rows' => [], 'summary' => []];

    protected MarketingBudgetService $marketingBudgetService;
    protected string $lastAlertSignature = '';

    public function boot(MarketingBudgetService $marketingBudgetService): void
    {
        $this->marketingBudgetService = $marketingBudgetService;
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
        return __('marketing.budget.navigation');
    }

    public function getTitle(): string
    {
        return __('marketing.budget.title');
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
            'budget_amount' => 0,
            'actual_spend' => 0,
            'fee_amount' => 0,
            'note' => '',
            'attachment_path' => [],
        ]);

        $this->refreshReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.budget.title'))
                    ->schema([
                        DatePicker::make('from_date')
                            ->label(__('marketing.budget.filters.from_date'))
                            ->required()
                            ->native(false)
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('marketing.budget.filters.from_date'),
                                ]),
                            ])
                            ->live(),
                        DatePicker::make('to_date')
                            ->label(__('marketing.budget.filters.to_date'))
                            ->required()
                            ->native(false)
                            ->extraInputAttributes(['required' => false])
                            ->afterOrEqual('from_date')
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('marketing.budget.filters.to_date'),
                                ]),
                                'after_or_equal' => __('marketing.budget.validation.invalid_date_range'),
                            ])
                            ->live(),
                        TextInput::make('channel')
                            ->label(__('marketing.budget.form.channel'))
                            ->required()
                            ->maxLength(100)
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('marketing.budget.form.channel'),
                                ]),
                                'max' => __('common.error.max_length', ['max' => 100]),
                            ])
                            ->live(debounce: 400),
                        TextInput::make('campaign')
                            ->label(__('marketing.budget.form.campaign'))
                            ->required()
                            ->maxLength(150)
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'required' => __('validation.required', [
                                    'attribute' => __('marketing.budget.form.campaign'),
                                ]),
                                'max' => __('common.error.max_length', ['max' => 150]),
                            ])
                            ->live(debounce: 400),
                        TextInput::make('budget_amount')
                            ->label(__('marketing.budget.form.budget_amount'))
                            ->validationAttribute(__('marketing.budget.form.budget_amount'))
                            ->rule('numeric')
                            ->rules(['min:0'])
                            ->default(0)
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->validationMessages([
                                'numeric' => __('validation.numeric', [
                                    'attribute' => __('marketing.budget.form.budget_amount'),
                                ]),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ]),
                        TextInput::make('actual_spend')
                            ->label(__('marketing.budget.form.actual_spend'))
                            ->validationAttribute(__('marketing.budget.form.actual_spend'))
                            ->rule('numeric')
                            ->rules(['min:0'])
                            ->default(0)
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->validationMessages([
                                'numeric' => __('validation.numeric', [
                                    'attribute' => __('marketing.budget.form.actual_spend'),
                                ]),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ]),
                        TextInput::make('fee_amount')
                            ->label(__('marketing.budget.form.fee_amount'))
                            ->validationAttribute(__('marketing.budget.form.fee_amount'))
                            ->rule('numeric')
                            ->rules(['min:0'])
                            ->default(0)
                            ->extraInputAttributes([
                                'type' => 'text',
                                'inputmode' => 'decimal',
                                'required' => false,
                                'min' => null,
                                'max' => null,
                                'step' => null,
                            ])
                            ->validationMessages([
                                'numeric' => __('validation.numeric', [
                                    'attribute' => __('marketing.budget.form.fee_amount'),
                                ]),
                                'min' => __('common.error.min_value', ['min' => 0]),
                            ]),
                        FileUpload::make('attachment_path')
                            ->label(__('marketing.budget.form.attachment'))
                            ->directory('marketing/spends')
                            ->multiple()
                            ->maxFiles(3),
                        Textarea::make('note')->label(__('marketing.budget.form.note'))->rows(2),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(MarketingBudgetService $marketingBudgetService): void
    {
        /** @var User $user */
        $user = Auth::user();
        $validatedState = $this->form->getState();
        $saveDate = $this->resolveSaveDate($validatedState);

        $marketingBudgetService->upsertDailyBudget([
            ...$validatedState,
            'date' => $saveDate,
        ], $user);
        $this->syncReportFiltersToSavedDate($saveDate);

        Notification::make()
            ->title(__('marketing.common.updated_success'))
            ->success()
            ->send();

        $this->refreshReport();
    }

    public function updatedData($value, ?string $key = null): void
    {
        if (!in_array($key, ['from_date', 'to_date', 'channel', 'campaign'], true)) {
            return;
        }

        if (!$this->hasValidReportDateRange()) {
            return;
        }

        $this->refreshReport();
    }

    public function refreshReport(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->report = $this->marketingBudgetService->summarize($this->getReportFilters(), $user);
        $this->notifyBudgetAlerts();
    }

    protected function getReportFilters(): array
    {
        return [
            'from_date' => $this->data['from_date'] ?? now()->startOfMonth()->toDateString(),
            'to_date' => $this->data['to_date'] ?? now()->toDateString(),
            'channel' => (string) ($this->data['channel'] ?? ''),
            'campaign' => (string) ($this->data['campaign'] ?? ''),
            'budget_amount' => $this->data['budget_amount'] ?? 0,
            'actual_spend' => $this->data['actual_spend'] ?? 0,
            'fee_amount' => $this->data['fee_amount'] ?? 0,
            'note' => (string) ($this->data['note'] ?? ''),
            'attachment_path' => $this->data['attachment_path'] ?? [],
        ];
    }

    protected function hasValidReportDateRange(): bool
    {
        $this->resetValidation([
            'data.from_date',
            'data.to_date',
        ]);

        $fromDate = $this->data['from_date'] ?? null;
        $toDate = $this->data['to_date'] ?? null;

        if (blank($fromDate)) {
            $this->addError('data.from_date', __('validation.required', [
                'attribute' => __('marketing.budget.filters.from_date'),
            ]));

            return false;
        }

        if (blank($toDate)) {
            $this->addError('data.to_date', __('validation.required', [
                'attribute' => __('marketing.budget.filters.to_date'),
            ]));

            return false;
        }

        try {
            $from = Carbon::parse((string) $fromDate);
            $to = Carbon::parse((string) $toDate);
        } catch (Throwable) {
            $this->addError('data.to_date', __('marketing.budget.validation.invalid_date_range'));

            return false;
        }

        if ($to->lt($from)) {
            $this->addError('data.to_date', __('marketing.budget.validation.invalid_date_range'));

            return false;
        }

        return true;
    }

    protected function resolveSaveDate(array $state): string
    {
        return (string) ($state['to_date'] ?? $state['from_date'] ?? now()->toDateString());
    }

    protected function syncReportFiltersToSavedDate(string $savedDate): void
    {
        $this->data['from_date'] = $savedDate;
        $this->data['to_date'] = $savedDate;
    }

    private function notifyBudgetAlerts(): void
    {
        $alertRows = collect($this->report['rows'] ?? [])
            ->filter(fn(array $row) => in_array((string) ($row['status'] ?? ''), ['over_budget', 'roi_low'], true))
            ->values();

        if ($alertRows->isEmpty()) {
            return;
        }

        $signature = sha1(json_encode($alertRows->map(fn(array $row) => [
            'date' => $row['date'] ?? '',
            'channel' => $row['channel'] ?? '',
            'campaign' => $row['campaign'] ?? '',
            'status' => $row['status'] ?? '',
        ])->all()));

        if ($signature === $this->lastAlertSignature) {
            return;
        }

        $this->lastAlertSignature = $signature;

        Notification::make()
            ->title(__('filament.integration.notifications.marketing_alert_title'))
            ->body(__('filament.integration.notifications.marketing_alert_body', ['count' => $alertRows->count()]))
            ->danger()
            ->send();
    }
}
