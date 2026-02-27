<?php

namespace App\Filament\Clusters\Accounting\Resources\Expenses;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Clusters\Accounting\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Clusters\Accounting\Resources\Expenses\Tables\ExpensesTable;
use App\Models\Expense;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-minus-circle';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.expense.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.expense.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.expense.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::ACCOUNTING->value,
        ], Auth::user()->role);
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenses::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $organizationId = Auth::user()->organization_id;
        return $query->where('organization_id', $organizationId);
    }
}
