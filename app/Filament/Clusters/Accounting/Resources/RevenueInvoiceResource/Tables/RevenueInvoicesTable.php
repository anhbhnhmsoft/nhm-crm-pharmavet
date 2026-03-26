<?php

namespace App\Filament\Clusters\Accounting\Resources\RevenueInvoiceResource\Tables;

use App\Common\Constants\Order\InvoiceStatus;
use App\Common\Constants\Order\OrderStatus;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RevenueInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('order.table.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('customer.username')
                    ->label(__('order.table.customer'))
                    ->searchable(),

                TextColumn::make('total_amount')
                    ->label(__('order.table.total_amount'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('order.table.status'))
                    ->badge()
                    ->color(fn(int $state): string => OrderStatus::color($state))
                    ->formatStateUsing(fn(int $state): string => OrderStatus::getLabel($state))
                    ->sortable(),

                TextColumn::make('invoice_code')
                    ->label(__('order.invoice_code'))
                    ->searchable()
                    ->url(fn(Order $record): ?string => $record->invoice_url)
                    ->openUrlInNewTab()
                    ->icon(fn(Order $record): ?string => $record->invoice_url ? 'heroicon-o-arrow-top-right-on-square' : null)
                    ->placeholder(__('order.invoice_status_options.unissued')),

                TextColumn::make('invoice_status')
                    ->label(__('order.invoice_status'))
                    ->badge()
                    ->color(fn(int $state): string => InvoiceStatus::tryFrom($state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn(int $state): string => InvoiceStatus::tryFrom($state)?->getLabel() ?? '')
                    ->sortable(),

                TextColumn::make('invoice_at')
                    ->label(__('order.invoice_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('order.filter.status'))
                    ->options(OrderStatus::toOptions())
                    ->multiple(),

                SelectFilter::make('invoice_status')
                    ->label(__('order.invoice_status'))
                    ->options(InvoiceStatus::toArray())
                    ->multiple(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('update_invoice')
                        ->label(__('order.invoice_action.update_invoice'))
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->visible(fn(Order $record) => $record->status == OrderStatus::COMPLETED->value)
                        ->form([
                            Select::make('invoice_status')
                                ->label(__('order.invoice_status'))
                                ->options(InvoiceStatus::toArray())
                                ->required()
                                ->default(fn(Order $record) => $record->invoice_status),
                            TextInput::make('invoice_code')
                                ->label(__('order.invoice_code'))
                                ->default(fn(Order $record) => $record->invoice_code),
                            TextInput::make('invoice_url')
                                ->label(__('order.invoice_url'))
                                ->url()
                                ->default(fn(Order $record) => $record->invoice_url),
                            DateTimePicker::make('invoice_at')
                                ->label(__('order.invoice_at'))
                                ->default(fn(Order $record) => $record->invoice_at ?? now()),
                        ])
                        ->action(function (Order $record, array $data) {
                            $record->update($data);
                            Notification::make()
                                ->title(__('order.invoice_action.success'))
                                ->success()
                                ->send();
                        }),
                ])
            ], position: RecordActionsPosition::BeforeColumns)
            ->defaultSort('created_at', 'desc')
            ->striped();
    }
}
