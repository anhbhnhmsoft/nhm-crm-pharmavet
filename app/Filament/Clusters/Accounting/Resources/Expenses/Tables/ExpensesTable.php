<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses\Tables;

use App\Common\Constants\Accounting\ExpenseCategory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('expense_date')
                    ->label(__('accounting.expense.expense_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('category')
                    ->label(__('accounting.expense.category'))
                    ->formatStateUsing(fn($state) => ExpenseCategory::tryFrom($state)?->getLabel())
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        ExpenseCategory::SALES->value => 'success',
                        ExpenseCategory::MARKETING->value => 'warning',
                        ExpenseCategory::RECONCILIATION->value => 'info',
                        ExpenseCategory::SHIPPING_AUTO->value => 'primary',
                        ExpenseCategory::MANAGEMENT->value => 'danger',
                        ExpenseCategory::OFFICE->value => 'gray',
                        ExpenseCategory::SPENDING->value => 'warning',
                        ExpenseCategory::OTHER->value => 'gray',
                        ExpenseCategory::COST_OF_GOODS->value => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('accounting.expense.description'))
                    ->searchable()
                    ->limit(50),

                TextColumn::make('amount')
                    ->label(__('accounting.expense.amount'))
                    ->money('VND')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()->money('VND')),

                TextColumn::make('createdBy.name')
                    ->label(__('accounting.expense.created_by'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('accounting.exchange_rate.created_at'))
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('accounting.expense.category'))
                    ->options(ExpenseCategory::getOptions()),

                Filter::make('expense_date')
                    ->form([
                        DatePicker::make('from')->label(__('accounting.reconciliation.from_date')),
                        DatePicker::make('until')->label(__('accounting.reconciliation.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('expense_date', '<=', $date),
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
            ->defaultSort('expense_date', 'desc');
    }
}
