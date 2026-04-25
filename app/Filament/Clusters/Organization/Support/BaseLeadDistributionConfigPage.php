<?php

namespace App\Filament\Clusters\Organization\Support;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\DistributionMethod;
use App\Common\Constants\Team\TeamType;
use App\Models\LeadDistributionConfig as LeadDistributionConfigModel;
use App\Models\Team;
use App\Models\User;
use App\Services\LeadDistributionConfigService;
use App\Support\LeadDistributionConfigRuleMatrix;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

abstract class BaseLeadDistributionConfigPage extends Page
{
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.organization.resources.organizations.pages.lead-distribution-config';

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

        $result = $configService->getLeadDistributionConfig((int) $this->organizationId);

        if ($result->isError()) {
            Notification::make()
                ->danger()
                ->title(__('common.error.server_error'))
                ->body($result->getMessage())
                ->send();

            $this->form->fill($this->getDefaultFormState());

            return;
        }

        $this->config = $result->getData();
        $this->form->fill($this->getFormState());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament.user.basic_info'))
                    ->description(__('filament.lead.config.general_info_desc'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.lead.table.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(__('filament.lead.name_placeholder'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'max' => __('common.error.max_length', ['max' => 255]),
                                    ]),

                                Hidden::make('product_id')
                                    ->default(null),
                            ]),
                    ]),

                Section::make(__('filament.lead.rule.title'))
                    ->description(__('filament.lead.rule.fixed_description'))
                    ->icon('heroicon-o-clipboard-document-list')
                    ->hidden()
                    ->schema([
                        Repeater::make('rules')
                            ->label(__('filament.lead.rule.matrix_label'))
                            ->schema([
                                Hidden::make('customer_type'),
                                Hidden::make('staff_type'),
                                Grid::make(3)
                                    ->schema([
                                        Placeholder::make('customer_type_display')
                                            ->label(__('filament.lead.customer.label'))
                                            ->content(fn (Get $get): string => CustomerType::getLabel((int) $get('customer_type')) ?: '-'),

                                        Placeholder::make('staff_type_display')
                                            ->label(__('filament.lead.staff.type'))
                                            ->content(fn (Get $get): string => TeamType::getLabel((int) $get('staff_type')) ?: '-'),

                                        Select::make('distribution_method')
                                            ->label(__('filament.lead.distribution.label'))
                                            ->options(DistributionMethod::toOptions())
                                            ->required()
                                            ->native(false)
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                            ]),
                                    ]),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): string => $this->formatRuleLabel(
                                (int) ($state['customer_type'] ?? 0),
                                (int) ($state['staff_type'] ?? 0),
                            ))
                            ->columns(1)
                            ->collapsible(),
                    ]),

                $this->buildStaffSection(TeamType::SALE, 'staffSale'),
                $this->buildStaffSection(TeamType::CSKH, 'staffCSKH'),
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
        $data = $this->form->getState();
        $data['product_id'] = null;
        $data['rules'] = LeadDistributionConfigRuleMatrix::normalizeForForm($data['rules'] ?? []);

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
        $this->form->fill($this->getFormState());

        Notification::make()
            ->success()
            ->title(__('common.success.update_success'))
            ->send();
    }

    protected function getDefaultFormState(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'name' => '',
            'product_id' => null,
            'rules' => LeadDistributionConfigRuleMatrix::defaultRules(),
            'staffSale' => [],
            'staffCSKH' => [],
        ];
    }

    protected function getFormState(): array
    {
        if (! $this->config) {
            return $this->getDefaultFormState();
        }

        return [
            'name' => $this->config->name,
            'product_id' => null,
            'organization_id' => $this->config->organization_id,
            'rules' => LeadDistributionConfigRuleMatrix::normalizeForForm(
                $this->config->rules
                    ->map(fn ($rule): array => [
                        'customer_type' => $rule->customer_type,
                        'staff_type' => $rule->staff_type,
                        'distribution_method' => $rule->distribution_method,
                    ])
                    ->all(),
            ),
            'staffSale' => $this->mapStaffState($this->config->staffSale, TeamType::SALE),
            'staffCSKH' => $this->mapStaffState($this->config->staffCSKH, TeamType::CSKH),
        ];
    }

    protected function mapStaffState(Collection $staff, TeamType $teamType): array
    {
        return $staff
            ->map(fn ($member): array => [
                'staff_id' => $member->id,
                'team_id' => $member->teams
                    ->first(fn ($team) => (int) $team->type === $teamType->value)?->id,
                'weight' => $member->pivot?->weight ?? 1,
            ])
            ->values()
            ->all();
    }

    protected function buildStaffSection(TeamType $teamType, string $field): Section
    {
        $isSale = $teamType === TeamType::SALE;

        return Section::make($isSale ? __('filament.lead.staff.sale_title') : __('filament.lead.staff.cskh_title'))
            ->description(__('filament.lead.staff.weight_helper'))
            ->icon($isSale ? 'heroicon-o-user-group' : 'heroicon-o-users')
            ->schema([
                Repeater::make($field)
                    ->label($isSale ? __('filament.lead.staff.sale_label') : __('filament.lead.staff.cskh_label'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('team_id')
                                    ->label(__('filament.team.label'))
                                    ->options(fn (): array => $this->getTeamOptions($teamType))
                                    ->live()
                                    ->required()
                                    ->native(false)
                                    ->afterStateUpdated(fn ($state, Set $set) => $set('staff_id', null))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                Select::make('staff_id')
                                    ->label(__('filament.lead.staff.title'))
                                    ->options(fn (Get $get): array => $this->getStaffOptions($field, $get))
                                    ->required()
                                    ->searchable()
                                    ->native(false)
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),

                                TextInput::make('weight')
                                    ->label(__('filament.lead.staff.weight'))
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(1)
                                    ->required()
                                    ->helperText(__('filament.lead.staff.weight_helper'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'integer' => __('common.error.integer'),
                                        'min' => __('common.error.min_value', ['min' => 1]),
                                        'max' => __('common.error.max_value', ['max' => 100]),
                                    ]),
                            ]),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->addActionLabel(__('common.action.add'))
                    ->defaultItems(0)
                    ->minItems(1)
                    ->columns(1),
            ]);
    }

    protected function getTeamOptions(TeamType $teamType): array
    {
        return Team::query()
            ->where('organization_id', $this->organizationId)
            ->where('type', $teamType->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function getStaffOptions(string $field, Get $get): array
    {
        $teamId = (int) ($get('team_id') ?? 0);

        if ($teamId <= 0) {
            return [];
        }

        $selectedIds = collect($this->form->getRawState()[$field] ?? [])
            ->pluck('staff_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $currentId = (int) ($get('staff_id') ?? 0);

        if ($currentId > 0) {
            $selectedIds = array_values(array_diff($selectedIds, [$currentId]));
        }

        return User::query()
            ->where('organization_id', $this->organizationId)
            ->where('disable', false)
            ->whereHas('teams', fn ($query) => $query->where('teams.id', $teamId))
            ->when(
                $selectedIds !== [],
                fn ($query) => $query->whereNotIn('id', $selectedIds),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function formatRuleLabel(int $customerType, int $staffType): string
    {
        $customerLabel = CustomerType::getLabel($customerType) ?: __('filament.lead.rule.item.untitled');
        $staffLabel = TeamType::getLabel($staffType) ?: __('filament.lead.staff.type');

        return "{$customerLabel} -> {$staffLabel}";
    }
}
