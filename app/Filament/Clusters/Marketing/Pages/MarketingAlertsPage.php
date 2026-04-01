<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Common\Constants\User\UserRole;
use App\Repositories\MarketingAlertLogRepository;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class MarketingAlertsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected string $view = 'filament.clusters.marketing.pages.marketing-alerts-page';

    protected static ?int $navigationSort = 6;

    public ?array $data = [];

    public array $alerts = [];

    protected MarketingAlertLogRepository $marketingAlertLogRepository;

    public function boot(MarketingAlertLogRepository $marketingAlertLogRepository): void
    {
        $this->marketingAlertLogRepository = $marketingAlertLogRepository;
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
        return __('marketing.alert_center.navigation');
    }

    public function getTitle(): string
    {
        return __('marketing.alert_center.title');
    }

    public static function canAccess(): bool
    {
        return config('marketing.features.budget_kpi_v1', false)
            && Auth::check()
            && in_array(Auth::user()->role, [
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
                UserRole::MARKETING->value,
            ], true);
    }

    public function mount(): void
    {
        $this->form->fill([
            'status' => 'open',
            'severity' => '',
            'alert_type' => '',
        ]);

        $this->refreshAlerts();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.alert_center.title'))
                    ->schema([
                        Select::make('status')
                            ->label(__('marketing.alert_center.filters.status'))
                            ->options([
                                'open' => __('marketing.alert_center.status.open'),
                                'resolved' => __('marketing.alert_center.status.resolved'),
                                'all' => __('marketing.alert_center.status.all'),
                            ])
                            ->default('open')
                            ->native(false)
                            ->live(),
                        Select::make('severity')
                            ->label(__('marketing.alert_center.filters.severity'))
                            ->options([
                                'high' => __('marketing.alert_center.severity.high'),
                                'warning' => __('marketing.alert_center.severity.warning'),
                            ])
                            ->placeholder(__('marketing.alert_center.filters.all_option'))
                            ->native(false)
                            ->live(),
                        Select::make('alert_type')
                            ->label(__('marketing.alert_center.filters.alert_type'))
                            ->options([
                                'over_budget' => __('marketing.alert_center.alert_type.over_budget'),
                                'low_roi' => __('marketing.alert_center.alert_type.low_roi'),
                                'spend_without_lead' => __('marketing.alert_center.alert_type.spend_without_lead'),
                            ])
                            ->placeholder(__('marketing.alert_center.filters.all_option'))
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function updatedData(): void
    {
        $this->refreshAlerts();
    }

    public function markResolved(int $id): void
    {
        $alert = $this->marketingAlertLogRepository->find($id);
        if (!$alert || (int) $alert->organization_id !== (int) Auth::user()->organization_id) {
            return;
        }

        $alert->update(['resolved_at' => now()]);

        Notification::make()
            ->title(__('marketing.common.updated_success'))
            ->success()
            ->send();

        $this->refreshAlerts();
    }

    public function reopen(int $id): void
    {
        $alert = $this->marketingAlertLogRepository->find($id);
        if (!$alert || (int) $alert->organization_id !== (int) Auth::user()->organization_id) {
            return;
        }

        $alert->update(['resolved_at' => null]);

        Notification::make()
            ->title(__('marketing.common.updated_success'))
            ->success()
            ->send();

        $this->refreshAlerts();
    }

    private function refreshAlerts(): void
    {
        $filters = $this->form->getState();

        $query = $this->marketingAlertLogRepository->query()
            ->where('organization_id', Auth::user()->organization_id)
            ->when(!empty($filters['severity']), fn($q) => $q->where('severity', $filters['severity']))
            ->when(!empty($filters['alert_type']), fn($q) => $q->where('alert_type', $filters['alert_type']))
            ->orderByDesc('triggered_at')
            ->orderByDesc('id');

        $status = (string) ($filters['status'] ?? 'open');
        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        $this->alerts = $query->limit(200)->get()->map(fn($row) => [
            'id' => (int) $row->id,
            'alert_type' => (string) $row->alert_type,
            'severity' => (string) $row->severity,
            'channel' => (string) ($row->channel ?? ''),
            'campaign' => (string) ($row->campaign ?? ''),
            'triggered_at' => $row->triggered_at?->format('Y-m-d H:i:s'),
            'resolved_at' => $row->resolved_at?->format('Y-m-d H:i:s'),
            'payload_json' => (array) ($row->payload_json ?? []),
        ])->all();
    }
}
