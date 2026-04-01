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
use Illuminate\Support\Facades\Auth;

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
            'date' => now()->toDateString(),
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
                        DatePicker::make('date')->label(__('marketing.budget.form.date'))->required()->native(false),
                        DatePicker::make('from_date')->label(__('marketing.budget.filters.from_date'))->required()->native(false)->live(),
                        DatePicker::make('to_date')->label(__('marketing.budget.filters.to_date'))->required()->native(false)->afterOrEqual('from_date')->live(),
                        TextInput::make('channel')->label(__('marketing.budget.form.channel'))->required()->maxLength(100)->live(debounce: 400),
                        TextInput::make('campaign')->label(__('marketing.budget.form.campaign'))->required()->maxLength(150)->live(debounce: 400),
                        TextInput::make('budget_amount')->label(__('marketing.budget.form.budget_amount'))->numeric()->minValue(0)->default(0),
                        TextInput::make('actual_spend')->label(__('marketing.budget.form.actual_spend'))->numeric()->minValue(0)->default(0),
                        TextInput::make('fee_amount')->label(__('marketing.budget.form.fee_amount'))->numeric()->minValue(0)->default(0),
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

        $marketingBudgetService->upsertDailyBudget($this->form->getState(), $user);

        Notification::make()
            ->title(__('marketing.common.updated_success'))
            ->success()
            ->send();

        $this->refreshReport();
    }

    public function updatedData($value, ?string $key = null): void
    {
        if (in_array($key, ['from_date', 'to_date', 'channel', 'campaign'], true)) {
            $this->refreshReport();
        }
    }

    public function refreshReport(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->report = $this->marketingBudgetService->summarize($this->form->getState(), $user);
        $this->notifyBudgetAlerts();
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
