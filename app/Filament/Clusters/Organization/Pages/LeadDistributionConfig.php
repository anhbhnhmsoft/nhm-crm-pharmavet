<?php

namespace App\Filament\Clusters\Organization\Pages;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Team\TeamType;
use App\Models\LeadDistributionConfig as LeadDistributionConfigModel;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\User\UserRole;
use App\Models\Team;
use App\Models\User;
use App\Models\Product;
use App\Services\LeadDistributionConfigService;
use App\Utils\Helper;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class LeadDistributionConfig extends Page
{
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.organization.resources.organizations.pages.lead-distribution-config';

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], Auth::user()->role);
    }

    public ?array $data = [];
    public ?LeadDistributionConfigModel $config = null;
    public ?int $organizationId = null;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.lead.label');
    }

    public function getTitle(): string
    {
        return __('filament.lead.label');
    }

    public function mount(): void
    {
        $this->organizationId = Auth::user()->organization_id;

        /** @var LeadDistributionConfigService $configService */
        $configService = app(LeadDistributionConfigService::class);

        $result = $configService->getLeadDistributionConfig($this->organizationId);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('common.error.server_error'))
                ->body($result->getMessage())
                ->send();
            return;
        }

        /** @var LeadDistributionConfig|null $config */
        $this->config = $result->getData();

        if (! $this->config) {
            $this->form->fill([
                'organization_id' => $this->organizationId,
                'name' => '',
                'product_id' => null,
                'rules' => [],
                'staffSale' => [],
                'staffCSKH' => [],
            ]);
            return;
        }

        $this->data = [
            'name' => $this->config->name,
            'product_id' => $this->config->product_id,
            'organization_id' => $this->config->organization_id,
            'rules' => $this->config->rules->map(fn($rule) => [
                'id' => $rule->id,
                'field' => $rule->field,
                'operator' => $rule->operator,
                'value' => $rule->value,
            ])->toArray(),
            'staffSale' => $this->config->staffSale->map(fn($staff) => [
                'staff_id' => $staff->id,
                'team_id' => $staff->teams->first(fn($team) => $team->type == TeamType::SALE->value)?->id,
                'weight' => $staff->pivot?->weight,
            ])->toArray(),
            'staffCSKH' => $this->config->staffCSKH->map(fn($staff) => [
                'staff_id' => $staff->id,
                'team_id' => $staff->teams->first(fn($team) => $team->type == TeamType::CSKH->value)?->id,
                'weight' => $staff->pivot?->weight,
            ])->toArray(),
        ];

        $this->form->fill($this->data);
    }


    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament.user.basic_info'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.lead.table.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'max' => __('common.error.max_length', ['max' => 255])
                                    ]),

                                Select::make('product_id')
                                    ->label(__('filament.lead.table.product'))
                                    ->options(
                                        fn() => Product::query()
                                            ->where('organization_id', $this->organizationId)
                                            ->where('is_business_product', true)
                                            ->pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                            ]),
                    ]),

                Section::make(__('filament.lead.rule.title'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Repeater::make('rules')
                            ->label(__('filament.lead.rule.label'))
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('customer_type')
                                            ->label(__('filament.lead.customer.label'))
                                            ->options(CustomerType::toOptions())
                                            ->required()
                                            ->disabled()
                                            ->native(false)
                                            ->live()
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),

                                        Select::make('staff_type')
                                            ->label(__('filament.lead.staff.type'))
                                            ->options([
                                                TeamType::SALE->value => TeamType::SALE->label(),
                                                TeamType::BILL_OF_LADING->value => TeamType::BILL_OF_LADING->label(),
                                            ])
                                            ->required()
                                            ->disabled()
                                            ->native(false)
                                            ->live()
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),

                                        Select::make('distribution_method')
                                            ->label(__('filament.lead.distribution.label'))
                                            ->options(DistributionMethod::toOptions())
                                            ->required()
                                            ->native(false)
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),
                                    ]),
                            ])
                            ->reorderable()
                            ->addActionLabel(__('common.action.add'))
                            ->defaultItems(0)
                            ->deletable(false)
                            ->minItems(1)
                            ->maxItems(6)
                            ->columns(1)
                            ->collapsible(),
                    ]),

                Section::make(__('filament.lead.staff.sale_title'))
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Repeater::make('staffSale')
                            ->label(__('filament.lead.staff.sale_label'))
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('team_id')
                                            ->label(__('filament.team.label'))
                                            ->options(
                                                fn() =>
                                                Team::where('type', TeamType::SALE->value)
                                                    ->where('organization_id', $this->organizationId)
                                                    ->pluck('name', 'id')
                                            )
                                            ->live()
                                            ->required()
                                            ->native(false)
                                            ->afterStateUpdated(fn($state, Set $set) => $set('id', null))
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),

                                        Select::make('staff_id')
                                            ->label(__('filament.lead.staff.title'))
                                            ->options(function (Get $get) {
                                                $teamId = $get('team_id');
                                                if (!$teamId) return [];

                                                $allItems =  $this->form->getRawState()['staffSale'] ?? [];
                                                $selectedIds = collect($allItems)
                                                    ->pluck('staff_id')
                                                    ->filter()
                                                    ->toArray();

                                                $currentId = $get('staff_id');
                                                $selectedIds = array_diff($selectedIds, [$currentId]);

                                                return User::query()
                                                    ->whereHas('teams', fn($q) => $q->where('teams.id', $teamId))
                                                    ->where('disable', false)
                                                    ->when(!empty($selectedIds), fn($query) => $query->whereNotIn('id', $selectedIds))
                                                    ->pluck('name', 'id');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->native(false)
                                            ->live()
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),

                                        TextInput::make('weight')
                                            ->label(__('filament.lead.staff.weight'))
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(100)
                                            ->default(1)
                                            ->required()
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                                'min' => __('common.error.min_value', ['min' => 1]),
                                                'max' => __('common.error.max_value', ['max' => 100]),
                                                'numeric' => __('common.error.numeric'),
                                            ])
                                    ]),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->addActionLabel(__('common.action.add'))
                            ->defaultItems(0)
                            ->minItems(1)
                            ->columns(1),
                    ]),

                Section::make(__('filament.lead.staff.cskh_title'))
                    ->icon('heroicon-o-users')
                    ->schema([
                        Repeater::make('staffCSKH')
                            ->label(__('filament.lead.staff.cskh_label'))
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        Select::make('team_id')
                                            ->label(__('filament.team.label'))
                                            ->options(
                                                fn() =>
                                                Team::where('type', TeamType::CSKH->value)
                                                    ->where('organization_id', $this->organizationId)
                                                    ->pluck('name', 'id')
                                            )
                                            ->live()
                                            ->required()
                                            ->native(false)
                                            ->afterStateUpdated(fn($state, Set $set) => $set('id', null))
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),

                                        Select::make('staff_id')
                                            ->label(__('filament.lead.staff.title'))
                                            ->options(function ($state, Get $get) {
                                                $teamId = $get('team_id');
                                                if (!$teamId) return [];

                                                $allItems =  $this->form->getRawState()['staffCSKH'] ?? [];
                                                $selectedIds = collect($allItems)
                                                    ->pluck('staff_id')
                                                    ->filter()
                                                    ->toArray();

                                                $currentId = $get('staff_id');
                                                $selectedIds = array_diff($selectedIds, [$currentId]);

                                                return User::query()
                                                    ->whereHas('teams', fn($q) => $q->where('teams.id', $teamId))
                                                    ->where('disable', false)
                                                    ->when(!empty($selectedIds), fn($query) => $query->whereNotIn('id', $selectedIds))
                                                    ->pluck('name', 'id');
                                            })
                                            ->required()
                                            ->searchable()
                                            ->native(false)
                                            ->live()
                                            ->validationMessages([
                                                'required' => __('common.error.required')
                                            ]),

                                        TextInput::make('weight')
                                            ->label(__('filament.lead.staff.weight'))
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(100)
                                            ->default(1)
                                            ->required()
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                                'min' => __('common.error.min_value', ['min' => 1]),
                                                'max' => __('common.error.max_value', ['max' => 100]),
                                                'numeric' => __('common.error.numeric'),
                                            ])
                                    ]),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->addActionLabel(__('common.action.add'))
                            ->defaultItems(0)
                            ->minItems(1)
                            ->columns(1),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.action.save'))
                ->action('save')
                ->keyBindings(['mod+s'])
                ->color('primary')
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->modalHeading(__('filament.lead.confirm_save'))
                ->modalDescription(__('filament.lead.confirm_save_description'))
                ->modalSubmitActionLabel(__('common.action.save')),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getRawState();

        if (empty($data['name'])) {
            Notification::make()
                ->warning()
                ->title(__('common.error.validation_failed'))
                ->send();
            return;
        }

        /** @var LeadDistributionConfigService $service */
        $service = app(LeadDistributionConfigService::class);

        $result = $service->saveLeadDistributionConfig($this->config, $data, $this->organizationId);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('common.error.server_error'))
                ->body($result->getMessage())
                ->persistent()
                ->send();
            return;
        }

        $this->config = $result->getData();

        Notification::make()
            ->success()
            ->title(__('common.success.update_success'))
            ->send();
    }
}
