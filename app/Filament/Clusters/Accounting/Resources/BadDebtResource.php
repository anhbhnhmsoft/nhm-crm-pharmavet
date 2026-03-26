<?php

namespace App\Filament\Clusters\Accounting\Resources;

use App\Filament\Clusters\Accounting\Resources\BadDebtResource\Pages\ListBadDebts;
use App\Filament\Clusters\Accounting\Resources\BadDebtResource\Tables\BadDebtsTable;
use App\Models\Order;
use App\Common\Constants\User\UserRole;
use App\Common\Constants\User\UserPosition;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Common\Constants\GateKey;

class BadDebtResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'bad-debts';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.bad_debt.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.bad_debt.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.bad_debt.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_CHIEF_ACCOUNTANT->value);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', $user->organization_id);
        }

        // Mặc định chỉ lọc nợ chưa thu hết
        return $query->whereRaw('(collect_amount - amount_recived_from_customer) > 0');
    }

    public static function table(Table $table): Table
    {
        return BadDebtsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBadDebts::route('/'),
        ];
    }
}
