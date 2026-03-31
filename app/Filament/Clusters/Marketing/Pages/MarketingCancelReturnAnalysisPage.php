<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\User\UserRole;
use App\Repositories\OrderRepository;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class MarketingCancelReturnAnalysisPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected string $view = 'filament.clusters.marketing.pages.marketing-cancel-return-analysis-page';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public array $rows = [];

    protected OrderRepository $orderRepository;

    public function boot(OrderRepository $orderRepository): void
    {
        $this->orderRepository = $orderRepository;
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
        return __('marketing.cancel_return.navigation');
    }

    public function getTitle(): string
    {
        return __('marketing.cancel_return.title');
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
            'source' => '',
        ]);

        $this->refreshRows();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.cancel_return.title'))
                    ->schema([
                        DatePicker::make('from_date')->label(__('marketing.cancel_return.filters.from_date'))->required()->native(false)->live(),
                        DatePicker::make('to_date')->label(__('marketing.cancel_return.filters.to_date'))->required()->native(false)->afterOrEqual('from_date')->live(),
                        TextInput::make('source')->label(__('marketing.cancel_return.filters.source'))->live(debounce: 400),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function updatedData(): void
    {
        $this->refreshRows();
    }

    public function refreshRows(): void
    {
        $filters = $this->form->getState();
        $from = ($filters['from_date'] ?? now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $to = ($filters['to_date'] ?? now()->toDateString()) . ' 23:59:59';

        $query = $this->orderRepository->query()
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.organization_id', Auth::user()->organization_id)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereIn('orders.status', [OrderStatus::CANCELLED->value])
            ->selectRaw('COALESCE(NULLIF(customers.source, ""), ?) as source_name', [__('marketing.honor_board.unknown_source')])
            ->selectRaw('COALESCE(NULLIF(orders.shipping_exception_reason_code, ""), ?) as reason', [__('marketing.honor_board.unknown_entity')])
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->groupBy('source_name', 'reason');

        if (!empty($filters['source'])) {
            $query->where('customers.source', 'like', '%' . trim((string) $filters['source']) . '%');
        }

        $rows = $query->get();
        $total = max(1, $rows->sum('orders_count'));

        $this->rows = $rows->map(fn($row) => [
            'source' => (string) $row->source_name,
            'reason' => (string) $row->reason,
            'orders' => (int) $row->orders_count,
            'ratio' => round(((int) $row->orders_count / $total) * 100, 2),
        ])->sortByDesc('orders')->values()->all();
    }
}
