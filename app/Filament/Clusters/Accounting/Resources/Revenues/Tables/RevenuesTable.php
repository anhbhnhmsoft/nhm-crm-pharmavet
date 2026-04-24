<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues\Tables;

use App\Models\Revenue;
use App\Utils\DateRangeGuard;
use App\Utils\AccountingPeriodGuard;
use App\Utils\DateRangeGuard;
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
                        DatePicker::make('from')
                            ->label(__('accounting.reconciliation.from_date'))
                            ->live()
                            ->beforeOrEqual('until')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'before_or_equal' => __('validation.before_or_equal', [
                                    'attribute' => __('accounting.reconciliation.from_date'),
                                    'date' => __('accounting.reconciliation.to_date'),
                                ]),
                            ]),
                        DatePicker::make('until')
                            ->label(__('accounting.reconciliation.to_date'))
                            ->live()
                            ->afterOrEqual('from')
                            ->extraInputAttributes(['required' => false])
                            ->validationMessages([
                                'after_or_equal' => __('validation.after_or_equal', [
                                    'attribute' => __('accounting.reconciliation.to_date'),
                                    'date' => __('accounting.reconciliation.from_date'),
                                ]),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (DateRangeGuard::hasInvalidRange($data['from'] ?? null, $data['until'] ?? null)) {
                            DateRangeGuard::notifyInvalidRange(
                                __CLASS__ . ':revenue_date',
                                __('validation.after_or_equal', [
                                    'attribute' => __('accounting.reconciliation.to_date'),
                                    'date' => __('accounting.reconciliation.from_date'),
                                ]),
                            );

                            return $query;
                        }

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
                EditAction::make()
                    ->disabled(fn(Revenue $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'revenue_date'))
                    ->tooltip(fn(Revenue $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'revenue_date') ? __('accounting.accounting_period.period_closed') : null),
                DeleteAction::make()
                    ->disabled(fn(Revenue $record): bool => AccountingPeriodGuard::isClosedForRecord($record, 'revenue_date'))
                    ->tooltip(fn(Revenue $record): ?string => AccountingPeriodGuard::isClosedForRecord($record, 'revenue_date') ? __('accounting.accounting_period.period_closed') : null),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('revenue_date', 'desc');
    }
}
