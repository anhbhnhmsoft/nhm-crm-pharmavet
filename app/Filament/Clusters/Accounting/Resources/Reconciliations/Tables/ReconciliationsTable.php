<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Tables;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\CacheKey;
use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Shipping\RequiredNote;
use App\Core\Caching;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Models\User;
use App\Common\Constants\User\UserRole;
use App\Models\Warehouse;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;

class ReconciliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.createdBy.name')
                    ->label('Sale')
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->order || !$record->order->createdBy) {
                            return '-';
                        }

                        $name = mb_strtoupper($record->order->createdBy->name);
                        $username = strtolower($record->order->createdBy->username);

                        return "<div class='text-sm font-medium text-gray-900 mb-0.5 text-center'>{$name}</div>
                                <div class='text-xs text-gray-400 text-center'>({$username})</div>";
                    })
                    ->searchable()
                    ->sortable()
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('order.code')
                    ->label(new HtmlString('<div class="text-center font-semibold">Ngày data về<br>Mã đơn<br>Ngày chốt đơn</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->order) {
                            return '-';
                        }

                        $dataDate = (string) ($record->order->customer?->created_at?->format('d/m/Y H:i:s') ?? '');
                        $code = (string) ($record->order->code ?? '-');
                        $orderDate = (string) ($record->order->created_at?->format('d/m/Y H:i') ?? '');

                        return "
                            <div class='text-xs text-gray-500 whitespace-nowrap mb-1 text-center'>{$dataDate}</div>
                            <div class='text-sm font-bold text-primary-600 mb-1 text-center'>{$code}</div>
                            <div class='text-xs text-gray-500 whitespace-nowrap text-center'>{$orderDate}</div>
                        ";
                    })
                    ->copyable()
                    ->copyableState(fn($record) => $record->order?->code)
                    ->searchable()
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('order.warehouse.name')
                    ->label(new HtmlString('<div class="text-center font-semibold">Kho<br>PTGH<br>Mã giao vận</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $warehouse = $record->order?->warehouse?->name ?? '-';
                        $provider = $record->order?->provider_shipping ?? ($record->order?->shipping_method ?? '-');
                        $ghnCode = $record->ghn_order_code ?? $record->order?->ghn_order_code ?? '-';

                        return "
                            <div class='text-sm font-semibold text-gray-900 text-center'>{$warehouse}</div>
                            <div class='text-xs text-emerald-600 font-medium text-center'>{$provider}</div>
                            <div class='text-xs text-primary-600 font-semibold text-center'>{$ghnCode}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('note')
                    ->label(new HtmlString('<div class="text-center font-semibold">Ngày cập nhật care đơn<br>Care đơn<br>Ghi chú kế toán</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $updated = $record->updated_at?->format('d/m/Y H:i') ?? '-';
                        $care = $record->order?->updatedBy?->name ?? '-';
                        $note = !empty($record->note) ? e($record->note) : '-';

                        return "
                            <div class='text-xs text-gray-500 text-center'>{$updated}</div>
                            <div class='text-sm text-gray-900 text-center'>{$care}</div>
                            <div class='text-xs text-gray-500 text-center line-clamp-2'>{$note}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('order.ghn_status')
                    ->label(new HtmlString('<div class="text-center font-semibold">Ngày cập nhật<br>Trạng thái giao hàng<br>Ngày đăng đơn</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $updated = $record->updated_at?->format('d/m/Y H:i') ?? '-';
                        $status = $record->order?->ghn_status;
                        $label = GhnOrderStatus::getLabel($status);
                        $posted = $record->order?->ghn_posted_at ? Carbon::parse($record->order->ghn_posted_at)->format('d/m/Y H:i') : ($record->order?->created_at?->format('d/m/Y H:i') ?? '-');

                        return "
                            <div class='text-xs text-gray-500 text-center'>{$updated}</div>
                            <div class='text-sm font-semibold text-center text-primary-600'>{$label}</div>
                            <div class='text-xs text-gray-500 text-center'>{$posted}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('order.items')
                    ->label(new HtmlString('<div class="text-center font-semibold">Sản phẩm - Số lượng - Đơn giá</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $items = $record->order?->items;

                        if (!$items || $items->isEmpty()) {
                            return '-';
                        }

                        return $items->take(3)->map(function ($item) {
                            $name = e($item->product?->name ?? 'SP');
                            $qty = number_format((float) $item->quantity, 0, ',', '.');
                            $price = number_format((float) $item->price, 0, ',', '.');

                            return "<div class='text-xs mb-1'><span class='font-medium'>{$name}</span> x{$qty} <span class='text-gray-500'>{$price}</span></div>";
                        })->implode('');
                    })
                    ->alignLeft(),

                TextColumn::make('order.total_amount')
                    ->label('Thành tiền')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('order.discount')
                    ->label('CK')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('shipping_fee')
                    ->label('Phí VC thu của khách')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('total_fee')
                    ->label('Tổng tiền')
                    ->money('VND')
                    ->alignEnd()
                    ->summarize([
                        Sum::make()->money('VND'),
                    ]),

                TextColumn::make('order.deposit')
                    ->label('Đặt cọc')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('order.amount_recived_from_customer')
                    ->label('Tiền thu của khách')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('shipping_fee')
                    ->label('Giá dịch vụ VC')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('order.amout_support_fee')
                    ->label('Phí VC hỗ trợ khách')
                    ->money('VND')
                    ->alignEnd(),

                TextColumn::make('order.customer.username')
                    ->label(new HtmlString('<div class="text-center font-semibold">Họ tên<br>Số điện thoại</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $name = e($record->order?->customer?->username ?? '-');
                        $phone = e($record->order?->customer?->phone ?? '');

                        return "
                            <div class='text-sm font-semibold text-center'>{$name}</div>
                            <div class='text-xs text-primary-600 text-center'>{$phone}</div>
                        ";
                    })
                    ->alignCenter()
                    ->size('sm'),

                TextColumn::make('order.shipping_address')
                    ->label(new HtmlString('<div class="text-center font-semibold">Địa chỉ<br>Ghi chú giao hàng</div>'))
                    ->html()
                    ->formatStateUsing(function ($state, $record) {
                        $address = e($record->order?->shipping_address ?? '-');
                        $note = e($record->order?->note ?? '-');

                        return "
                            <div class='text-sm text-gray-900'>{$address}</div>
                            <div class='text-xs text-gray-500'>{$note}</div>
                        ";
                    }),

                TextColumn::make('status')
                    ->label('Trạng thái đối soát')
                    ->formatStateUsing(fn($state) => ReconciliationStatus::getOptions()[$state] ?? '-')
                    ->alignCenter()
                    ->size('sm'),
            ])
            ->filters([
                Filter::make('reconciliation_date_range')
                    ->label('Khoảng ngày đối soát')
                    ->form([
                        DatePicker::make('from')
                            ->label(__('accounting.reconciliation.from_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('to')
                            ->label(__('accounting.reconciliation.to_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reconciliation_date', '>=', $date),
                            )
                            ->when(
                                $data['to'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reconciliation_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('Từ ngày: ' . Carbon::parse($data['from'])->format('d/m/Y'))
                                ->removeField('from');
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = Indicator::make('Đến ngày: ' . Carbon::parse($data['to'])->format('d/m/Y'))
                                ->removeField('to');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('sale_id')
                    ->label('Sale (Tạo đơn)')
                    ->options(function () {
                        return User::where('role', UserRole::SALE->value)->pluck('name', 'id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value']) || $data['value'] === '0') {
                            $query->whereHas('order', fn($q) => $q->where('created_by', $data['value']));
                        }
                        return $query;
                    }),

                SelectFilter::make('warehouse_id')
                    ->label('Kho hàng')
                    ->options(function () {
                        return Warehouse::pluck('name', 'id');
                    })
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value']) || $data['value'] === '0') {
                            $query->whereHas('order', fn($q) => $q->where('warehouse_id', $data['value']));
                        }
                        return $query;
                    }),

                TernaryFilter::make('has_deposit')
                    ->label('Đặt cọc')
                    ->placeholder('Tất cả')
                    ->trueLabel('Có đặt cọc')
                    ->falseLabel('Không/Chưa cọc')
                    ->queries(
                        true: fn(Builder $query) => $query->whereHas('order', fn($q) => $q->where('deposit', '>', 0)),
                        false: fn(Builder $query) => $query->whereHas('order', fn($q) => $q->where(function ($sub) {
                            $sub->whereNull('deposit')->orWhere('deposit', '<=', 0);
                        })),
                        blank: fn(Builder $query) => $query,
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                Action::make('view_detail')
                    ->label(__('accounting.reconciliation.view_detail'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(__('accounting.reconciliation.order_detail_modal_title'))
                    ->modalWidth('4xl')
                    ->mountUsing(function ($form, $record) {
                        $service = app(ReconciliationService::class);
                        $result = $service->getOrderDetailFromGHN($record->id);

                        if ($result->isError()) {
                            $form->fill(['error' => $result->getMessage()]);
                            return;
                        }

                        $orderDetail = $result->getData();
                        $fee = $orderDetail['fee'] ?? [];

                        // Cache order detail để dùng khi submit (15 phút = 900 giây = 15 minutes)
                        Caching::setCache(CacheKey::GHN_ORDER_DETAIL, $orderDetail, (string) $record->id, 15);

                        $form->fill([
                            'order_code' => $orderDetail['order_code'] ?? '',
                            'status' => GhnOrderStatus::tryFrom($orderDetail['status'] ?? '')?->label()
                                ?? ($orderDetail['status'] ?? ''),
                            'created_date' => $orderDetail['created_date'] ?? '',
                            'to_name' => $orderDetail['to_name'] ?? '',
                            'to_phone' => $orderDetail['to_phone'] ?? '',
                            'to_address' => $orderDetail['to_address'] ?? '',
                            'cod_amount' => $orderDetail['cod_amount'] ?? 0,
                            'payment_type_id' => $orderDetail['payment_type_id'] ?? null,
                            'weight' => $orderDetail['weight'] ?? 0,
                            'length' => $orderDetail['length'] ?? 0,
                            'width' => $orderDetail['width'] ?? 0,
                            'height' => $orderDetail['height'] ?? 0,
                            'note' => $orderDetail['note'] ?? '',
                            'content' => $orderDetail['content'] ?? '',
                            'required_note' => $orderDetail['required_note'] ?? '',
                            'main_service_fee' => $fee['main_service'] ?? 0,
                            'cod_fee' => $fee['cod_fee'] ?? 0,
                            'storage_fee' => $fee['station_do'] ?? 0,
                            'total_fee' => $orderDetail['total_fee'] ?? 0,
                        ]);
                    })
                    ->extraModalFooterActions(fn($record) => [
                        Action::make('sync_from_ghn')
                            ->label(__('accounting.reconciliation.sync_from_ghn'))
                            ->icon('heroicon-o-arrow-path')
                            ->color('info')
                            ->action(function ($record) {
                                $service = app(ReconciliationService::class);
                                $result = $service->getOrderDetailFromGHN($record->id);

                                if ($result->isError()) {
                                    Notification::make()
                                        ->danger()
                                        ->title(__('accounting.reconciliation.detail_load_failed'))
                                        ->body($result->getMessage())
                                        ->send();
                                    return;
                                }

                                $orderDetail = $result->getData();
                                $fee = $orderDetail['fee'] ?? [];

                                // Cache order detail (15 phút)
                                Caching::setCache(CacheKey::GHN_ORDER_DETAIL, $orderDetail, (string) $record->id, 15);

                                Notification::make()
                                    ->success()
                                    ->title(__('accounting.reconciliation.detail_loaded'))
                                    ->body(__('accounting.reconciliation.close_and_reopen_modal'))
                                    ->send();
                            }),
                    ])
                    ->form([
                        Section::make(__('accounting.reconciliation.basic_info'))
                            ->schema([
                                TextInput::make('order_code')
                                    ->label(__('accounting.reconciliation.ghn_order_code'))
                                    ->disabled(),
                                TextInput::make('status')
                                    ->label(__('accounting.reconciliation.status'))
                                    ->disabled(),
                                TextInput::make('created_date')
                                    ->label(__('accounting.reconciliation.created_date'))
                                    ->disabled(),
                            ])
                            ->columns(3),

                        Section::make(__('accounting.reconciliation.receiver_info'))
                            ->schema([
                                TextInput::make('to_name')
                                    ->label(__('accounting.reconciliation.to_name')),
                                TextInput::make('to_phone')
                                    ->label(__('accounting.reconciliation.to_phone')),
                                Textarea::make('to_address')
                                    ->label(__('accounting.reconciliation.to_address'))
                                    ->rows(2),
                            ])
                            ->columns(2),

                        Section::make(__('accounting.reconciliation.product_info'))
                            ->schema([
                                TextInput::make('weight')
                                    ->label(__('accounting.reconciliation.weight'))
                                    ->numeric()
                                    ->suffix('g')
                                    ->helperText(__('accounting.reconciliation.weight_help')),
                                TextInput::make('length')
                                    ->label(__('accounting.reconciliation.length'))
                                    ->numeric()
                                    ->suffix('cm'),
                                TextInput::make('width')
                                    ->label(__('accounting.reconciliation.width'))
                                    ->numeric()
                                    ->suffix('cm'),
                                TextInput::make('height')
                                    ->label(__('accounting.reconciliation.height'))
                                    ->numeric()
                                    ->suffix('cm'),
                            ])
                            ->columns(4),

                        Section::make(__('accounting.reconciliation.notes'))
                            ->schema([
                                Textarea::make('note')
                                    ->label(__('accounting.reconciliation.note'))
                                    ->rows(2),
                                Textarea::make('content')
                                    ->label(__('accounting.reconciliation.content'))
                                    ->rows(2),
                                Select::make('required_note')
                                    ->label(__('accounting.reconciliation.required_note'))
                                    ->options(RequiredNote::getOptions())
                                    ->native(false),
                            ])
                            ->columns(1),

                        Section::make(__('accounting.reconciliation.financial_info'))
                            ->schema([
                                TextInput::make('cod_amount')
                                    ->label(__('accounting.reconciliation.cod_amount'))
                                    ->numeric()
                                    ->prefix('₫'),
                                TextInput::make('payment_type_id')
                                    ->label(__('accounting.reconciliation.payment_type'))
                                    ->numeric()
                                    ->helperText(__('accounting.reconciliation.payment_type_help')),
                                TextInput::make('main_service_fee')
                                    ->label(__('accounting.reconciliation.main_service_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                                TextInput::make('cod_fee')
                                    ->label(__('accounting.reconciliation.cod_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                                TextInput::make('storage_fee')
                                    ->label(__('accounting.reconciliation.storage_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                                TextInput::make('total_fee')
                                    ->label(__('accounting.reconciliation.total_fee'))
                                    ->prefix('₫')
                                    ->disabled(),
                            ])
                            ->columns(3),

                        Textarea::make('error')
                            ->label(__('accounting.reconciliation.error'))
                            ->disabled()
                            ->visible(fn($get) => !empty($get('error'))),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(ReconciliationService::class);

                        $orderDetail = Caching::getCache(CacheKey::GHN_ORDER_DETAIL, (string) $record->id);

                        $fields = [
                            'cod_amount',
                            'to_name',
                            'to_phone',
                            'to_address',
                            'payment_type_id',
                            'weight',
                            'length',
                            'width',
                            'height',
                            'note',
                            'content',
                            'required_note',
                        ];

                        $updateData = [];

                        foreach ($fields as $field) {
                            if ($field === 'to_address' && isset($data['to_address'])) {
                                if ($data['to_address'] !== ($orderDetail['to_address'] ?? '')) {
                                    $updateData['to_address'] = $data['to_address'];
                                    $updateData['to_ward_code'] = $orderDetail['to_ward_code'] ?? null;
                                    $updateData['to_district_id'] = $orderDetail['to_district_id'] ?? null;
                                }
                                continue;
                            }
                            if (isset($data[$field]) && $data[$field] !== ($orderDetail[$field] ?? (is_numeric($data[$field]) ? 0 : ''))) {
                                $updateData[$field] = $data[$field];
                            }
                        }

                        if (count($updateData) === 0) {
                            Notification::make()
                                ->title(__('accounting.reconciliation.no_changes'))
                                ->body(__('accounting.reconciliation.no_update_required'))
                                ->info()
                                ->send();
                            return;
                        }

                        $result = $service->updateOrderOnGHN($record->id, $updateData);

                        if ($result->isError()) {
                            Notification::make()
                                ->title(__('accounting.reconciliation.order_update_failed'))
                                ->body($result->getMessage())
                                ->danger()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('accounting.reconciliation.order_updated'))
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('confirm')
                    ->label(__('accounting.reconciliation.confirm'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->status === ReconciliationStatus::PENDING->value)
                    ->action(function ($record) {
                        $service = app(ReconciliationService::class);
                        $result = $service->confirmReconciliation($record->id);

                        if ($result->isError()) {
                            Notification::make()
                                ->danger()
                                ->title(__('accounting.reconciliation.confirm_failed'))
                                ->body($result->getMessage())
                                ->send();
                        } else {
                            Notification::make()
                                ->success()
                                ->title(__('accounting.reconciliation.confirmed'))
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('reconciliation_date', 'desc');
    }
}

