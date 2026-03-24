<?php

namespace App\Filament\Clusters\Organization\Pages;

use App\Models\ShippingShop;
use App\Services\GHNService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use App\Services\ShippingConfigService;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use App\Common\Constants\Shipping\RequiredNote;
use App\Common\Constants\Shipping\ShiftGetGood;
use App\Common\Constants\User\UserRole;
use App\Utils\Helper;

class ShippingConfig extends Page
{
    protected string $view = 'filament.clusters.organization.resources.organizations.pages.shipping-config';

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ], Auth::user()->role);
    }

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_administration');
    }

    public ?array $data = [];

    public ?array $shops = [];

    public bool $isConnected = false;

    public static function getNavigationLabel(): string
    {
        return __('filament.shipping.navigation_label');
    }

    public function getTitle(): string
    {
        return __('filament.shipping.title');
    }

    public function mount(): void
    {
        /** @var ShippingConfigService $configService */
        $configService = app(ShippingConfigService::class);

        $result = $configService->getShippConfig(Auth::user()->organization_id);

        if ($result->isSuccess()) {
            $config = $result->getData();
            $this->form->fill($config->toArray());
            $this->isConnected = !empty($config->api_token);
        } else {
            $this->form->fill([
                'use_insurance' => false,
                'insurance_limit' => 0,
                'required_note' => RequiredNote::ALLOW_TO_TRY->value,
                'allow_cod_on_failed' => false,
                'default_pickup_shift' => ShiftGetGood::MORNING->value,
            ]);
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('filament.shipping.connection_info'))
                    ->description(__('filament.shipping.connection_info_description'))
                    ->schema([
                        TextInput::make('account_name')
                            ->label(__('filament.shipping.account_name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('filament.shipping.account_name_placeholder'))
                            ->helperText(__('filament.shipping.account_name_helper'))
                            ->live()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        TextInput::make('api_token')
                            ->label(__('filament.shipping.api_token'))
                            ->required()
                            ->maxLength(255)
                            ->password()
                            ->revealable()
                            ->placeholder(__('filament.shipping.api_token_placeholder'))
                            ->helperText(__('filament.shipping.api_token_helper'))
                            ->live()
                            ->suffixAction(
                                Action::make('testConnection')
                                    ->label(__('filament.shipping.test_connection'))
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('primary')
                                    ->requiresConfirmation(false)
                                    ->action('testConnection')
                            )
                            ->validationMessages([
                                'required' => __('common.error.required'),
                                'max' => __('common.error.max_length', ['max' => 255]),
                            ]),

                        Placeholder::make('connection_status')
                            ->label(__('filament.shipping.connection_status'))
                            ->content(fn() => $this->isConnected
                                ? __('filament.shipping.connected')
                                : __('filament.shipping.not_connected'))
                            ->visible(fn() => !empty($this->data['api_token'])),
                    ])
                    ->columns(2),

                Section::make(__('filament.shipping.shop_info'))
                    ->description(__('filament.shipping.shop_info_description'))
                    ->headerActions([
                        Action::make('sync_shops')
                            ->label(__('filament.shipping.sync_shops'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->action('syncShops')
                    ])
                    ->schema([
                        Select::make('default_store_id')
                            ->label(__('filament.shipping.default_store'))
                            ->options(function () {
                                return ShippingShop::where('organization_id', Auth::user()->organization_id)->pluck('name', 'shop_id')->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->placeholder(__('filament.shipping.select_store'))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),
                    ]),

                Section::make(__('filament.shipping.insurance_settings'))
                    ->description(__('filament.shipping.insurance_settings_description'))
                    ->schema([
                        Toggle::make('use_insurance')
                            ->label(__('filament.shipping.use_insurance'))
                            ->helperText(__('filament.shipping.use_insurance_helper'))
                            ->live()
                            ->inline(false),

                        TextInput::make('insurance_limit')
                            ->label(__('filament.shipping.insurance_limit'))
                            ->numeric()
                            ->prefix('₫')
                            ->step(10000)
                            ->minValue(0)
                            ->maxValue(10000000)
                            ->default(0)
                            ->disabled(fn($get) => !$get('use_insurance'))
                            ->helperText(__('filament.shipping.insurance_limit_helper'))
                            ->validationMessages([
                                'numeric' => __('common.error.numeric'),
                                'min' => __('common.error.min_value', ['min' => 0]),
                                'max' => __('common.error.max_value', ['max' => 10000000]),
                            ]),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make(__('filament.shipping.delivery_settings'))
                    ->description(__('filament.shipping.delivery_settings_description'))
                    ->schema([
                        Select::make('required_note')
                            ->label(__('filament.shipping.required_note'))
                            ->options(RequiredNote::getOptions())
                            ->required()
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        Toggle::make('allow_cod_on_failed')
                            ->label(__('filament.shipping.allow_cod_on_failed'))
                            ->default(false)
                            ->inline(false),

                        Select::make('default_pickup_shift')
                            ->label(__('filament.shipping.default_pickup_shift'))
                            ->options(ShiftGetGood::getOptions())
                            ->required()
                            ->helperText(__('filament.shipping.pickup_shift_helper'))
                            ->validationMessages([
                                'required' => __('common.error.required'),
                            ]),

                        TimePicker::make('default_pickup_time')
                            ->label(__('filament.shipping.default_pickup_time'))
                            ->seconds(false)
                            ->timezone('Asia/Ho_Chi_Minh')
                            ->format('H:i')
                            ->helperText(__('filament.shipping.pickup_time_helper')),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    /**
     * Test connection and fetch shops from GHN API
     */
    public function testConnection(): void
    {
        try {
            $this->validate([
                'data.account_name' => 'required|string',
                'data.api_token' => 'required|string',
            ]);

            Notification::make()
                ->title(__('filament.shipping.connecting'))
                ->info()
                ->send();

            /** @var GHNService $ghnService */
            $ghnService = app(GHNService::class);

            $result = $ghnService->testConnection($this->data);

            if ($result->isSuccess()) {
                $shops = $result->getData();
                $this->shops = $shops['shops'];
                $this->isConnected = true;

                Notification::make()
                    ->title(__('filament.shipping.connection_success'))
                    ->body(__('filament.shipping.found_shops', ['count' => count($this->shops)]))
                    ->success()
                    ->send();
            } else {
                $this->isConnected = false;
                $this->shops = [];

                Notification::make()
                    ->title(__('filament.shipping.connection_error'))
                    ->body($result->getMessage())
                    ->danger()
                    ->send();
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title(__('filament.shipping.validation_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (\Exception $e) {
            $this->isConnected = false;
            $this->shops = [];

            Notification::make()
                ->title(__('filament.shipping.connection_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Save shipping configuration
     */
    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Validate shops loaded
            if (empty($this->shops)) {
                Notification::make()
                    ->title(__('filament.shipping.test_connection_first'))
                    ->warning()
                    ->send();
                return;
            }

            /** @var ShippingConfigService $configService */
            $configService = app(ShippingConfigService::class);

            $configData = [
                'organization_id' => Auth::user()->organization_id,
                'account_name' => $data['account_name'],
                'api_token' => $data['api_token'],
                'default_store_id' => $data['default_store_id'],
                'use_insurance' => $data['use_insurance'] ?? false,
                'insurance_limit' => $data['insurance_limit'] ?? 0,
                'required_note' => $data['required_note'],
                'allow_cod_on_failed' => $data['allow_cod_on_failed'] ?? false,
                'default_pickup_shift' => $data['default_pickup_shift'],
                'default_pickup_time' => $data['default_pickup_time'] ?? null,
            ];

            $result = $configService->saveShippingConfig($configData);

            if ($result->isSuccess()) {
                Notification::make()
                    ->title(__('filament.shipping.saved_successfully'))
                    ->success()
                    ->send();

                // Reload config
                $this->mount();
            } else {
                Notification::make()
                    ->title(__('filament.shipping.save_error'))
                    ->body($result->getMessage())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('filament.shipping.save_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Đồng bộ danh sách hàng từ GHN về DB
     */
    public function syncShops(): void
    {
        try {
            $this->validate([
                'data.api_token' => 'required|string',
            ]);

            Notification::make()
                ->title(__('filament.shipping.syncing'))
                ->info()
                ->send();

            $ghnService = app(GHNService::class, [
                'organizationId' => Auth::user()->organization_id
            ]);

            $shops = $ghnService->syncShopsToDatabase(Auth::user()->organization_id);

            if (!empty($shops)) {
                Notification::make()
                    ->title(__('filament.shipping.sync_success'))
                    ->body(__('filament.shipping.found_shops', ['count' => count($shops)]))
                    ->success()
                    ->send();

                // Reload component
                $this->mount();
            } else {
                Notification::make()
                    ->title(__('filament.shipping.sync_error'))
                    ->body(__('filament.shipping.no_shops_found'))
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('filament.shipping.sync_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
