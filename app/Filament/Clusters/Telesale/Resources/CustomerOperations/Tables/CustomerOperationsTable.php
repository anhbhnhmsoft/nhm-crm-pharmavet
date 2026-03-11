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
use App\Models\Province;
use App\Models\Ward;
use App\Services\OrderService;
use App\Common\Constants\User\UserRole;
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
                                                ->formatStateUsing(fn($record) => $record->province_id)
                                                ->options(Province::all()->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->afterStateUpdated(fn($state, $get, $set) => $set('district_id', null))
                                                ->required()
                                                ->validationMessages([
                                                    'required' => __('common.error.required')
                                                ]),
                                            Select::make('district_id')
                                                ->label(__('warehouse.order.form.district'))
                                                ->formatStateUsing(fn($record) => $record->district_id)
                                                ->options(fn($get) => District::where('province_id', $get('province_id'))->pluck('name', 'id'))
                                                ->searchable()
                                                ->live()
                                                ->afterStateUpdated(fn($state, $get, $set) => $set('ward_id', null))
                                                ->required()
                                                ->validationMessages([
                                                    'required' => __('common.error.required')
                                                ]),
                                            Select::make('ward_id')
                                                ->label(__('warehouse.order.form.ward'))
                                                ->formatStateUsing(fn($record) => $record->ward_id)
                                                ->options(fn($get) => Ward::where('district_id', $get('district_id'))->pluck('name', 'id'))
                                                ->searchable()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => __('common.error.required')
                                                ]),
                                            TextInput::make('address')->label(__('warehouse.order.form.address'))->formatStateUsing(fn($record) => $record->address),
                                        ]),
                                    Section::make(__('warehouse.order.form.info'))
                                        ->columnSpan(2)
                                        ->schema([
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

                                            Repeater::make('items')
                                                ->label(__('warehouse.order.form.product'))
                                                ->schema([
                                                    Select::make('product_id')
                                                        ->label(__('warehouse.order.form.product'))
                                                        ->options(Product::all()->pluck('name', 'id'))
                                                        ->searchable()
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $get, $set) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('price', $product->sale_price ?? 0);
                                        }
                                    })
                                                        ->required()
                                                        ->live()
                                                        ->afterStateUpdated(function ($state, $get, $set) {
                                        $set('quantity', 1);
                                        $set('total', $get('price') * $get('quantity'));
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
                                                        ->afterStateUpdated(function ($state, $get, $set) {
                                        $set('total', $state * $get('price'));
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
                                                ->afterStateUpdated(function ($get, $set) {
                                    $items = $get('items');
                                    $total = collect($items)->sum(function ($item) {
                                        if ($item['price'] == null || $item['quantity'] == null || $item['price'] == 0 || is_int($item['quantity']) == false) {
                                            return 0;
                                        }
                                        return ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
                                    });
                                    $set('total_amount_temp', $total);
                                }),

                                            Grid::make(3)->schema([
                                                TextInput::make('discount')
                                                    ->label(__('warehouse.order.form.discount'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                    $productTotal = $get('total_amount_temp') ?? 0;
                                    $ck1 = $get('ck1') ?? 0;
                                    $ck2 = $get('ck2') ?? 0;
                                    $orderDiscount = $get('discount') ?? 0;
                                    $productDiscount = $productTotal * ($ck1 + $ck2) / 100;
                                    $totalDiscount = $productDiscount + $orderDiscount;
                                    $set('total_discount_display', number_format($totalDiscount) . ' VNĐ');
                                }),
                                                TextInput::make('ck1')
                                                    ->label('CK1 (%)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                    $productTotal = $get('total_amount_temp') ?? 0;
                                    $ck1 = $get('ck1') ?? 0;
                                    $ck2 = $get('ck2') ?? 0;
                                    $orderDiscount = $get('discount') ?? 0;
                                    $productDiscount = $productTotal * ($ck1 + $ck2) / 100;
                                    $totalDiscount = $productDiscount + $orderDiscount;
                                    $set('total_discount_display', number_format($totalDiscount) . ' VNĐ');
                                })
                                                    ->validationMessages([
                                                        'min' => __('common.error.min_value', ['min' => 0]),
                                                        'max' => __('common.error.max_value', ['max' => 100]),
                                                    ]),
                                                TextInput::make('ck2')
                                                    ->label('CK2 (%)')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                    $productTotal = $get('total_amount_temp') ?? 0;
                                    $ck1 = $get('ck1') ?? 0;
                                    $ck2 = $get('ck2') ?? 0;
                                    $orderDiscount = $get('discount') ?? 0;
                                    $productDiscount = $productTotal * ($ck1 + $ck2) / 100;
                                    $totalDiscount = $productDiscount + $orderDiscount;
                                    $set('total_discount_display', number_format($totalDiscount) . ' VNĐ');
                                })
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
                                                    ->afterStateUpdated(fn($state, Set $set) => $set('cod_amount', round($state))),
                                                TextInput::make('cod_amount')->label(__('warehouse.order.form.cod_amount'))->numeric(),
                                            ]),

                                            TextInput::make('deposit')->label(__('warehouse.order.form.deposit'))->numeric()->default(0),

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

                            $data['customer_id'] = $record->id;
                            $data['organization_id'] = $record->organization_id;
                            $data['created_by'] = Auth::id();
                            $data['updated_by'] = Auth::id();

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
}
