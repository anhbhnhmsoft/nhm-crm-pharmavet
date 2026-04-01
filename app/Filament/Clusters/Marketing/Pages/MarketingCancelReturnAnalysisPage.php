<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Order\GhnOrderStatus;
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
    public array $summary = [];
    public array $riskyCampaigns = [];

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
            'source_detail' => '',
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
                        TextInput::make('source_detail')->label(__('marketing.cancel_return.filters.source_detail'))->live(debounce: 400),
                    ])
                    ->columns(4),
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
            ->where(function ($query) {
                $query->where('orders.status', OrderStatus::CANCELLED->value)
                    ->orWhereIn('orders.ghn_status', [
                        GhnOrderStatus::WAITING_TO_RETURN->value,
                        GhnOrderStatus::RETURN->value,
                        GhnOrderStatus::RETURN_TRANSPORTING->value,
                        GhnOrderStatus::RETURN_SORTING->value,
                        GhnOrderStatus::RETURNING->value,
                        GhnOrderStatus::RETURN_FAIL->value,
                        GhnOrderStatus::RETURNED->value,
                    ])
                    ->orWhereRaw('LOWER(COALESCE(orders.shipping_exception_reason_code, "")) LIKE ?', ['%return%'])
                    ->orWhereRaw('LOWER(COALESCE(orders.shipping_exception_reason_code, "")) LIKE ?', ['%exchange%']);
            })
            ->selectRaw('COALESCE(NULLIF(customers.source, ""), ?) as source_name', [__('marketing.honor_board.unknown_source')])
            ->selectRaw('COALESCE(NULLIF(customers.source_detail, ""), ?) as source_detail_name', [__('marketing.honor_board.unknown_entity')])
            ->selectRaw('
                CASE
                    WHEN LOWER(COALESCE(orders.shipping_exception_reason_code, "")) LIKE "%exchange%" THEN "exchange"
                    WHEN orders.ghn_status IN (?, ?, ?, ?, ?, ?, ?) OR LOWER(COALESCE(orders.shipping_exception_reason_code, "")) LIKE "%return%" THEN "return"
                    ELSE "cancel"
                END as exception_type
            ', [
                GhnOrderStatus::WAITING_TO_RETURN->value,
                GhnOrderStatus::RETURN->value,
                GhnOrderStatus::RETURN_TRANSPORTING->value,
                GhnOrderStatus::RETURN_SORTING->value,
                GhnOrderStatus::RETURNING->value,
                GhnOrderStatus::RETURN_FAIL->value,
                GhnOrderStatus::RETURNED->value,
            ])
            ->selectRaw('COALESCE(NULLIF(orders.shipping_exception_reason_code, ""), ?) as reason', [__('marketing.honor_board.unknown_entity')])
            ->selectRaw('COUNT(orders.id) as orders_count')
            ->groupBy('source_name', 'source_detail_name', 'reason', 'exception_type');

        if (!empty($filters['source'])) {
            $query->where('customers.source', 'like', '%' . trim((string) $filters['source']) . '%');
        }

        if (!empty($filters['source_detail'])) {
            $query->where('customers.source_detail', 'like', '%' . trim((string) $filters['source_detail']) . '%');
        }

        $rows = $query->get();
        $total = max(1, $rows->sum('orders_count'));

        $this->rows = $rows->map(fn($row) => [
            'source' => (string) $row->source_name,
            'source_detail' => (string) $row->source_detail_name,
            'exception_type' => (string) $row->exception_type,
            'reason' => (string) $row->reason,
            'orders' => (int) $row->orders_count,
            'ratio' => round(((int) $row->orders_count / $total) * 100, 2),
        ])->sortByDesc('orders')->values()->all();

        $this->buildDashboardSlices($from, $to, $filters);
    }

    private function buildDashboardSlices(string $from, string $to, array $filters): void
    {
        $base = $this->orderRepository->query()
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.organization_id', Auth::user()->organization_id)
            ->whereBetween('orders.created_at', [$from, $to]);

        if (!empty($filters['source'])) {
            $base->where('customers.source', 'like', '%' . trim((string) $filters['source']) . '%');
        }
        if (!empty($filters['source_detail'])) {
            $base->where('customers.source_detail', 'like', '%' . trim((string) $filters['source_detail']) . '%');
        }

        $totalOrders = (clone $base)->count('orders.id');
        $cancelOrders = (clone $base)->where('orders.status', OrderStatus::CANCELLED->value)->count('orders.id');
        $returnOrExchangeOrders = (clone $base)
            ->where(function ($query) {
                $query->whereIn('orders.ghn_status', [
                    GhnOrderStatus::WAITING_TO_RETURN->value,
                    GhnOrderStatus::RETURN->value,
                    GhnOrderStatus::RETURN_TRANSPORTING->value,
                    GhnOrderStatus::RETURN_SORTING->value,
                    GhnOrderStatus::RETURNING->value,
                    GhnOrderStatus::RETURN_FAIL->value,
                    GhnOrderStatus::RETURNED->value,
                ])
                    ->orWhereRaw('LOWER(COALESCE(orders.shipping_exception_reason_code, "")) LIKE ?', ['%return%'])
                    ->orWhereRaw('LOWER(COALESCE(orders.shipping_exception_reason_code, "")) LIKE ?', ['%exchange%']);
            })
            ->count('orders.id');

        $exceptionTotal = max(1, $cancelOrders + $returnOrExchangeOrders);
        $this->summary = [
            'total_orders' => $totalOrders,
            'cancel_orders' => $cancelOrders,
            'return_exchange_orders' => $returnOrExchangeOrders,
            'cancel_rate' => $totalOrders > 0 ? round(($cancelOrders / $totalOrders) * 100, 2) : 0,
            'exception_rate' => $totalOrders > 0 ? round((($cancelOrders + $returnOrExchangeOrders) / $totalOrders) * 100, 2) : 0,
            'junk_lead_rate' => round((($cancelOrders + $returnOrExchangeOrders) / $exceptionTotal) * 100, 2),
        ];

        $campaignRows = (clone $base)
            ->selectRaw('COALESCE(NULLIF(customers.source_detail, ""), ?) as campaign', [__('marketing.honor_board.unknown_entity')])
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('SUM(CASE WHEN orders.status = ? THEN 1 ELSE 0 END) as cancel_orders', [OrderStatus::CANCELLED->value])
            ->selectRaw('SUM(CASE WHEN orders.ghn_status IN (?, ?, ?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as return_orders', [
                GhnOrderStatus::WAITING_TO_RETURN->value,
                GhnOrderStatus::RETURN->value,
                GhnOrderStatus::RETURN_TRANSPORTING->value,
                GhnOrderStatus::RETURN_SORTING->value,
                GhnOrderStatus::RETURNING->value,
                GhnOrderStatus::RETURN_FAIL->value,
                GhnOrderStatus::RETURNED->value,
            ])
            ->groupBy('campaign')
            ->get();

        $this->riskyCampaigns = $campaignRows->map(function ($row) {
            $total = max(1, (int) $row->total_orders);
            $riskOrders = (int) $row->cancel_orders + (int) $row->return_orders;
            $riskRate = round(($riskOrders / $total) * 100, 2);

            return [
                'campaign' => (string) $row->campaign,
                'total_orders' => (int) $row->total_orders,
                'risk_orders' => $riskOrders,
                'risk_rate' => $riskRate,
            ];
        })
            ->filter(fn(array $row) => $row['risk_rate'] >= 30)
            ->sortByDesc('risk_rate')
            ->take(10)
            ->values()
            ->all();
    }
}
