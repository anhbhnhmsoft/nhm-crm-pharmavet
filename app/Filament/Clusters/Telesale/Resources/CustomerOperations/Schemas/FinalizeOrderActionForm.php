<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Schemas;

use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Order\PaymentType;
use App\Common\Constants\Order\ServiceType;
use App\Common\Constants\Shipping\ProviderShipping;
use App\Common\Constants\Shipping\RequiredNote;
use App\Models\District;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Province;
use App\Models\Ward;
use App\Services\OrderService;
use App\Services\Telesale\TelesaleFinalizeOrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

class FinalizeOrderActionForm
{
    public static function make(): Action
    {
        return Action::make('finalize_order')
            ->label(fn($record): string => app(TelesaleFinalizeOrderService::class)->hasEditableOrder($record)
                ? __('telesale_action.update_order')
                : __('telesale_action.finalize_order'))
            ->icon('heroicon-o-shopping-cart')
            ->color('success')
            ->disabled(fn($record): bool => app(TelesaleFinalizeOrderService::class)->isFinalizeOrderLockedForSale($record))
            ->tooltip(fn($record): ?string => app(TelesaleFinalizeOrderService::class)->isFinalizeOrderLockedForSale($record)
                ? __('telesale.messages.order_edit_locked_for_sale')
                : null)
            ->modalWidth('7xl')
            ->modalDescription(fn($record) => self::modalDescription($record))
            ->mountUsing(fn($form, $record) => app(TelesaleFinalizeOrderService::class)->mountForm($form, $record))
            ->schema([
                Grid::make(3)->schema(function ($record) {
                    $service = app(TelesaleFinalizeOrderService::class);

                    return [
                        Section::make(__('warehouse.order.form.customer_info'))
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('username')
                                    ->label(__('warehouse.order.form.username'))
                                    ->formatStateUsing(fn() => $record->username)
                                    ->disabled(),
                                TextInput::make('phone')
                                    ->label(__('warehouse.order.form.phone'))
                                    ->formatStateUsing(fn() => $record->phone)
                                    ->disabled(),
                                Select::make('shipping_method')
                                    ->label(__('warehouse.order.form.shipping_method'))
                                    ->options(ProviderShipping::getOptions())
                                    ->live()
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                Select::make('province_id')
                                    ->label(__('warehouse.order.form.province'))
                                    ->options(Province::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                    ->afterStateUpdated(fn(Set $set) => $set('district_id', null))
                                    ->required(fn(Get $get) => $get('shipping_method') !== ProviderShipping::GHN->value),
                                Select::make('district_id')
                                    ->label(__('warehouse.order.form.district'))
                                    ->options(fn(Get $get) => District::query()->where('province_id', $get('province_id'))->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->hidden(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                    ->afterStateUpdated(fn(Set $set) => $set('ward_id', null))
                                    ->required(fn(Get $get) => $get('shipping_method') !== ProviderShipping::GHN->value),
                                Select::make('ward_id')
                                    ->label(__('warehouse.order.form.ward'))
                                    ->options(fn(Get $get) => Ward::query()->where('district_id', $get('district_id'))->pluck('name', 'id'))
                                    ->searchable()
                                    ->hidden(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                    ->required(fn(Get $get) => $get('shipping_method') !== ProviderShipping::GHN->value),
                                Select::make('ghn_province_id')
                                    ->label(__('warehouse.order.form.province') . ' (GHN)')
                                    ->options(fn(Get $get) => $service->getGhnProvinceOptions((int) $get('organization_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('ghn_district_id', null);
                                        $set('ghn_ward_code', null);
                                    })
                                    ->required(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value),
                                Select::make('ghn_district_id')
                                    ->label(__('warehouse.order.form.district') . ' (GHN)')
                                    ->options(fn(Get $get) => $service->getGhnDistrictOptions(
                                        (int) $get('organization_id'),
                                        (int) $get('ghn_province_id')
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->visible(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                    ->afterStateUpdated(fn(Set $set) => $set('ghn_ward_code', null))
                                    ->required(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value),
                                Select::make('ghn_ward_code')
                                    ->label(__('warehouse.order.form.ward') . ' (GHN)')
                                    ->options(fn(Get $get) => $service->getGhnWardOptions(
                                        (int) $get('organization_id'),
                                        (int) $get('ghn_district_id')
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->visible(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                    ->required(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value),
                                TextInput::make('address')
                                    ->label(__('warehouse.order.form.address'))
                                    ->formatStateUsing(fn() => $record->address),
                            ]),
                        Section::make(__('warehouse.order.form.info'))
                            ->columnSpan(2)
                            ->schema([
                                Select::make('organization_id')
                                    ->label(__('telesale.table.organization'))
                                    ->options(Organization::query()->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->afterStateUpdated(function (Set $set) use ($service) {
                                        $set('warehouse_id', null);
                                        $set('items', []);
                                        $service->syncOrderLogistics($set, []);
                                        $service->resetOrderSummary($set);
                                    }),
                                Select::make('warehouse_id')
                                    ->label(__('order.form.warehouse'))
                                    ->options(fn(Get $get) => $service->getWarehouseOptions((int) $get('organization_id')))
                                    ->searchable()
                                    ->live()
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->afterStateUpdated(function (Set $set) use ($service) {
                                        $set('items', []);
                                        $service->syncOrderLogistics($set, []);
                                        $service->resetOrderSummary($set);
                                    })
                                    ->validationMessages([
                                        'required' => __('telesale.messages.warehouse_required'),
                                    ]),
                                TextInput::make('client_order_code')
                                    ->label(__('warehouse.order.form.client_order_code'))
                                    ->placeholder('ORD-XXXXXXX')
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ])
                                    ->rules(function ($record) {
                                        return [
                                            function (string $attribute, $value, $fail) use ($record) {
                                                $query = Order::query()->where('code', $value);

                                                $existingOrder = Order::query()
                                                    ->where('customer_id', $record->id)
                                                    ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])
                                                    ->latest()
                                                    ->first();

                                                if ($existingOrder) {
                                                    $query->where('id', '<>', $existingOrder->id);
                                                }

                                                if ($query->exists()) {
                                                    $fail(__('validation.unique', ['attribute' => __('warehouse.order.form.client_order_code')]));
                                                }
                                            },
                                        ];
                                    }),
                                Select::make('required_note')
                                    ->label(__('warehouse.order.form.required_note'))
                                    ->options(RequiredNote::getOptions())
                                    ->required()
                                    ->extraInputAttributes(['required' => false])
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                Placeholder::make('logistics_helper')
                                    ->label('')
                                    ->content(fn() => new HtmlString(
                                        '<div class="text-xs text-gray-500 italic">'
                                        . __('telesale.helper.auto_calculated_logistics_dimensions')
                                        . '</div>'
                                    )),
                                Repeater::make('items')
                                    ->label(__('warehouse.order.form.product'))
                                    ->schema([
                                        Select::make('product_id')
                                            ->label(__('warehouse.order.form.product'))
                                            ->options(fn(Get $get) => $service->getWarehouseProductOptions(
                                                (int) $get('../../organization_id'),
                                                (int) $get('../../warehouse_id')
                                            ))
                                            ->searchable()
                                            ->disabled(fn(Get $get) => (int) $get('../../warehouse_id') <= 0)
                                            ->required()
                                            ->live()
                                            ->helperText(function (Get $get) use ($service): ?string {
                                                $warehouseId = (int) $get('../../warehouse_id');
                                                $productId = (int) $get('product_id');

                                                if ($warehouseId <= 0) {
                                                    return __('telesale.messages.warehouse_required');
                                                }

                                                if ($productId <= 0) {
                                                    return null;
                                                }

                                                return __('warehouse.reports.available_stock') . ': ' . $service->getAvailableStock($warehouseId, $productId);
                                            })
                                            ->disableOptionWhen(
                                                fn($value, $state, $get) => collect($get('../../items'))
                                                    ->pluck('product_id')
                                                    ->contains($value) && $value != $state
                                            )
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) use ($service) {
                                                $product = Product::find($state);

                                                if ($product) {
                                                    $set('price', $product->sale_price ?? 0);
                                                    $set('quantity', 1);
                                                    $set('total', $product->sale_price ?? 0);
                                                }

                                                $service->syncOrderLogistics($set, $get('../../items') ?? []);
                                                $service->recalculateOrderSummary($set, $get);
                                            })
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                            ]),
                                        TextInput::make('quantity')
                                            ->label(__('warehouse.order.form.quantity'))
                                            ->integer()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->extraInputAttributes(self::numericInputAttributes('numeric'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Get $get, Set $set) use ($service) {
                                                $set('total', max(0, (int) $state) * (float) ($get('price') ?? 0));
                                                $service->syncOrderLogistics($set, $get('../../items') ?? []);
                                                $service->recalculateOrderSummary($set, $get);
                                            })
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                                'integer' => __('common.error.integer'),
                                                'min' => __('common.error.min_value', ['min' => 1]),
                                            ]),
                                        TextInput::make('price')
                                            ->label(__('warehouse.order.form.price'))
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->required()
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                            ]),
                                        TextInput::make('total')
                                            ->label(__('warehouse.order.form.total'))
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->required()
                                            ->validationMessages([
                                                'required' => __('common.error.required'),
                                            ]),
                                    ])
                                    ->columns(4)
                                    ->defaultItems(1)
                                    ->minItems(1)
                                    ->live(debounce: 500)
                                    ->validationMessages([
                                        'min' => __('common.error.min.array', ['min' => 1]),
                                    ])
                                    ->afterStateUpdated(function (Get $get, Set $set) use ($service) {
                                        $service->syncOrderLogistics($set, $get('items') ?? []);
                                        $service->recalculateOrderSummary($set, $get);
                                    }),
                                Grid::make(3)->schema([
                                    TextInput::make('discount')
                                        ->label(__('warehouse.order.form.discount'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->extraInputAttributes(self::numericInputAttributes())
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $service->recalculateOrderSummary($set, $get))
                                        ->validationMessages([
                                            'numeric' => __('common.error.numeric'),
                                            'min' => __('common.error.min_value', ['min' => 0]),
                                        ]),
                                    TextInput::make('ck1')
                                        ->label('CK1 (%)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->default(0)
                                        ->extraInputAttributes(self::numericInputAttributes())
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $service->recalculateOrderSummary($set, $get))
                                        ->validationMessages([
                                            'numeric' => __('common.error.numeric'),
                                            'min' => __('common.error.min_value', ['min' => 0]),
                                            'max' => __('common.error.max_value', ['max' => 100]),
                                        ]),
                                    TextInput::make('ck2')
                                        ->label('CK2 (%)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->default(0)
                                        ->extraInputAttributes(self::numericInputAttributes())
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $service->recalculateOrderSummary($set, $get))
                                        ->validationMessages([
                                            'numeric' => __('common.error.numeric'),
                                            'min' => __('common.error.min_value', ['min' => 0]),
                                            'max' => __('common.error.max_value', ['max' => 100]),
                                        ]),
                                ]),
                                TextInput::make('total_discount_display')
                                    ->label(__('warehouse.order.form.total_discount_display'))
                                    ->disabled()
                                    ->dehydrated(false),
                                Grid::make(3)->schema([
                                    TextInput::make('cod_fee')
                                        ->label(__('warehouse.order.form.cod_fee'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->extraInputAttributes(self::numericInputAttributes())
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $service->recalculateOrderSummary($set, $get))
                                        ->validationMessages([
                                            'numeric' => __('common.error.numeric'),
                                            'min' => __('common.error.min_value', ['min' => 0]),
                                        ]),
                                    TextInput::make('cod_support_amount')
                                        ->label(__('telesale.form.cod_support_amount'))
                                        ->numeric()
                                        ->minValue(0)
                                        ->default(0)
                                        ->extraInputAttributes(self::numericInputAttributes())
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn(Get $get, Set $set) => $service->recalculateOrderSummary($set, $get))
                                        ->validationMessages([
                                            'numeric' => __('common.error.numeric'),
                                            'min' => __('common.error.min_value', ['min' => 0]),
                                        ]),
                                    TextInput::make('cod_amount')
                                        ->label(__('warehouse.order.form.cod_amount'))
                                        ->numeric()
                                        ->disabled()
                                        ->dehydrated(),
                                ]),
                                TextInput::make('deposit')
                                    ->label(__('warehouse.order.form.deposit'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(fn(Get $get): float => $service->getMaxAllowedDeposit($get))
                                    ->default(0)
                                    ->extraInputAttributes(self::numericInputAttributes())
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Get $get, Set $set) => $service->recalculateOrderSummary($set, $get))
                                    ->validationMessages([
                                        'numeric' => __('common.error.numeric'),
                                        'min' => __('common.error.min_value', ['min' => 0]),
                                        'max' => __('telesale.messages.deposit_exceeds_total'),
                                    ]),
                                Section::make(__('Logistics GHN'))
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextInput::make('weight')
                                                ->label(__('warehouse.order.form.weight'))
                                                ->numeric()
                                                ->suffix('g')
                                                ->required()
                                                ->minValue(1)
                                                ->maxValue(20000)
                                                ->extraInputAttributes(self::numericInputAttributes('numeric'))
                                                ->validationMessages([
                                                    'required' => __('common.error.required'),
                                                    'numeric' => __('common.error.numeric'),
                                                    'min' => __('common.error.min_value', ['min' => 1]),
                                                    'max' => __('common.error.max_value', ['max' => 20000]),
                                                ]),
                                            TextInput::make('length')
                                                ->label(__('warehouse.order.form.length'))
                                                ->numeric()
                                                ->suffix('cm')
                                                ->required()
                                                ->minValue(1)
                                                ->maxValue(200)
                                                ->extraInputAttributes(self::numericInputAttributes('numeric'))
                                                ->validationMessages([
                                                    'required' => __('common.error.required'),
                                                    'numeric' => __('common.error.numeric'),
                                                    'min' => __('common.error.min_value', ['min' => 1]),
                                                    'max' => __('common.error.max_value', ['max' => 200]),
                                                ]),
                                            TextInput::make('width')
                                                ->label(__('warehouse.order.form.width'))
                                                ->numeric()
                                                ->suffix('cm')
                                                ->required()
                                                ->minValue(1)
                                                ->maxValue(200)
                                                ->extraInputAttributes(self::numericInputAttributes('numeric'))
                                                ->validationMessages([
                                                    'required' => __('common.error.required'),
                                                    'numeric' => __('common.error.numeric'),
                                                    'min' => __('common.error.min_value', ['min' => 1]),
                                                    'max' => __('common.error.max_value', ['max' => 200]),
                                                ]),
                                            TextInput::make('height')
                                                ->label(__('warehouse.order.form.height'))
                                                ->numeric()
                                                ->suffix('cm')
                                                ->required()
                                                ->minValue(1)
                                                ->maxValue(200)
                                                ->extraInputAttributes(self::numericInputAttributes('numeric'))
                                                ->validationMessages([
                                                    'required' => __('common.error.required'),
                                                    'numeric' => __('common.error.numeric'),
                                                    'min' => __('common.error.min_value', ['min' => 1]),
                                                    'max' => __('common.error.max_value', ['max' => 200]),
                                                ]),
                                        ]),
                                        Grid::make(3)->schema([
                                            Select::make('ghn_payment_type_id')
                                                ->label(__('warehouse.order.form.ghn_payment_type_id'))
                                                ->options(PaymentType::toOptions())
                                                ->default(PaymentType::BUYER_PAYS_COD->value)
                                                ->required(),
                                            Select::make('ghn_service_type_id')
                                                ->label(__('warehouse.order.form.ghn_service_type_id'))
                                                ->options(ServiceType::toOptions())
                                                ->default(ServiceType::LIGHT->value)
                                                ->required(),
                                            TextInput::make('insurance_value')
                                                ->label(__('warehouse.order.form.insurance_value'))
                                                ->numeric()
                                                ->default(0)
                                                ->extraInputAttributes(self::numericInputAttributes())
                                                ->validationMessages([
                                                    'numeric' => __('common.error.numeric'),
                                                ]),
                                            TextInput::make('ghn_cod_failed_amount')
                                                ->label(__('warehouse.order.form.ghn_cod_failed_amount'))
                                                ->numeric()
                                                ->default(0)
                                                ->extraInputAttributes(self::numericInputAttributes())
                                                ->validationMessages([
                                                    'numeric' => __('common.error.numeric'),
                                                ]),
                                            Select::make('ghn_pick_station_id')
                                                ->label(__('warehouse.order.form.ghn_pick_station_id'))
                                                ->options(fn(Get $get) => $service->getShippingShopOptions((int) $get('organization_id')))
                                                ->searchable(),
                                        ]),
                                        Textarea::make('ghn_content')
                                            ->label(__('warehouse.order.form.ghn_content'))
                                            ->rows(2),
                                    ]),
                                Radio::make('status_action')
                                    ->label(__('warehouse.order.form.status_action'))
                                    ->options([
                                        OrderStatus::CONFIRMED->value => OrderStatus::CONFIRMED->label(),
                                    ])
                                    ->default(OrderStatus::CONFIRMED->value)
                                    ->inline()
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                        'in' => __('common.error.in', ['attribute' => 'Trạng thái']),
                                    ]),
                            ]),
                    ];
                }),
            ])
            ->extraModalFooterActions(fn($record) => [
                Action::make('cancel_finalize')
                    ->label(__('warehouse.order.form.cancel_finalize'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn() => app(TelesaleFinalizeOrderService::class)->canCancelFinalize($record))
                    ->action(fn() => app(TelesaleFinalizeOrderService::class)->cancelFinalize($record)),
            ])
            ->action(fn(array $data, $record, OrderService $orderService) => app(TelesaleFinalizeOrderService::class)->handleFinalizeAction($data, $record, $orderService));
    }

    protected static function modalDescription($record): string
    {
        $parts = [
            __('telesale.table.data_code') . ': ' . $record->id,
        ];

        if (filled($record->username)) {
            $parts[] = __('telesale.table.customer_name') . ': ' . $record->username;
        }

        if (filled($record->phone)) {
            $parts[] = __('common.table.phone') . ': ' . $record->phone;
        }

        return implode(' | ', $parts);
    }

    protected static function numericInputAttributes(string $inputMode = 'decimal'): array
    {
        return [
            'type' => 'text',
            'inputmode' => $inputMode,
            'required' => false,
            'min' => null,
            'max' => null,
            'step' => null,
        ];
    }
}
