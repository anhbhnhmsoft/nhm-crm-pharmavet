<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Common\Constants\Order\OrderStatus;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class RecentOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 10;
    protected int | string | array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('dashboard.recent_orders.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->where('organization_id', Auth::user()->organization_id)
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('dashboard.recent_orders.time'))
                    ->dateTime('H:i d/m')
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label(__('dashboard.recent_orders.code'))
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label(__('dashboard.recent_orders.customer')),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('dashboard.recent_orders.amount'))
                    ->money('VND'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('dashboard.recent_orders.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OrderStatus::PENDING->value => 'gray',
                        OrderStatus::CONFIRMED->value => 'warning',
                        OrderStatus::SHIPPING->value => 'info',
                        OrderStatus::COMPLETED->value => 'success',
                        OrderStatus::CANCELLED->value => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => OrderStatus::from($state)->label()),
            ])
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->label(__('dashboard.recent_orders.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn (Order $record): string => "/admin/sales/orders/{$record->id}")
            ]);
    }
}
