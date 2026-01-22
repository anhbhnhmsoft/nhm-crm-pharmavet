<?php

namespace App\Filament\Clusters\Warehouse\Resources\Warehouses\Tables;

use App\Common\Constants\Shipping\RequiredNote;
use App\Common\Constants\Shipping\ShiftGetGood;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use App\Services\GhnShippingService;
use App\Services\GHNService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('warehouse.form.code'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('warehouse.form.name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('warehouse.form.phone'))
                    ->searchable(),
                TextColumn::make('province.name')
                    ->label(__('warehouse.form.province'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('warehouse.form.is_active'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('common.form.created_at'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([

                    EditAction::make(),
                    Action::make('configureShipping')
                        ->label(__('warehouse.actions.configure_shipping'))
                        ->icon('heroicon-o-truck')
                        ->schema([
                            Hidden::make('is_connected')->default(false),
                            Hidden::make('shops_list'),

                            // Connection Info Section
                            \Filament\Schemas\Components\Section::make(__('filament.shipping.connection_info'))
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
                                                ->action(function (Set $set, Get $get, $livewire) {
                                                    self::handleTestConnection($set, $get, $livewire);
                                                })
                                        )
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                            'max' => __('common.error.max_length', ['max' => 255]),
                                        ]),

                                    Placeholder::make('connection_status')
                                        ->label(__('filament.shipping.connection_status'))
                                        ->content(fn(Get $get) => $get('is_connected')
                                            ? __('filament.shipping.connected')
                                            : __('filament.shipping.not_connected'))
                                        ->visible(fn(Get $get) => !empty($get('api_token'))),
                                ])
                                ->columns(2),

                            // Shop Info Section
                            \Filament\Schemas\Components\Section::make(__('filament.shipping.shop_info'))
                                ->description(__('filament.shipping.shop_info_description'))
                                ->schema([
                                    Select::make('store_id')
                                        ->label(__('filament.shipping.default_store'))
                                        ->options(function (Get $get) {
                                            $shops = $get('shops_list');
                                            return $shops ? collect($shops)->pluck('name', '_id')->toArray() : [];
                                        })
                                        ->required()
                                        ->searchable()
                                        ->disabled(fn(Get $get) => empty($get('shops_list')))
                                        ->placeholder(fn(Get $get) => empty($get('shops_list'))
                                            ? __('filament.shipping.test_connection_first')
                                            : __('filament.shipping.select_store'))
                                        ->helperText(fn(Get $get) => empty($get('shops_list'))
                                            ? __('filament.shipping.test_connection_to_load_stores')
                                            : __('filament.shipping.store_helper'))
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),
                                ])
                                ->visible(fn(Get $get) => !empty($get('shops_list'))),

                            // Insurance Settings Section
                            \Filament\Schemas\Components\Section::make(__('filament.shipping.insurance_settings'))
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
                                        ->disabled(fn(Get $get) => !$get('use_insurance'))
                                        ->helperText(__('filament.shipping.insurance_limit_helper'))
                                        ->validationMessages([
                                            'numeric' => __('common.error.numeric'),
                                            'min' => __('common.error.min_value', ['min' => 0]),
                                            'max' => __('common.error.max_value', ['max' => 10000000]),
                                        ]),
                                ])
                                ->columns(2)
                                ->collapsible(),

                            // Delivery Settings Section
                            \Filament\Schemas\Components\Section::make(__('filament.shipping.delivery_settings'))
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

                                    Select::make('pickup_shift')
                                        ->label(__('filament.shipping.default_pickup_shift'))
                                        ->options(ShiftGetGood::getOptions())
                                        ->required()
                                        ->helperText(__('filament.shipping.pickup_shift_helper'))
                                        ->validationMessages([
                                            'required' => __('common.error.required'),
                                        ]),

                                    TimePicker::make('pickup_time')
                                        ->label(__('filament.shipping.default_pickup_time'))
                                        ->seconds(false)
                                        ->timezone('Asia/Ho_Chi_Minh')
                                        ->format('H:i')
                                        ->helperText(__('filament.shipping.pickup_time_helper')),
                                ])
                                ->columns(2)
                                ->collapsible(),
                        ])
                        ->mountUsing(function ($form, $record) {
                            $config = $record->shippingConfig;
                            if ($config) {
                                $data = $config->toArray();
                                $data['is_connected'] = !empty($data['api_token']);

                                // Load shops if api_token exists
                                if (!empty($data['api_token'])) {
                                    try {
                                        $service = app(GHNService::class);
                                        $result = $service->testConnection($data);
                                        if ($result->isSuccess()) {
                                            $shopData = $result->getData();
                                            $data['shops_list'] = $shopData['shops'] ?? [];
                                        }
                                    } catch (\Exception $e) {
                                        // Ignore error on mount
                                    }
                                }

                                $form->fill($data);
                            } else {
                                // Default values for new config
                                $form->fill([
                                    'use_insurance' => false,
                                    'insurance_limit' => 0,
                                    'required_note' => RequiredNote::ALLOW_TO_TRY->value,
                                    'allow_cod_on_failed' => false,
                                    'pickup_shift' => ShiftGetGood::MORNING->value,
                                    'is_connected' => false,
                                ]);
                            }
                        })
                        ->action(function ($record, array $data) {
                            try {
                                // Validate shops loaded
                                if (empty($data['shops_list'])) {
                                    Notification::make()
                                        ->title(__('filament.shipping.test_connection_first'))
                                        ->warning()
                                        ->send();
                                    return;
                                }

                                // Remove temporary fields
                                unset($data['is_connected'], $data['shops_list']);

                                $configData = array_merge($data, [
                                    'organization_id' => $record->organization_id
                                ]);

                                if ($record->shippingConfig) {
                                    $record->shippingConfig->update($configData);
                                } else {
                                    $record->shippingConfig()->create($configData);
                                }

                                Notification::make()
                                    ->title(__('filament.shipping.saved_successfully'))
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title(__('filament.shipping.save_error'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])
            ], position: \Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->filters([
                TrashedFilter::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('common.action.delete'))
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),

                    RestoreBulkAction::make()
                        ->label(__('common.action.restore'))
                        ->visible(fn($livewire) => $livewire->tableFilters['trashed']['value'] ?? null === 'only'),

                    ForceDeleteBulkAction::make()
                        ->label(__('common.action.force_delete'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.force_delete_title'))
                        ->modalDescription(__('common.modal.force_delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete')),
                ]),
            ]);
    }

    /**
     * Handle test connection
     */
    private static function handleTestConnection(Set $set, Get $get, $livewire): void
    {
        try {
            $accountName = $get('account_name');
            $apiToken = $get('api_token');

            if (empty($accountName) || empty($apiToken)) {
                Notification::make()
                    ->title(__('filament.shipping.validation_error'))
                    ->body(__('common.error.required'))
                    ->danger()
                    ->send();
                return;
            }

            Notification::make()
                ->title(__('filament.shipping.connecting'))
                ->info()
                ->send();

            $service = app(GHNService::class);
            $result = $service->testConnection([
                'account_name' => $accountName,
                'api_token' => $apiToken,
            ]);

            if ($result->isSuccess()) {
                $shopData = $result->getData();
                $shops = $shopData['shops'] ?? [];
                $set('shops_list', $shops);
                $set('is_connected', true);

                Notification::make()
                    ->title(__('filament.shipping.connection_success'))
                    ->body(__('filament.shipping.found_shops', ['count' => count($shops)]))
                    ->success()
                    ->send();
            } else {
                $set('is_connected', false);
                $set('shops_list', []);

                Notification::make()
                    ->title(__('filament.shipping.connection_error'))
                    ->body($result->getMessage())
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            $set('is_connected', false);
            $set('shops_list', []);

            Notification::make()
                ->title(__('filament.shipping.connection_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
