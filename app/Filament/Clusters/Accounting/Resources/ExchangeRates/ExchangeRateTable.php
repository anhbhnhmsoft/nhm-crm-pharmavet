<?php

namespace App\Filament\Clusters\Accounting\Resources\ExchangeRates;

use App\Utils\DateRangeGuard;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExchangeRateTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rate_date')
                    ->label(__('accounting.exchange_rate.rate_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('from_currency')
                    ->label(__('accounting.exchange_rate.from_currency'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('to_currency')
                    ->label(__('accounting.exchange_rate.to_currency'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('rate')
                    ->label(__('accounting.exchange_rate.rate'))
                    ->sortable()
                    ->alignEnd()
                    ->formatStateUsing(function ($state, $record): string {
                        $formatted = rtrim(rtrim(number_format((float) $state, 6, '.', ','), '0'), '.');

                        return $formatted . ' ' . $record->to_currency;
                    }),

                TextColumn::make('source')
                    ->label(__('accounting.exchange_rate.source'))
                    ->badge()
                    ->color(fn(string $state): string => $state === 'api' ? 'info' : 'success')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'manual' => __('accounting.exchange_rate.source_manual'),
                        'api' => __('accounting.exchange_rate.source_api'),
                        default => $state,
                    }),

                TextColumn::make('createdBy.name')
                    ->label(__('accounting.exchange_rate.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('accounting.exchange_rate.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('rate_date')
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
                                __CLASS__ . ':rate_date',
                                __('validation.after_or_equal', [
                                    'attribute' => __('accounting.reconciliation.to_date'),
                                    'date' => __('accounting.reconciliation.from_date'),
                                ]),
                            );

                            return $query;
                        }

                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('rate_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('rate_date', '<=', $date),
                            );
                    }),

                SelectFilter::make('source')
                    ->label(__('accounting.exchange_rate.source'))
                    ->options([
                        'manual' => __('accounting.exchange_rate.source_manual'),
                        'api' => __('accounting.exchange_rate.source_api'),
                    ]),
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
            ->defaultSort('rate_date', 'desc');
    }
}
