<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class RevenuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('revenue_date')
                    ->label(__('accounting.revenue.revenue_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('accounting.revenue.description'))
                    ->searchable()
                    ->limit(50),

                TextColumn::make('amount')
                    ->label(__('accounting.revenue.amount'))
                    ->money('VND')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->money('VND')),

                TextColumn::make('createdBy.name')
                    ->label(__('accounting.revenue.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('accounting.exchange_rate.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('revenue_date')
                    ->form([
                        DatePicker::make('from')->label(__('accounting.reconciliation.from_date')),
                        DatePicker::make('until')->label(__('accounting.reconciliation.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('revenue_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('revenue_date', '<=', $date),
                            );
                    })
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('revenue_date', 'desc');
    }
}
