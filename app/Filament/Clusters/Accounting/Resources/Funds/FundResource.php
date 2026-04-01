<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\AccountingCluster;
use App\Filament\Clusters\Accounting\Resources\Funds\Pages\EditFund;
use App\Filament\Clusters\Accounting\Resources\Funds\Pages\ListFunds;
use App\Filament\Clusters\Accounting\Resources\Funds\Pages\ViewFund;
use App\Filament\Clusters\Accounting\Resources\Funds\RelationManagers\FundLockAuditsRelationManager;
use App\Filament\Clusters\Accounting\Resources\Funds\Schemas\FundForm;
use App\Filament\Clusters\Accounting\Resources\Funds\Tables\FundsTable;
use App\Filament\Clusters\Accounting\Resources\Funds\RelationManagers\FundTransactionsRelationManager;
use App\Models\Fund;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class FundResource extends Resource
{
    protected static ?string $model = Fund::class;

    protected static ?string $cluster = AccountingCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('accounting.fund.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.fund.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.fund.navigation_label');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if ($user->role === UserRole::SUPER_ADMIN->value) {
            return true;
        }

        return Helper::checkPermission([
            UserRole::ADMIN->value,
            UserRole::ACCOUNTING->value,
        ], $user->role) && ($user->organization?->is_foreign ?? false);
    }

    public static function form(Schema $schema): Schema
    {
        return FundForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FundsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FundTransactionsRelationManager::class,
            FundLockAuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFunds::route('/'),
            'view' => ViewFund::route('/{record}'),
            'edit' => EditFund::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = Auth::user();

        if (!$user) {
            return $query->whereKey(-1); // không có user => không trả dữ liệu
        }

        if ($user->role === UserRole::SUPER_ADMIN->value) {
            return $query;
        }

        return $query->where('organization_id', $user->organization_id);
    }
}
