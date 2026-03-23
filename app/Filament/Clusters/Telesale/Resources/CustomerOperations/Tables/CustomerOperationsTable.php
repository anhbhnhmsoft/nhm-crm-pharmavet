<?php

namespace App\Filament\Clusters\Telesale\Resources\CustomerOperations\Tables;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\Customer\ReasonInteraction;
use App\Common\Constants\Interaction\InteractionStatus;
use App\Common\Constants\Marketing\IntegrationType;
use App\Common\Constants\Order\OrderStatus;
use App\Common\Constants\Shipping\ProviderShipping;
use App\Common\Constants\Shipping\RequiredNote;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Province;
use App\Models\Warehouse;
use App\Models\Ward;
use App\Models\Organization;
use App\Services\OrderService;
use App\Services\Telesale\Customer360Service;
use App\Common\Constants\User\UserRole;
use App\Common\Constants\Order\PaymentType;
use App\Common\Constants\Order\ServiceType;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class CustomerOperationsTable
{
    public static function configure(Table $table): Table
    {
        $recalculateOrderSummary = function (Get $get, Set $set): void {
            $items = $get('items') ?? [];

            $productTotal = collect($items)->sum(function ($item) {
                $qty = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);

                return $qty * $price;
            });

            $discount = (float) ($get('discount') ?? 0);
            $ck1 = (float) ($get('ck1') ?? 0);
            $ck2 = (float) ($get('ck2') ?? 0);
            $codFee = (float) ($get('cod_fee') ?? 0);
            $shippingFee = (float) ($get('shipping_fee') ?? 0);
            $deposit = (float) ($get('deposit') ?? 0);
            $codSupportAmount = (float) ($get('cod_support_amount') ?? 0);

            $productDiscount = $productTotal * (($ck1 + $ck2) / 100);
            $totalDiscount = $productDiscount + $discount;
            $grossTotal = max(0, $productTotal - $totalDiscount + $shippingFee + $codFee);
            $collectAmount = max(0, $grossTotal - $deposit - $codSupportAmount);

            $set('total_amount_temp', round($productTotal));
            $set('total_discount_display', number_format($totalDiscount) . ' ' . __('telesale.form.currency_suffix'));
            $set('cod_amount', round($collectAmount));
            $set('final_collect_amount_display', number_format($collectAmount) . ' ' . __('telesale.form.currency_suffix'));
        };

        return $table
            ->recordClasses(
                fn($record) =>
                $record->orders()->where('status', OrderStatus::PENDING->value)->exists()
                ? 'bg-red-50 dark:bg-red-900/10'
                : null
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('telesale.table.data_code'))
                    ->searchable()
                    ->sortable()
                    ->size('sm')
                    ->weight('bold'),
                TextColumn::make('organization.name')
                    ->label(__('telesale.table.organization'))
                    ->visible(fn() => Auth::user()->role === UserRole::SUPER_ADMIN->value)
                    ->searchable()
                    ->sortable()
                    ->size('sm'),

                TextColumn::make('username')
                    ->label(__('telesale.table.customer_name'))
                    ->searchable(['username', 'phone'])
                    ->size('sm')
                    ->weight('medium'),

                TextColumn::make('source')
                    ->label(__('telesale.table.source'))
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(int $state) => IntegrationType::getLabel($state))
                    ->size('sm'),

                TextColumn::make('customer_type')
                    ->label(__('telesale.table.customer_type'))
                    ->badge()
                    ->color(fn(int $state): string => CustomerType::colors($state))
                    ->formatStateUsing(fn($state) => CustomerType::getLabel($state))
                    ->size('sm'),

                TextColumn::make('next_action_at')
                    ->label(__('telesale.table.next_action'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('telesale.messages.no_schedule'))
                    ->color(fn($state) => $state && $state->isPast() ? 'danger' : 'success')
                    ->size('sm'),

                TextColumn::make('created_at')
                    ->label(__('telesale.table.date_received'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable()
                    ->size('sm'),
                TextColumn::make('interaction_status')
                    ->label(__('telesale.table.interaction_status'))
                    ->badge()
                    ->formatStateUsing(fn($state) => InteractionStatus::getLabelStatus($state))
                    ->size('sm'),
                TextColumn::make('blackList')
                    ->label(__('telesale.table.blacklist'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? __('telesale.table.blacklist') : __('telesale.table.unblacklist'))
                    ->size('sm'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('finalize_order')
                        ->label(__('warehouse.actions.finalize_order'))
                        ->icon('heroicon-o-shopping-cart')
                        ->color('success')
                        ->modalWidth('7xl')
                        ->disabled(function ($record): bool {
                            $isSale = Auth::user()->role === UserRole::SALE->value;
                            if (!$isSale) {
                                return false;
                            }

                            return $record->orders()
                                ->whereIn('status', [OrderStatus::SHIPPING->value, OrderStatus::COMPLETED->value])
                                ->exists();
                        })
                        ->tooltip(function ($record): ?string {
                            $isSale = Auth::user()->role === UserRole::SALE->value;
                            if (!$isSale) {
                                return null;
                            }

                            $isLocked = $record->orders()
                                ->whereIn('status', [OrderStatus::SHIPPING->value, OrderStatus::COMPLETED->value])
                                ->exists();

                            return $isLocked ? __('telesale.messages.order_edit_locked_for_sale') : null;
                        })
                        ->mountUsing(function ($form, $record) {
                            $order = Order::where('customer_id', $record->id)
                                ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])
                                ->latest()
                                ->first();

                            if ($order) {
                                $data = $order->toArray();
                                $data['items'] = $order->items->map(function ($item) {
                                    return [
                                        'product_id' => $item->product_id,
                                        'quantity' => $item->quantity,
                                        'price' => $item->price,
                                        'total' => $item->total,
                                    ];
                                })->toArray();
                                $data['status_action'] = $order->status;
                                $data['weight'] = $order->weight;
                                $data['length'] = $order->length;
                                $data['width'] = $order->width;
                                $data['height'] = $order->height;
                                $data['ghn_payment_type_id'] = $order->ghn_payment_type_id;
                                $data['ghn_service_type_id'] = $order->ghn_service_type_id;
                                $data['ghn_content'] = $order->ghn_content;
                                $data['insurance_value'] = $order->insurance_value;
                                $data['ghn_cod_failed_amount'] = $order->ghn_cod_failed_amount;
                                $data['ghn_pick_station_id'] = $order->ghn_pick_station_id;
                                $data['ghn_deliver_station_id'] = $order->ghn_deliver_station_id;
                                $data['ghn_province_id'] = $order->ghn_province_id;
                                $data['ghn_district_id'] = $order->ghn_district_id;
                                $data['ghn_ward_code'] = $order->ghn_ward_code;
                                $data['client_order_code'] = $order->code;
                                $data['warehouse_id'] = $order->warehouse_id;
                                // Map customer info from order if needed, or keep current
                                $form->fill($data);
                            } else {
                                $form->fill([
                                    'username' => $record->username,
                                    'phone' => $record->phone,
                                    'address' => $record->address,
                                    'province_id' => $record->province_id,
                                    'district_id' => $record->district_id,
                                    'ward_id' => $record->ward_id,
                                    'organization_id' => $record->organization_id,
                                    'warehouse_id' => null,
                                    'client_order_code' => 'ORD-' . time(),
                                    'status_action' => OrderStatus::CONFIRMED->value,
                                ]);
                            }
                        })
                        ->schema([
                            Grid::make(3)->schema(function ($record) {
                                // dump($record);
                                return [
                                    Section::make(__('warehouse.order.form.customer_info'))
                                        ->columnSpan(1)
                                        ->schema([
                                            TextInput::make('username')->label(__('warehouse.order.form.username'))->formatStateUsing(fn($record) => $record->username)->disabled(),
                                            TextInput::make('phone')->label(__('warehouse.order.form.phone'))->formatStateUsing(fn($record) => $record->phone)->disabled(),
                                            Select::make('province_id')
                                                ->label(__('warehouse.order.form.province'))
                                                ->options(Province::all()->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->hidden(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                                ->afterStateUpdated(fn(Set $set) => $set('district_id', null))
                                                ->required(fn(Get $get) => $get('shipping_method') !== ProviderShipping::GHN->value),
                                            Select::make('district_id')
                                                ->label(__('warehouse.order.form.district'))
                                                ->options(fn($get) => District::where('province_id', $get('province_id'))->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->hidden(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                                ->afterStateUpdated(fn(Set $set) => $set('ward_id', null))
                                                ->required(fn(Get $get) => $get('shipping_method') !== ProviderShipping::GHN->value),
                                            Select::make('ward_id')
                                                ->label(__('warehouse.order.form.ward'))
                                                ->options(fn($get) => Ward::where('district_id', $get('district_id'))->pluck('name', 'id'))
                                                ->searchable()
                                                ->hidden(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                                ->required(fn(Get $get) => $get('shipping_method') !== ProviderShipping::GHN->value),
                                            Select::make('ghn_province_id')
                                                ->label(__('warehouse.order.form.province') . ' (GHN)')
                                                ->options(function (Get $get) {
                                    $orgId = $get('organization_id');
                                    if (!$orgId)
                                        return [];
                                    try {
                                        $ghn = new \App\Services\GHNService(app(\App\Repositories\ShippingConfigRepository::class), (int) $orgId);
                                        $provinces = $ghn->getProvinces();
                                        return collect($provinces)->pluck('ProvinceName', 'ProvinceID');
                                    } catch (\Exception $e) {
                                        return [];
                                    }
                                })
                                                ->searchable()
                                                ->live()
                                                ->visible(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                                ->afterStateUpdated(function (Set $set) {
                                    $set('ghn_district_id', null);
                                    $set('ghn_ward_code', null);
                                })
                                                ->required(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value),
                                            Select::make('ghn_district_id')
                                                ->label(__('warehouse.order.form.district') . ' (GHN)')
                                                ->options(function (Get $get) {
                                    $orgId = $get('organization_id');
                                    $provinceId = $get('ghn_province_id');
                                    if (!$orgId || !$provinceId)
                                        return [];
                                    try {
                                        $ghn = new \App\Services\GHNService(app(\App\Repositories\ShippingConfigRepository::class), (int) $orgId);
                                        $districts = $ghn->getDistricts((int) $provinceId);
                                        return collect($districts)->pluck('DistrictName', 'DistrictID');
                                    } catch (\Exception $e) {
                                        return [];
                                    }
                                })
                                                ->searchable()
                                                ->live()
                                                ->visible(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                                ->afterStateUpdated(fn(Set $set) => $set('ghn_ward_code', null))
                                                ->required(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value),
                                            Select::make('ghn_ward_code')
                                                ->label(__('warehouse.order.form.ward') . ' (GHN)')
                                                ->options(function (Get $get) {
                                    $orgId = $get('organization_id');
                                    $districtId = $get('ghn_district_id');
                                    if (!$orgId || !$districtId)
                                        return [];
                                    try {
                                        $ghn = new \App\Services\GHNService(app(\App\Repositories\ShippingConfigRepository::class), (int) $orgId);
                                        $wards = $ghn->getWards((int) $districtId);
                                        return collect($wards)->pluck('WardName', 'WardCode');
                                    } catch (\Exception $e) {
                                        return [];
                                    }
                                })
                                                ->searchable()
                                                ->visible(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value)
                                                ->required(fn(Get $get) => $get('shipping_method') === ProviderShipping::GHN->value),

                                            TextInput::make('address')->label(__('warehouse.order.form.address'))->formatStateUsing(fn($record) => $record->address),
                                        ]),
                                    Section::make(__('warehouse.order.form.info'))
                                        ->columnSpan(2)
                                        ->schema([
                                            Select::make('organization_id')
                                                ->label(__('telesale.table.organization'))
                                                ->options(Organization::all()->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->required()
                                                ->afterStateUpdated(function (Set $set) {
                                                    $set('warehouse_id', null);
                                                    $set('items', []);
                                                }),
                                            Select::make('warehouse_id')
                                                ->label(__('order.form.warehouse'))
                                                ->options(fn(Get $get) => Warehouse::where('organization_id', $get('organization_id'))->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => __('telesale.messages.warehouse_required'),
                                                ])
                                                ->afterStateUpdated(fn(Set $set) => $set('items', [])),
                                            TextInput::make('client_order_code')
                                                ->label(__('warehouse.order.form.client_order_code'))
                                                ->placeholder('ORD-XXXXXXX')
                                                ->required()
                                                ->rules(function ($record) {
                                    return [
                                        function (string $attribute, $value, $fail) use ($record) {
                                            $query = Order::where('code', $value);

                                            // Find if this customer already has an order we should ignore
                                            $existingOrder = Order::where('customer_id', $record->id)
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
                                            Select::make('shipping_method')
                                                ->label(__('warehouse.order.form.shipping_method'))
                                                ->options(ProviderShipping::getOptions())
                                                ->live()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => __('common.error.required')
                                                ]),
                                            Select::make('required_note')
                                                ->label(__('warehouse.order.form.required_note'))
                                                ->options(RequiredNote::getOptions())
                                                ->required()
                                                ->validationMessages([
                                                    'required' => __('common.error.required')
                                                ]),

                                            Placeholder::make('logistics_helper')
                                                ->label('')
                                                ->content(fn() => new \Illuminate\Support\HtmlString('<div class="text-xs text-gray-500 italic">' . e(__('telesale.helper.auto_calculated_logistics_dimensions')) . '</div>')),

                                            Repeater::make('items')
                                                ->label(__('warehouse.order.form.product'))
                                                ->schema([
                                                    Select::make('product_id')
                                                        ->label(__('warehouse.order.form.product'))
                                                        ->options(function (Get $get) {
                                                            $organizationId = $get('../../organization_id');
                                                            $warehouseId = $get('../../warehouse_id');

                                                            if (!$organizationId || !$warehouseId) {
                                                                return [];
                                                            }

                                                            return Product::query()
                                                                ->where('organization_id', $organizationId)
                                                                ->whereIn('id', function ($query) use ($warehouseId) {
                                                                    $query->select('product_id')
                                                                        ->from('product_warehouse')
                                                                        ->where('warehouse_id', $warehouseId)
                                                                        ->whereRaw('(quantity - pending_quantity) > 0');
                                                                })
                                                                ->pluck('name', 'id');
                                                        })
                                                        ->searchable()
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, Get $get, Set $set) use ($recalculateOrderSummary) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('price', $product->sale_price ?? 0);
                                            $set('quantity', 1);
                                            $set('total', $product->sale_price ?? 0);

                                            // Update logistics immediately for this item
                                            $items = $get('../../items') ?? [];
                                            $totalWeight = 0;
                                            $maxLength = 0;
                                            $maxWidth = 0;
                                            $totalHeight = 0;

                                            foreach ($items as $item) {
                                                $p = Product::find($item['product_id']);
                                                if ($p) {
                                                    $qty = $item['quantity'] ?? 1;
                                                    $totalWeight += ($p->weight ?? 200) * $qty;
                                                    $maxLength = max($maxLength, $p->length ?? 10);
                                                    $maxWidth = max($maxWidth, $p->width ?? 10);
                                                    $totalHeight += ($p->height ?? 5) * $qty;
                                                }
                                            }

                                            $set('../../weight', $totalWeight);
                                            $set('../../length', $maxLength);
                                            $set('../../width', $maxWidth);
                                            $set('../../height', $totalHeight);
                                        }

                                        $recalculateOrderSummary($get, $set);
                                    })
                                                        ->validationMessages([
                                                            'required' => __('common.error.required')
                                                        ]),
                                                    TextInput::make('quantity')
                                                        ->label(__('warehouse.order.form.quantity'))
                                                        ->numeric()
                                                        ->default(1)
                                                        ->required()
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function ($state, Get $get, Set $set) use ($recalculateOrderSummary) {
                                        $set('total', $state * $get('price'));

                                        // Update logistics
                                        $items = $get('../../items') ?? [];
                                        $totalWeight = 0;
                                        $maxLength = 0;
                                        $maxWidth = 0;
                                        $totalHeight = 0;

                                        foreach ($items as $item) {
                                            $product = Product::find($item['product_id']);
                                            if ($product) {
                                                $qty = $item['quantity'] ?? 1;
                                                $totalWeight += ($product->weight ?? 200) * $qty;
                                                $maxLength = max($maxLength, $product->length ?? 10);
                                                $maxWidth = max($maxWidth, $product->width ?? 10);
                                                $totalHeight += ($product->height ?? 5) * $qty;
                                            }
                                        }

                                        $set('../../weight', $totalWeight);
                                        $set('../../length', $maxLength);
                                        $set('../../width', $maxWidth);
                                        $set('../../height', $totalHeight);

                                        $recalculateOrderSummary($get, $set);
                                    })
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => __('common.error.required')
                                                        ]),
                                                    TextInput::make('price')
                                                        ->label(__('warehouse.order.form.price'))
                                                        ->numeric()
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => __('common.error.required')
                                                        ]),
                                                    TextInput::make('total')
                                                        ->label(__('warehouse.order.form.total'))
                                                        ->numeric()
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->required()
                                                        ->validationMessages([
                                                            'required' => __('common.error.required')
                                                        ]),
                                                ])
                                                ->columns(4)
                                                ->live(debounce: 500)
                                                ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set)),

                                            Grid::make(3)->schema([
                                                // TextInput::make('shipping_fee')->label(__('warehouse.order.form.shipping_fee'))->numeric()->default(0),
                                                TextInput::make('discount')
                                                    ->label(__('warehouse.order.form.discount'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set)),
                                                TextInput::make('ck1')
                                                    ->label(__('telesale.form.ck1'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set))
                                                    ->validationMessages([
                                                        'min' => __('common.error.min_value', ['min' => 0]),
                                                        'max' => __('common.error.max_value', ['max' => 100]),
                                                    ]),
                                                TextInput::make('ck2')
                                                    ->label(__('telesale.form.ck2'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set))
                                                    ->validationMessages([
                                                        'min' => __('common.error.min_value', ['min' => 0]),
                                                        'max' => __('common.error.max_value', ['max' => 100]),
                                                    ]),
                                            ]),

                                            TextInput::make('total_discount_display')
                                                ->label(__('warehouse.order.form.total_discount_display'))
                                                ->disabled()
                                                ->dehydrated(false),

                                            Grid::make(3)->schema([
                                                // TextInput::make('shipping_fee')->label(__('warehouse.order.form.shipping_fee'))->numeric()->default(0),
                                                TextInput::make('cod_fee')
                                                    ->label(__('warehouse.order.form.cod_fee'))
                                                    ->numeric()
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set)),
                                                TextInput::make('cod_support_amount')
                                                    ->label(__('telesale.form.cod_support_amount'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set)),
                                                TextInput::make('cod_amount')
                                                    ->label(__('warehouse.order.form.cod_amount'))
                                                    ->numeric()
                                                    ->disabled()
                                                    ->dehydrated(),
                                            ]),

                                            TextInput::make('deposit')
                                                ->label(__('warehouse.order.form.deposit'))
                                                ->numeric()
                                                ->default(0)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn(Get $get, Set $set) => $recalculateOrderSummary($get, $set)),

                                            TextInput::make('final_collect_amount_display')
                                                ->label(__('warehouse.order.form.cod_amount'))
                                                ->disabled()
                                                ->dehydrated(false),

                                            Section::make(__('Logistics GHN'))
                                                ->schema([
                                                    Grid::make(4)->schema([
                                                        TextInput::make('weight')->label(__('warehouse.order.form.weight'))->numeric()->suffix('g')->required()->minValue(1)->maxValue(20000),
                                                        TextInput::make('length')->label(__('warehouse.order.form.length'))->numeric()->suffix('cm')->required()->minValue(1)->maxValue(200),
                                                        TextInput::make('width')->label(__('warehouse.order.form.width'))->numeric()->suffix('cm')->required()->minValue(1)->maxValue(200),
                                                        TextInput::make('height')->label(__('warehouse.order.form.height'))->numeric()->suffix('cm')->required()->minValue(1)->maxValue(200),
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
                                                            ->default(0),
                                                        TextInput::make('ghn_cod_failed_amount')
                                                            ->label(__('warehouse.order.form.ghn_cod_failed_amount'))
                                                            ->numeric()
                                                            ->default(0),
                                                        TextInput::make('ghn_pick_station_id')
                                                            ->label(__('warehouse.order.form.ghn_pick_station_id'))
                                                            ->numeric(),
                                                        TextInput::make('ghn_deliver_station_id')
                                                            ->label(__('warehouse.order.form.ghn_deliver_station_id'))
                                                            ->numeric(),
                                                    ]),
                                                    Textarea::make('ghn_content')->label(__('warehouse.order.form.ghn_content'))->rows(2),
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
                                ->visible(function () use ($record) {
                                    $order = Order::where('customer_id', $record->id)->latest()->first();
                                    return $order && $order->status == OrderStatus::CONFIRMED->value;
                                })
                                ->action(function () use ($record) {
                                    $order = Order::where('customer_id', $record->id)->latest()->first();
                                    if ($order && $order->status == OrderStatus::CONFIRMED->value) {
                                        $order->update(['status' => OrderStatus::PENDING->value]);
                                        Notification::make()->title(__('warehouse.order.form.cancel_finalize'))->success()->send();
                                    }
                                }),
                        ])
                        ->action(function (array $data, $record) {
                            Log::info('finalize_order: Action triggered', [
                                'customer_id' => $record->id,
                                'user_id' => Auth::id(),
                                'data_keys' => array_keys($data)
                            ]);

                            $warehouseId = (int) ($data['warehouse_id'] ?? 0);
                            if ($warehouseId <= 0) {
                                Notification::make()
                                    ->title(__('telesale.messages.warehouse_required'))
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $items = collect($data['items'] ?? [])
                                ->filter(fn($item) => !empty($item['product_id']) && (int) ($item['quantity'] ?? 0) > 0)
                                ->values();

                            $productTotal = $items->sum(fn($item) => ((float) ($item['quantity'] ?? 0)) * ((float) ($item['price'] ?? 0)));
                            $discount = (float) ($data['discount'] ?? 0);
                            $ck1 = (float) ($data['ck1'] ?? 0);
                            $ck2 = (float) ($data['ck2'] ?? 0);
                            $shippingFee = (float) ($data['shipping_fee'] ?? 0);
                            $codFee = (float) ($data['cod_fee'] ?? 0);
                            $codSupportAmount = (float) ($data['cod_support_amount'] ?? 0);
                            $deposit = (float) ($data['deposit'] ?? 0);

                            $productDiscount = $productTotal * (($ck1 + $ck2) / 100);
                            $totalDiscount = $productDiscount + $discount;
                            $grossTotal = max(0, $productTotal - $totalDiscount + $shippingFee + $codFee);

                            if ($deposit > $grossTotal) {
                                Notification::make()
                                    ->title(__('telesale.messages.deposit_exceeds_total'))
                                    ->danger()
                                    ->send();
                                return;
                            }

                            foreach ($items as $item) {
                                $productId = (int) $item['product_id'];
                                $requiredQty = (int) $item['quantity'];

                                $stock = ProductWarehouse::query()
                                    ->where('warehouse_id', $warehouseId)
                                    ->where('product_id', $productId)
                                    ->first();

                                $availableQty = (int) (($stock?->quantity ?? 0) - ($stock?->pending_quantity ?? 0));
                                if ($availableQty < $requiredQty) {
                                    $productName = Product::find($productId)?->name ?? '#' . $productId;

                                    Notification::make()
                                        ->title(__('telesale.messages.insufficient_stock', ['product' => $productName]))
                                        ->danger()
                                        ->send();
                                    return;
                                }
                            }

                            $data['customer_id'] = $record->id;
                            $data['organization_id'] = $data['organization_id'] ?? $record->organization_id;
                            $data['code'] = $data['client_order_code'];
                            $data['created_by'] = Auth::id();
                            $data['updated_by'] = Auth::id();
                            $data['warehouse_id'] = $warehouseId;
                            $data['cod_amount'] = max(0, $grossTotal - $deposit - $codSupportAmount);

                            /** @var OrderService $orderService */
                            $orderService = app(OrderService::class);
                            $result = $orderService->finalizeOrder($data);

                            Log::info('finalize_order: Service result', [
                                'is_success' => $result->isSuccess(),
                                'message' => $result->getMessage()
                            ]);

                            if ($result->isSuccess()) {
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } else {
                                Notification::make()->title($result->getMessage())->danger()->send();
                            }
                        }),

                    Action::make('customer_360')
                        ->label(__('telesale.actions.customer_360'))
                        ->icon('heroicon-o-user-circle')
                        ->color('info')
                        ->modalWidth('7xl')
                        ->schema(function ($record) {
                            /** @var Customer360Service $customer360Service */
                            $customer360Service = app(Customer360Service::class);
                            $snapshot = $customer360Service->getCustomer360Snapshot((int) $record->id);

                            return [
                                Section::make(__('telesale.customer360.summary'))
                                    ->schema([
                                        Grid::make(4)->schema([
                                            Placeholder::make('c360_total_revenue')
                                                ->label(__('telesale.customer360.total_revenue'))
                                                ->content(number_format((float) ($snapshot['total_revenue'] ?? 0))),
                                            Placeholder::make('c360_debt_amount')
                                                ->label(__('telesale.customer360.debt_amount'))
                                                ->content(number_format((float) ($snapshot['debt_amount'] ?? 0))),
                                            Placeholder::make('c360_total_orders')
                                                ->label(__('telesale.customer360.total_orders'))
                                                ->content((string) count($snapshot['orders'] ?? [])),
                                            Placeholder::make('c360_latest_status')
                                                ->label(__('telesale.customer360.latest_order_status'))
                                                ->content($snapshot['latest_order_status'] ? OrderStatus::getLabel((int) $snapshot['latest_order_status']) : '-'),
                                        ]),
                                    ]),
                                Section::make(__('telesale.customer360.timeline'))
                                    ->schema([
                                        \Filament\Forms\Components\ViewField::make('interactions_timeline_c360')
                                            ->label('')
                                            ->view('filament.components.customer-interactions-timeline')
                                            ->columnSpanFull(),
                                    ]),
                            ];
                        }),

                    ViewAction::make()
                        ->label(__('common.action.view'))
                        ->tooltip(__('common.tooltip.view'))
                        ->icon('heroicon-o-eye'),

                    Action::make('blacklist')
                        ->label(__('telesale.table.blacklist'))
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->form([
                            Textarea::make('note')
                                ->label(__('common.table.note'))
                                ->required()
                                ->validationMessages([
                                    'required' => __('common.error.required'),
                                ]),
                        ])
                        ->action(function ($record, array $data) {
                            $record->blackList()->create([
                                'note' => $data['note'],
                                'user_id' => Auth::id(),
                            ]);
                            Notification::make()->title(__('common.success.update_success'))->success()->send();
                        })
                        ->visible(fn($record) => !$record->blackList),

                    Action::make('unblacklist')
                        ->label(__('telesale.table.unblacklist'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($record) {
                            $record->blackList()->delete();
                            Notification::make()->title(__('common.success.update_success'))->success()->send();
                        })
                        ->visible(fn($record) => $record->blackList),

                    // FIRST_CALL Action
                    Action::make('first_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::FIRST_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('danger')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                TextInput::make('address')
                                    ->label(__('common.table.address'))
                                    ->disabled()
                                    ->default($record->address)
                                    ->visible($record->address ? true : false),
                                TextInput::make('ward')
                                    ->label(__('common.table.ward'))
                                    ->disabled()
                                    ->default($record->ward?->name)
                                    ->visible($record->ward ? true : false),
                                TextInput::make('district')
                                    ->label(__('common.table.district'))
                                    ->disabled()
                                    ->default($record->district?->name)
                                    ->visible($record->district ? true : false),
                                TextInput::make('province')
                                    ->label(__('common.table.province'))
                                    ->disabled()
                                    ->default($record->province?->name)
                                    ->visible($record->province ? true : false),
                                TextInput::make('email')->disabled()->default($record->email)->visible($record->email ? true : false),
                                TextInput::make('note')
                                    ->label(__('common.table.note'))
                                    ->disabled()
                                    ->default($record->note)
                                    ->visible($record->note ? true : false),
                                TextInput::make('source')
                                    ->disabled()
                                    ->label(__('common.table.source'))
                                    ->default(IntegrationType::getLabel($record->source))
                                    ->visible($record->source ? true : false),
                                TextInput::make('product')
                                    ->disabled()
                                    ->label(__('common.table.product'))
                                    ->default($record->product?->name)
                                    ->visible($record->product ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(
                                        fn($get) =>
                                        ReasonInteraction::requiresScheduling((int) $get('reason'))
                                    )
                                    ->visible(
                                        fn($get) =>
                                        ReasonInteraction::requiresScheduling((int) $get('reason'))
                                    )
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                // Xác định trạng thái tiếp theo dựa trên lý do
                                $nextStatus = ReasonInteraction::getNextStatus(
                                    $data['reason'],
                                    $record->interaction_status
                                );

                                // Tạo log
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);

                                // Cập nhật trạng thái
                                $record->interaction_status = $nextStatus;

                                // Nếu cần lên lịch (CALL_BACK, THINK_MORE)
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }

                                $record->save();

                                Notification::make()
                                    ->title(__('common.success.update_success'))
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()
                                    ->title(__('common.error.update_error'))
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FIRST_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::FIRST_CALL->value),

                    // SECOND_CALL Action
                    Action::make('second_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::SECOND_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('warning')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SECOND_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::SECOND_CALL->value),

                    // THIRD_CALL Action
                    Action::make('third_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::THIRD_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('info')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::THIRD_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::THIRD_CALL->value),

                    // FOURTH_CALL Action
                    Action::make('fourth_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::FOURTH_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('primary')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FOURTH_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::FOURTH_CALL->value),

                    // FIFTH_CALL Action
                    Action::make('fifth_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::FIFTH_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('success')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FIFTH_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::FIFTH_CALL->value),

                    // SIXTH_CALL Action
                    Action::make('sixth_call')
                        ->label(InteractionStatus::getLabel(InteractionStatus::SIXTH_CALL->value))
                        ->icon('heroicon-o-phone')
                        ->color('gray')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SIXTH_CALL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::SIXTH_CALL->value),

                    // USER_MANUAL Action
                    Action::make('user_manual')
                        ->label(InteractionStatus::getLabel(InteractionStatus::USER_MANUAL->value))
                        ->icon('heroicon-o-book-open')
                        ->color('indigo')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::USER_MANUAL->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::USER_MANUAL->value),

                    // SECOND_CARE Action
                    Action::make('second_care')
                        ->label(InteractionStatus::getLabel(InteractionStatus::SECOND_CARE->value))
                        ->icon('heroicon-o-heart')
                        ->color('pink')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SECOND_CARE->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::SECOND_CARE->value),

                    // THIRD_CARE Action
                    Action::make('third_care')
                        ->label(InteractionStatus::getLabel(InteractionStatus::THIRD_CARE->value))
                        ->icon('heroicon-o-heart')
                        ->color('rose')
                        ->schema(fn($record) => [
                            Grid::make(2)->schema([
                                TextInput::make('phone')
                                    ->label(__('common.table.phone'))
                                    ->disabled()
                                    ->default($record->phone)
                                    ->visible($record->phone ? true : false),
                                TextInput::make('name')
                                    ->label(__('common.table.name'))
                                    ->disabled()
                                    ->default($record->username)
                                    ->visible($record->username ? true : false),
                                Select::make('reason')
                                    ->options(ReasonInteraction::options())
                                    ->label(__('common.table.result'))
                                    ->required()
                                    ->live()
                                    ->validationMessages([
                                        'required' => __('common.error.required'),
                                    ]),
                                DateTimePicker::make('next_action_at')
                                    ->label(__('telesale.table.next_action'))
                                    ->native(false)
                                    ->displayFormat('d/m/Y H:i')
                                    ->seconds(false)
                                    ->minutesStep(15)
                                    ->required(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->visible(fn($get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
                                    ->helperText(__('telesale.helper.schedule_callback'))
                                    ->validationMessages(['required' => __('common.error.required')]),
                            ])
                        ])
                        ->action(function (array $data, $record) {
                            try {
                                $nextStatus = ReasonInteraction::getNextStatus($data['reason'], $record->interaction_status);
                                $record->customerStatusLog()->create([
                                    'from_status' => $record->interaction_status,
                                    'to_status' => $nextStatus,
                                    'reason' => $data['reason'],
                                    'user_id' => Auth::user()->id,
                                ]);

                                self::logCustomerInteraction($record, $nextStatus);
                                $record->interaction_status = $nextStatus;
                                if (ReasonInteraction::requiresScheduling($data['reason']) && isset($data['next_action_at'])) {
                                    $record->next_action_at = $data['next_action_at'];
                                }
                                $record->save();
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } catch (Throwable $e) {
                                Log::error('Interaction Action Error: ' . $e->getMessage());
                                Notification::make()->title(__('common.error.update_error'))->warning()->send();
                            }
                        })
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::THIRD_CARE->value))
                        ->modalSubmitActionLabel(__('common.action.save'))
                        ->visible(fn($record): bool => $record->interaction_status == InteractionStatus::THIRD_CARE->value),

                    DeleteAction::make()
                        ->label(__('common.action.delete'))
                        ->tooltip(__('common.tooltip.delete'))
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading(__('common.modal.delete_title'))
                        ->modalDescription(__('common.modal.delete_confirm'))
                        ->modalSubmitActionLabel(__('common.action.confirm_delete'))
                        ->visible(fn($record) => !$record->trashed()),

                    RestoreAction::make()
                        ->label(__('common.action.restore'))
                        ->tooltip(__('common.tooltip.restore'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn($record) => $record->trashed()),
                ])
            ], position: \Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)
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
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->persistFiltersInSession()
            ->poll('30s');
    }

    private static function logCustomerInteraction($record, int $nextStatus): void
    {
        $lastAttempt = (int) ($record->interactions()->max('attempt_no') ?? 0);
        $lastCare = (int) ($record->interactions()->max('care_no') ?? 0);

        $record->interactions()->create([
            'user_id' => Auth::id(),
            'type' => 1,
            'channel' => 'call',
            'attempt_no' => max(1, $lastAttempt + 1),
            'care_no' => max(1, $lastCare + 1),
            'status' => $nextStatus,
            'interacted_at' => now(),
            'content' => InteractionStatus::getLabelStatus($nextStatus),
        ]);
    }
}
