<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations\Tables;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\CacheKey;
use App\Common\Constants\Order\GhnOrderStatus;
use App\Common\Constants\Shipping\RequiredNote;
use App\Core\Caching;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\ReconciliationResource;
use App\Services\ReconciliationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReconciliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reconciliation_date')
                    ->label(__('accounting.reconciliation.reconciliation_date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('ghn_order_code')
                    ->label(__('accounting.reconciliation.ghn_order_code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order.code')
                    ->label(__('accounting.reconciliation.order_code'))
                    ->searchable(),

                TextColumn::make('cod_amount')
                    ->label(__('accounting.reconciliation.cod_amount'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('shipping_fee')
                    ->label(__('accounting.reconciliation.shipping_fee'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('storage_fee')
                    ->label(__('accounting.reconciliation.storage_fee'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('total_fee')
                    ->label(__('accounting.reconciliation.total_fee'))
                    ->money('VND')
                    ->sortable()
                    ->summarize([
                        Sum::make()
                            ->money('VND'),
                    ]),

                TextColumn::make('exchangeRate.rate')
                    ->label(__('accounting.reconciliation.exchange_rate'))
                    ->formatStateUsing(fn ($state, $record) => $state ? number_format($state, 2) . ' ' . ($record->exchangeRate->to_currency ?? '') : '-'),

                TextColumn::make('converted_amount')
                    ->label(__('accounting.reconciliation.converted_amount'))
                    ->money('VND')
                    ->toggleable(),

                SelectColumn::make('status')
                    ->label(__('accounting.reconciliation.status'))
                    ->options(ReconciliationStatus::getOptions())
                    ->selectablePlaceholder(false)
                    ->disabled(),

                TextColumn::make('confirmed_at')
                    ->label(__('accounting.reconciliation.confirmed_at'))
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('accounting.reconciliation.status'))
                    ->options(ReconciliationStatus::getOptions()),
            ])
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
                        Caching::setCache(CacheKey::GHN_ORDER_DETAIL, $orderDetail, (string)$record->id, 15);

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
                    ->extraModalFooterActions(fn ($record) => [
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
                                Caching::setCache(CacheKey::GHN_ORDER_DETAIL, $orderDetail, (string)$record->id, 15);

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
                            ->visible(fn ($get) => !empty($get('error'))),
                    ])
                    ->action(function ($record, array $data) {
                        $service = app(ReconciliationService::class);

                        $orderDetail = Caching::getCache(CacheKey::GHN_ORDER_DETAIL, (string)$record->id);

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
                    ->visible(fn ($record) => $record->status === ReconciliationStatus::PENDING->value)
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

