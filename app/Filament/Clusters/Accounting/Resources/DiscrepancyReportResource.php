<?php

namespace App\Filament\Clusters\Accounting\Resources;

use App\Common\Constants\User\UserPosition;
use App\Common\Constants\User\UserRole;
use App\Common\Constants\Warehouse\StatusTicket;
use App\Common\Constants\Warehouse\TypeTicket;
use App\Filament\Clusters\Accounting\Resources\DiscrepancyReportResource\Pages\ListDiscrepancyReports;
use App\Models\InventoryTicket;
use App\Models\Order;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DiscrepancyReportResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.report.discrepancy');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.report.discrepancy');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        
        if ($user->hasRole(UserRole::SUPER_ADMIN) || $user->hasRole(UserRole::ADMIN)) {
            return true;
        }

        // Kế toán cần kèm theo vị trí LEADER
        return $user->hasRole(UserRole::ACCOUNTING) && $user->hasPosition(UserPosition::LEADER);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('organization_id', Auth::user()->organization_id))
            ->columns([
                TextColumn::make('code')
                    ->label(__('order.table.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('order.table.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('createdBy.name')
                    ->label(__('order.table.created_by'))
                    ->sortable(),

                TextColumn::make('debt_age')
                    ->label(__('accounting.report.debt_age'))
                    ->getStateUsing(fn (Order $record) => (int) now()->diffInDays($record->created_at, true))
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label(__('accounting.report.discrepancy_system'))
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('warehouse_value')
                    ->label(__('accounting.report.discrepancy_warehouse'))
                    ->getStateUsing(function (Order $record) {
                        $orderItems = $record->items->keyBy('product_id');

                        $tickets = InventoryTicket::where('order_id', $record->id)
                            ->where('status', (int) StatusTicket::COMPLETED->value)
                            ->where('type', (int) TypeTicket::EXPORT->value)
                            ->with('details')
                            ->get();
                        
                        $value = 0;
                        foreach ($tickets as $ticket) {
                            foreach ($ticket->details as $detail) {
                                $orderItem = $orderItems->get($detail->product_id);
                                $price = $orderItem ? (float) $orderItem->price : 0;
                                $value += ($detail->quantity * $price);
                            }
                        }
                        return $value;
                    })
                    ->money('VND')
                    ->color(fn ($state, Order $record) => (float)$state != (float)$record->total_amount ? 'danger' : 'success')
                    ->weight(fn ($state, Order $record) => (float)$state != (float)$record->total_amount ? 'bold' : 'normal'),

                TextColumn::make('actual_payment')
                    ->label(__('accounting.report.discrepancy_actual'))
                    ->getStateUsing(function (Order $record) {
                        $reconciliationTotal = 0;
                        foreach ($record->reconciliation as $recon) {
                            $reconciliationTotal += (float) $recon->cod_amount;
                        }
                        return $reconciliationTotal + (float) $record->amount_recived_from_customer;
                    })
                    ->money('VND')
                    ->color(function ($state, Order $record) {
                        $orderItems = $record->items->keyBy('product_id');

                        $tickets = InventoryTicket::where('order_id', $record->id)
                            ->where('status', (int) StatusTicket::COMPLETED->value)
                            ->where('type', (int) TypeTicket::EXPORT->value)
                            ->with('details')
                            ->get();
                        
                        $warehouseValue = 0;
                        foreach ($tickets as $ticket) {
                            foreach ($ticket->details as $detail) {
                                $orderItem = $orderItems->get($detail->product_id);
                                $price = $orderItem ? (float) $orderItem->price : 0;
                                $warehouseValue += ($detail->quantity * $price);
                            }
                        }
                        return (float)$state != (float)$warehouseValue ? 'danger' : 'success';
                    })
                    ->weight('bold'),
                
                TextColumn::make('discrepancy_note')
                    ->label(__('accounting.report.note'))
                    ->getStateUsing(function (Order $record) {
                        $system = (float) $record->total_amount;
                        $orderItems = $record->items->keyBy('product_id');

                        $tickets = InventoryTicket::where('order_id', $record->id)
                            ->where('status', (int) StatusTicket::COMPLETED->value)
                            ->where('type', (int) TypeTicket::EXPORT->value)
                            ->with('details')
                            ->get();
                        
                        $warehouse = 0;
                        foreach ($tickets as $ticket) {
                            foreach ($ticket->details as $detail) {
                                $orderItem = $orderItems->get($detail->product_id);
                                $price = $orderItem ? (float) $orderItem->price : 0;
                                $warehouse += ($detail->quantity * $price);
                            }
                        }

                        $reconciliationTotal = 0;
                        foreach ($record->reconciliation as $recon) {
                            $reconciliationTotal += (float) $recon->cod_amount;
                        }
                        $payment = $reconciliationTotal + (float) $record->amount_recived_from_customer;

                        if (abs($system - $warehouse) > 0.1) return __('accounting.report.discrepancy_system_warehouse_diff');
                        if (abs($warehouse - $payment) > 0.1) return __('accounting.report.discrepancy_warehouse_payment_diff');
                        return __('accounting.report.discrepancy_matched');
                    })
                    ->color(fn($state) => str_contains($state, 'Khớp') ? 'success' : 'danger')
                    ->weight('bold'),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('date')
                    ->form([
                        DatePicker::make('from')->label(__('common.from_date')),
                        DatePicker::make('to')->label(__('common.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['to'], fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    })
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDiscrepancyReports::route('/'),
        ];
    }
}
