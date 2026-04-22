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
use App\Models\ShippingShop;
use App\Models\Ward;
use App\Models\Organization;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Services\Telesale\OrderFinanceService;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class CustomerOperationsTable
{
    protected static function interactionModalDescription($record): string
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

    protected static function getLatestOrder($record): ?Order
    {
        if ($record->relationLoaded('customerOperationsLatestOrder')) {
            return $record->getRelation('customerOperationsLatestOrder');
        }

        $latestOrder = $record->orders()->latest()->first();
        $record->setRelation('customerOperationsLatestOrder', $latestOrder);

        return $latestOrder;
    }

    protected static function getLatestOrderStatus($record): ?int
    {
        return self::getLatestOrder($record)?->status;
    }

    protected static function isFinalizeOrderLockedForSale($record): bool
    {
        if ((int) (Auth::user()?->role ?? 0) !== UserRole::SALE->value) {
            return false;
        }

        return in_array(
            (int) (self::getLatestOrderStatus($record) ?? 0),
            [OrderStatus::SHIPPING->value, OrderStatus::COMPLETED->value],
            true
        );
    }

    protected static function getOrderFinanceInputs(Get $get, ?float $depositOverride = null): array
    {
        $items = $get('items') ?? [];
        $productTotal = collect($items)->sum(function ($item) {
            return ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
        });

        return [
            'product_total' => $productTotal,
            'discount' => $get('discount') ?? 0,
            'ck1' => $get('ck1') ?? 0,
            'ck2' => $get('ck2') ?? 0,
            'shipping_fee' => $get('shipping_fee') ?? 0,
            'cod_fee' => $get('cod_fee') ?? 0,
            'deposit' => $depositOverride ?? ($get('deposit') ?? 0),
            'cod_support_amount' => $get('cod_support_amount') ?? 0,
        ];
    }

    protected static function getMaxAllowedDeposit(Get $get): float
    {
        $financeService = app(OrderFinanceService::class);
        $results = $financeService->calculatePreview(self::getOrderFinanceInputs($get, 0));

        return (float) ($results['gross_total'] ?? 0);
    }

    protected static function formatCurrency(float|int $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' VNĐ';
    }

    protected static function resetOrderSummary(Set $set): void
    {
        $set('total_amount_temp', 0);
        $set('total_discount_display', self::formatCurrency(0));
        $set('cod_amount', 0);
    }

    protected static function getWarehouseOptions(?int $organizationId): array
    {
        if (($organizationId ?? 0) <= 0) {
            return [];
        }

        return Warehouse::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected static function getAvailableStock(int $warehouseId, int $productId): int
    {
        if ($warehouseId <= 0 || $productId <= 0) {
            return 0;
        }

        $stock = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first(['quantity', 'pending_quantity']);

        return max(0, (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0));
    }

    protected static function getWarehouseProductOptions(?int $organizationId, ?int $warehouseId): array
    {
        if (($organizationId ?? 0) <= 0 || ($warehouseId ?? 0) <= 0) {
            return [];
        }

        $products = Product::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stocks = ProductWarehouse::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $products->pluck('id'))
            ->get(['product_id', 'quantity', 'pending_quantity'])
            ->keyBy('product_id');

        return $products
            ->mapWithKeys(function (Product $product) use ($stocks) {
                $stock = $stocks->get($product->id);
                $availableStock = max(0, (int) ($stock?->quantity ?? 0) - (int) ($stock?->pending_quantity ?? 0));

                return [
                    $product->id => sprintf(
                        '%s (%s: %d)',
                        $product->name,
                        __('warehouse.reports.available_stock'),
                        $availableStock
                    ),
                ];
            })
            ->all();
    }

    protected static function syncOrderLogistics(Set $set, array $items): void
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn($productId) => (int) $productId)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            $set('weight', 0);
            $set('length', 0);
            $set('width', 0);
            $set('height', 0);
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'weight', 'length', 'width', 'height'])
            ->keyBy('id');

        $totalWeight = 0;
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));

            if (! $product) {
                continue;
            }

            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $totalWeight += (int) ($product->weight ?? 200) * $quantity;
            $maxLength = max($maxLength, (int) ($product->length ?? 10));
            $maxWidth = max($maxWidth, (int) ($product->width ?? 10));
            $totalHeight += (int) ($product->height ?? 5) * $quantity;
        }

        $set('weight', $totalWeight);
        $set('length', $maxLength);
        $set('width', $maxWidth);
        $set('height', $totalHeight);
    }

    protected static function getExistingReservedQuantity(?Order $existingOrder, int $warehouseId, int $productId): int
    {
        if (
            ! $existingOrder
            || (int) $existingOrder->status !== OrderStatus::CONFIRMED->value
            || (int) $existingOrder->warehouse_id !== $warehouseId
            || ! $existingOrder->relationLoaded('items')
        ) {
            return 0;
        }

        return (int) $existingOrder->items
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    protected static function validateFinalizeOrderData(array $data, $record): void
    {
        $messages = [];
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);

        if ($warehouseId <= 0) {
            $messages['warehouse_id'] = __('telesale.messages.warehouse_required');
        }

        $items = array_values($data['items'] ?? []);
        if ($items === []) {
            $messages['items'] = __('common.error.min.array', ['min' => 1]);
        }

        $normalizedItems = collect($items)->map(function (array $item, int $index) {
            return [
                'index' => $index,
                'product_id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
            ];
        });

        foreach ($normalizedItems as $item) {
            if ($item['product_id'] <= 0) {
                $messages["items.{$item['index']}.product_id"] = __('common.error.required');
            }

            if ($item['quantity'] <= 0) {
                $messages["items.{$item['index']}.quantity"] = __('common.error.min_value', ['min' => 1]);
            }
        }

        $existingOrder = Order::query()
            ->with('items')
            ->where('customer_id', $record->id)
            ->whereIn('status', [OrderStatus::PENDING->value, OrderStatus::CONFIRMED->value])
            ->latest()
            ->first();

        if ($warehouseId > 0) {
            foreach ($normalizedItems->groupBy('product_id') as $productId => $rows) {
                $productId = (int) $productId;

                if ($productId <= 0) {
                    continue;
                }

                $requiredQuantity = (int) $rows->sum('quantity');
                if ($requiredQuantity <= 0) {
                    continue;
                }

                $availableQuantity = self::getAvailableStock($warehouseId, $productId)
                    + self::getExistingReservedQuantity($existingOrder, $warehouseId, $productId);

                if ($requiredQuantity > $availableQuantity) {
                    $productName = Product::query()->find($productId)?->name ?? '#' . $productId;
                    $messages["items.{$rows->first()['index']}.quantity"] = __('telesale.messages.insufficient_stock', [
                        'product' => $productName,
                    ]);
                }
            }
        }

        foreach ([
            'weight' => 20000,
            'length' => 200,
            'width' => 200,
            'height' => 200,
        ] as $field => $maxValue) {
            $value = $data[$field] ?? null;

            if ($value === null || $value === '') {
                $messages[$field] = __('common.error.required');
                continue;
            }

            if (! is_numeric($value)) {
                $messages[$field] = __('common.error.numeric');
                continue;
            }

            $numericValue = (float) $value;

            if ($numericValue < 1) {
                $messages[$field] = __('common.error.min_value', ['min' => 1]);
                continue;
            }

            if ($numericValue > $maxValue) {
                $messages[$field] = __('common.error.max_value', ['max' => $maxValue]);
            }
        }

        $productTotal = $normalizedItems->sum(function (array $item) use ($items) {
            $row = $items[$item['index']] ?? [];

            return max(0, (int) ($item['quantity'] ?? 0)) * (float) ($row['price'] ?? 0);
        });

        $preview = app(OrderFinanceService::class)->calculatePreview([
            'product_total' => $productTotal,
            'discount' => $data['discount'] ?? 0,
            'ck1' => $data['ck1'] ?? 0,
            'ck2' => $data['ck2'] ?? 0,
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'cod_fee' => $data['cod_fee'] ?? 0,
            'deposit' => $data['deposit'] ?? 0,
            'cod_support_amount' => $data['cod_support_amount'] ?? 0,
        ]);

        if ((float) ($data['deposit'] ?? 0) > (float) ($preview['gross_total'] ?? 0)) {
            $messages['deposit'] = __('telesale.messages.deposit_exceeds_total');
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    protected static function careResultField(): Select
    {
        return Select::make('reason')
            ->options(ReasonInteraction::options())
            ->label(__('common.table.result'))
            ->required()
            ->live()
            ->afterStateUpdated(function ($state, Set $set): void {
                if (! ReasonInteraction::requiresScheduling((int) $state)) {
                    $set('next_action_at', null);
                }
            })
            ->extraInputAttributes(['required' => false])
            ->validationMessages([
                'required' => __('common.error.required'),
            ]);
    }

    protected static function careNextActionField(): DateTimePicker
    {
        return DateTimePicker::make('next_action_at')
            ->label(__('telesale.table.next_action'))
            ->native(false)
            ->displayFormat('d/m/Y H:i')
            ->seconds(false)
            ->minutesStep(15)
            ->required(fn(Get $get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
            ->visible(fn(Get $get) => ReasonInteraction::requiresScheduling((int) $get('reason')))
            ->helperText(__('telesale.helper.schedule_callback'))
            ->validationMessages([
                'required' => __('common.error.required'),
            ]);
    }

    protected static function handleInteractionAction($record, array $data): void
    {
        try {
            $reason = (int) $data['reason'];
            $currentStatus = (int) $record->interaction_status;
            $nextStatus = ReasonInteraction::getNextStatus($reason, $currentStatus);

            $record->customerStatusLog()->create([
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'reason' => $reason,
                'user_id' => Auth::id(),
            ]);

            $record->interaction_status = $nextStatus;
            $record->next_action_at = ReasonInteraction::requiresScheduling($reason)
                ? ($data['next_action_at'] ?? null)
                : null;
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
    }

    public static function configure(Table $table): Table
    {
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

                TextColumn::make('latest_order_status')
                    ->label(__('telesale.customer360.latest_order_status'))
                    ->state(fn($record) => self::getLatestOrderStatus($record))
                    ->badge()
                    ->color(fn($state) => $state ? OrderStatus::color((int) $state) : 'gray')
                    ->formatStateUsing(fn($state) => $state ? OrderStatus::getLabel((int) $state) : '-')
                    ->description(fn($record) => self::getLatestOrder($record)?->code)
                    ->size('sm'),

                TextColumn::make('source')
                    ->label(__('telesale.table.source'))
                    ->badge()
                    ->color(fn ($state) => IntegrationType::tryFrom((int)$state)?->color() ?? 'gray')
                    ->formatStateUsing(fn($state) => IntegrationType::getLabel((int) $state))
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
                    ->formatStateUsing(fn($state) => $state ? InteractionStatus::getLabelStatus((int) $state) : '-')
                    ->size('sm'),
                TextColumn::make('blackList')
                    ->label(__('telesale.table.blacklist'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(fn($state) => $state ? __('telesale.table.blacklist') : __('telesale.table.unblacklist'))
                    ->size('sm'),
            ])
            ->filters([
                SelectFilter::make('customer_type')
                    ->label(__('telesale.table.customer_type'))
                    ->options(CustomerType::toOptions()),
                SelectFilter::make('source')
                    ->label(__('telesale.table.source'))
                    ->options(IntegrationType::toOptions()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('finalize_order')
                        ->label(__('warehouse.actions.finalize_order'))
                        ->icon('heroicon-o-shopping-cart')
                        ->color('success')
                        ->disabled(fn($record): bool => self::isFinalizeOrderLockedForSale($record))
                        ->tooltip(fn($record): ?string => self::isFinalizeOrderLockedForSale($record)
                            ? __('telesale.messages.order_edit_locked_for_sale')
                            : null)
                        ->modalWidth('7xl')
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                                ->options(function (Get $get, \App\Repositories\ShippingConfigRepository $repo) {
                                    $orgId = $get('organization_id');
                                    if (!$orgId)
                                        return [];
                                    try {
                                        $ghn = new \App\Services\GHNService($repo, (int) $orgId);
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
                                                ->options(function (Get $get, \App\Repositories\ShippingConfigRepository $repo) {
                                    $orgId = $get('organization_id');
                                    $provinceId = $get('ghn_province_id');
                                    if (!$orgId || !$provinceId)
                                        return [];
                                    try {
                                        $ghn = new \App\Services\GHNService($repo, (int) $orgId);
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
                                                ->options(function (Get $get, \App\Repositories\ShippingConfigRepository $repo) {
                                    $orgId = $get('organization_id');
                                    $districtId = $get('ghn_district_id');
                                    if (!$orgId || !$districtId)
                                        return [];
                                    try {
                                        $ghn = new \App\Services\GHNService($repo, (int) $orgId);
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
                                                    self::syncOrderLogistics($set, []);
                                                    self::resetOrderSummary($set);
                                                }),
                                            Select::make('warehouse_id')
                                                ->label(__('order.form.warehouse'))
                                                ->options(fn(Get $get) => self::getWarehouseOptions((int) $get('organization_id')))
                                                ->searchable()
                                                ->live()
                                                ->required()
                                                ->afterStateUpdated(function (Set $set) {
                                                    $set('items', []);
                                                    self::syncOrderLogistics($set, []);
                                                    self::resetOrderSummary($set);
                                                })
                                                ->validationMessages([
                                                    'required' => __('telesale.messages.warehouse_required'),
                                                ]),
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
                                                ->content(fn() => new \Illuminate\Support\HtmlString('<div class="text-xs text-gray-500 italic">* Trọng lượng và kích thước sẽ tự động tính toán dựa trên sản phẩm đã chọn.</div>')),

                                            Repeater::make('items')
                                                ->label(__('warehouse.order.form.product'))
                                                ->schema([
                                                    Select::make('product_id')
                                                        ->label(__('warehouse.order.form.product'))
                                                        ->options(fn(Get $get) => self::getWarehouseProductOptions(
                                                            (int) $get('../../organization_id'),
                                                            (int) $get('../../warehouse_id')
                                                        ))
                                                        ->searchable()
                                                        ->disabled(fn(Get $get) => (int) $get('../../warehouse_id') <= 0)
                                                        ->required()
                                                        ->live()
                                                        ->helperText(function (Get $get): ?string {
                                                            $warehouseId = (int) $get('../../warehouse_id');
                                                            $productId = (int) $get('product_id');

                                                            if ($warehouseId <= 0) {
                                                                return __('telesale.messages.warehouse_required');
                                                            }

                                                            if ($productId <= 0) {
                                                                return null;
                                                            }

                                                            return __('warehouse.reports.available_stock') . ': ' . self::getAvailableStock($warehouseId, $productId);
                                                        })
                                                        ->disableOptionWhen(
                                                            fn($value, $state, $get) =>
                                                            collect($get('../../items'))
                                                                ->pluck('product_id')
                                                                ->contains($value) && $value != $state
                                                        )
                                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                            $product = Product::find($state);
                                                            if ($product) {
                                                                $set('price', $product->sale_price ?? 0);
                                                                $set('quantity', 1);
                                                                $set('total', $product->sale_price ?? 0);
                                                            }

                                                            self::syncOrderLogistics($set, $get('../../items') ?? []);
                                                            self::recalculateOrderSummary($set, $get);
                                                        })
                                                        ->validationMessages([
                                                            'required' => __('common.error.required')
                                                        ]),
                                                    TextInput::make('quantity')
                                                        ->label(__('warehouse.order.form.quantity'))
                                                        ->integer()
                                                        ->default(1)
                                                        ->minValue(1)
                                                        ->required()
                                                        ->extraInputAttributes(self::numericInputAttributes('numeric'))
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                                            $set('total', max(0, (int) $state) * (float) ($get('price') ?? 0));
                                                            self::syncOrderLogistics($set, $get('../../items') ?? []);
                                                            self::recalculateOrderSummary($set, $get);
                                                        })
                                                        ->required()
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
                                                ->defaultItems(1)
                                                ->minItems(1)
                                                ->live(debounce: 500)
                                                ->validationMessages([
                                                    'min' => __('common.error.min.array', ['min' => 1]),
                                                ])
                                                ->afterStateUpdated(function (Get $get, Set $set) {
                                                    self::syncOrderLogistics($set, $get('items') ?? []);
                                                    self::recalculateOrderSummary($set, $get);
                                                }),

                                            Grid::make(3)->schema([
                                                // TextInput::make('shipping_fee')->label(__('warehouse.order.form.shipping_fee'))->numeric()->default(0),
                                                TextInput::make('discount')
                                                    ->label(__('warehouse.order.form.discount'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->default(0)
                                                    ->extraInputAttributes(self::numericInputAttributes())
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateOrderSummary($set, $get))
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
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateOrderSummary($set, $get))
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
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateOrderSummary($set, $get))
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
                                                // TextInput::make('shipping_fee')->label(__('warehouse.order.form.shipping_fee'))->numeric()->default(0),
                                                TextInput::make('cod_fee')
                                                    ->label(__('warehouse.order.form.cod_fee'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->extraInputAttributes(self::numericInputAttributes())
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateOrderSummary($set, $get))
                                                    ->validationMessages([
                                                        'numeric' => __('common.error.numeric'),
                                                        'min' => __('common.error.min_value', ['min' => 0]),
                                                    ]),
                                                TextInput::make('cod_amount')->label(__('warehouse.order.form.cod_amount'))->numeric()->disabled()->dehydrated(),
                                            ]),

                                            TextInput::make('deposit')
                                                ->label(__('warehouse.order.form.deposit'))
                                                ->numeric()
                                                ->minValue(0)
                                                ->maxValue(fn(Get $get): float => self::getMaxAllowedDeposit($get))
                                                ->default(0)
                                                ->extraInputAttributes(self::numericInputAttributes())
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn(Get $get, Set $set) => self::recalculateOrderSummary($set, $get))
                                                ->validationMessages([
                                                    'numeric' => __('common.error.numeric'),
                                                    'min' => __('common.error.min_value', ['min' => 0]),
                                                    'max' => __('telesale.messages.deposit_exceeds_total'),
                                                ]),

                                            Section::make(__('Logistics GHN'))
                                                ->schema([
                                                    Grid::make(4)->schema([
                                                        TextInput::make('weight')->label(__('warehouse.order.form.weight'))->numeric()->suffix('g')->required()->minValue(1)->maxValue(20000)->extraInputAttributes(self::numericInputAttributes('numeric'))->validationMessages([
                                                            'required' => __('common.error.required'),
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 1]),
                                                            'max' => __('common.error.max_value', ['max' => 20000]),
                                                        ]),
                                                        TextInput::make('length')->label(__('warehouse.order.form.length'))->numeric()->suffix('cm')->required()->minValue(1)->maxValue(200)->extraInputAttributes(self::numericInputAttributes('numeric'))->validationMessages([
                                                            'required' => __('common.error.required'),
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 1]),
                                                            'max' => __('common.error.max_value', ['max' => 200]),
                                                        ]),
                                                        TextInput::make('width')->label(__('warehouse.order.form.width'))->numeric()->suffix('cm')->required()->minValue(1)->maxValue(200)->extraInputAttributes(self::numericInputAttributes('numeric'))->validationMessages([
                                                            'required' => __('common.error.required'),
                                                            'numeric' => __('common.error.numeric'),
                                                            'min' => __('common.error.min_value', ['min' => 1]),
                                                            'max' => __('common.error.max_value', ['max' => 200]),
                                                        ]),
                                                        TextInput::make('height')->label(__('warehouse.order.form.height'))->numeric()->suffix('cm')->required()->minValue(1)->maxValue(200)->extraInputAttributes(self::numericInputAttributes('numeric'))->validationMessages([
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
                                                            ->options(function (Get $get) {
                                                                $orgId = $get('organization_id');
                                                                if (!$orgId) return [];
                                                                
                                                                return ShippingShop::where('organization_id', (int) $orgId)
                                                                    ->pluck('name', 'shop_id')
                                                                    ->toArray();
                                                            })
                                                            ->searchable(),
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
                        ->action(function (array $data, $record, OrderService $orderService) {
                            Log::info('finalize_order: Action triggered', [
                                'customer_id' => $record->id,
                                'user_id' => Auth::id(),
                                'data_keys' => array_keys($data)
                            ]);

                            $data['customer_id'] = $record->id;
                            $data['organization_id'] = $data['organization_id'] ?? $record->organization_id;
                            $data['code'] = $data['client_order_code'];
                            $data['created_by'] = Auth::id();
                            $data['updated_by'] = Auth::id();

                            self::validateFinalizeOrderData($data, $record);

                            $result = $orderService->finalizeOrder($data);

                            Log::info('finalize_order: Service result', [
                                'is_success' => $result->isSuccess(),
                                'message' => $result->getMessage()
                            ]);

                            if ($result->isSuccess()) {
                                Notification::make()->title(__('common.success.update_success'))->success()->send();
                            } else {
                                if ($result->getMessage() === __('telesale.messages.deposit_exceeds_total')) {
                                    throw ValidationException::withMessages([
                                        'deposit' => $result->getMessage(),
                                    ]);
                                }

                                Notification::make()->title($result->getMessage())->danger()->send();
                            }
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
                                    ->default(IntegrationType::getLabel((int) $record->source))
                                    ->visible($record->source ? true : false),
                                TextInput::make('product')
                                    ->disabled()
                                    ->label(__('common.table.product'))
                                    ->default($record->product?->name)
                                    ->visible($record->product ? true : false),
                                self::careResultField(),
                                self::careNextActionField(),
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
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SECOND_CALL->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::THIRD_CALL->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FOURTH_CALL->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::FIFTH_CALL->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SIXTH_CALL->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::USER_MANUAL->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::SECOND_CARE->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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
                                self::careResultField(),
                                self::careNextActionField(),
                            ])
                        ])
                        ->action(fn(array $data, $record) => self::handleInteractionAction($record, $data))
                        ->modalHeading(InteractionStatus::getLabel(InteractionStatus::THIRD_CARE->value))
                        ->modalDescription(fn($record) => self::interactionModalDescription($record))
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

    protected static function recalculateOrderSummary(Set $set, Get $get): void
    {
        $financeService = app(OrderFinanceService::class);
        $inputs = self::getOrderFinanceInputs($get);
        $results = $financeService->calculatePreview($inputs);

        $set('total_amount_temp', $inputs['product_total']);
        $set('total_discount_display', self::formatCurrency($results['total_discount']));
        $set('cod_amount', $results['collect_amount']);
    }
}
