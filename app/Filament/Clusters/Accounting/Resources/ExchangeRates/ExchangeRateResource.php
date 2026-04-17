<?php

namespace App\Filament\Clusters\Accounting\Resources\ExchangeRates;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\AccountingCluster;
use App\Filament\Clusters\Accounting\Resources\ExchangeRates\Pages\ListExchangeRates;
use App\Models\ExchangeRate;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static ?string $cluster = AccountingCluster::class;

    protected static ?string $slug = 'exchange-rates';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('accounting.exchange_rate.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.exchange_rate.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.exchange_rate.navigation_label');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::ACCOUNTING->value,
        ], $user->role);
    }

    public static function form(Schema $schema): Schema
    {
        return ExchangeRateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExchangeRateTable::configure($table);
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
            'index' => ListExchangeRates::route('/'),
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
