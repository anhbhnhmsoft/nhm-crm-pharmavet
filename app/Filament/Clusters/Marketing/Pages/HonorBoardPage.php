<?php

namespace App\Filament\Clusters\Marketing\Pages;

use App\Common\Constants\User\UserRole;
use App\Models\MarketingScoringRuleSet;
use App\Models\PushsaleRuleSet;
use App\Models\Team;
use App\Models\User;
use App\Services\Telesale\HonorBoardService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class HonorBoardPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected string $view = 'filament.clusters.marketing.pages.honor-board-page';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public array $board = [
        'sale' => ['top3' => [], 'list' => []],
        'telesale' => ['top3' => [], 'list' => []],
        'marketing' => ['top3' => [], 'list' => []],
    ];

    public array $suggestions = [];

    protected bool $syncingState = false;

    protected HonorBoardService $honorBoardService;

    public function boot(HonorBoardService $honorBoardService): void
    {
        $this->honorBoardService = $honorBoardService;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('marketing.features.ranking_v2', false);
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_marketing');
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.honor_board.navigation');
    }

    public function getTitle(): string
    {
        return __('marketing.honor_board.title');
    }

    public static function canAccess(): bool
    {
        return config('marketing.features.ranking_v2', false)
            && Auth::check()
            && in_array(Auth::user()->role, [
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::WAREHOUSE->value,
            UserRole::ACCOUNTING->value,
            UserRole::MARKETING->value,
            UserRole::SALE->value,
        ], true);
    }

    public function mount(): void
    {
        $savedState = session()->get($this->getSessionKey(), []);
        $defaults = $this->defaultFilters();
        $state = array_merge($defaults, array_intersect_key((array) $savedState, $defaults));

        $this->syncingState = true;
        $this->form->fill($state);
        $this->syncingState = false;

        $this->refreshBoard();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('marketing.honor_board.filters.title'))
                    ->schema([
                        Select::make('team_id')
                            ->label(__('marketing.honor_board.filters.team'))
                            ->options(fn() => Team::query()
                                ->where('organization_id', Auth::user()->organization_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->placeholder(__('marketing.honor_board.filters.all_team'))
                            ->searchable()
                            ->live(),
                        Select::make('staff_id')
                            ->label(__('marketing.honor_board.filters.staff'))
                            ->options(fn() => User::query()
                                ->where('organization_id', Auth::user()->organization_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->placeholder(__('marketing.honor_board.filters.all_staff'))
                            ->searchable()
                            ->live(),
                        Select::make('pushsale_rule_set_id')
                            ->label(__('marketing.honor_board.filters.pushsale_rule_set'))
                            ->options(fn() => PushsaleRuleSet::query()
                                ->where('organization_id', Auth::user()->organization_id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->placeholder(__('marketing.honor_board.filters.all_pushsale'))
                            ->searchable()
                            ->live(),
                        Select::make('scoring_rule_set_id')
                            ->label(__('marketing.honor_board.filters.scoring_rule_set'))
                            ->options(fn() => MarketingScoringRuleSet::query()
                                ->where('organization_id', Auth::user()->organization_id)
                                ->where('is_active', true)
                                ->orderByDesc('is_default')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->placeholder(__('marketing.honor_board.filters.all_scoring_rule_set'))
                            ->searchable()
                            ->live(),
                        Select::make('revenue_mode')
                            ->label(__('marketing.honor_board.filters.revenue_mode'))
                            ->options([
                                'before_discount' => __('marketing.honor_board.revenue_mode.before_discount'),
                                'after_discount' => __('marketing.honor_board.revenue_mode.after_discount'),
                            ])
                            ->default('after_discount')
                            ->native(false)
                            ->live(),
                        Select::make('date_preset')
                            ->label(__('marketing.honor_board.filters.date_preset'))
                            ->options([
                                'today' => __('marketing.honor_board.date_preset.today'),
                                'this_week' => __('marketing.honor_board.date_preset.this_week'),
                                'this_month' => __('marketing.honor_board.date_preset.this_month'),
                                'custom' => __('marketing.honor_board.date_preset.custom'),
                            ])
                            ->default('this_month')
                            ->native(false)
                            ->live(),
                        DatePicker::make('from_date')
                            ->label(__('marketing.honor_board.filters.from_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->live(),
                        DatePicker::make('to_date')
                            ->label(__('marketing.honor_board.filters.to_date'))
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('from_date')
                            ->live(),
                        TextInput::make('q')
                            ->label(__('marketing.honor_board.filters.search'))
                            ->placeholder(__('marketing.honor_board.filters.search_placeholder'))
                            ->live(debounce: 300),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function updatedData($value, ?string $key = null): void
    {
        if ($this->syncingState) {
            return;
        }

        if ($key === 'date_preset') {
            $this->applyDatePreset((string) $value);
        }

        $this->refreshBoard();
    }

    public function applySuggestion(string $value): void
    {
        $this->data['q'] = $value;
        $this->refreshBoard();
    }

    public function clearSearch(): void
    {
        $this->data['q'] = '';
        $this->refreshBoard();
    }

    private function refreshBoard(): void
    {
        /** @var \App\Models\User $viewer */
        $viewer = Auth::user();

        $result = $this->honorBoardService->buildBoard($this->form->getState(), $viewer);

        $this->syncingState = true;
        $this->form->fill($result['filters']);
        $this->syncingState = false;

        $this->board = [
            'sale' => $result['sale'],
            'telesale' => $result['telesale'],
            'marketing' => $result['marketing'],
        ];
        $this->suggestions = $result['suggestions'];

        session()->put($this->getSessionKey(), $result['filters']);
    }

    private function defaultFilters(): array
    {
        return [
            'team_id' => null,
            'staff_id' => null,
            'pushsale_rule_set_id' => null,
            'scoring_rule_set_id' => null,
            'revenue_mode' => 'after_discount',
            'date_preset' => 'this_month',
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->endOfMonth()->toDateString(),
            'q' => '',
        ];
    }

    private function applyDatePreset(string $preset): void
    {
        if ($preset === 'today') {
            $this->data['from_date'] = now()->toDateString();
            $this->data['to_date'] = now()->toDateString();

            return;
        }

        if ($preset === 'this_week') {
            $this->data['from_date'] = now()->startOfWeek(Carbon::MONDAY)->toDateString();
            $this->data['to_date'] = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

            return;
        }

        if ($preset === 'this_month') {
            $this->data['from_date'] = now()->startOfMonth()->toDateString();
            $this->data['to_date'] = now()->endOfMonth()->toDateString();

            return;
        }

        if (empty($this->data['from_date']) || empty($this->data['to_date'])) {
            $this->data['from_date'] = now()->startOfMonth()->toDateString();
            $this->data['to_date'] = now()->endOfMonth()->toDateString();
        }
    }

    private function getSessionKey(): string
    {
        return 'marketing.honor_board.filters.' . (string) Auth::id();
    }
}
