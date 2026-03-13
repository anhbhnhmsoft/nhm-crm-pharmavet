<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders;

use App\Filament\Clusters\Warehouse\Resources\Orders\Pages\CreateOrder;
use App\Filament\Clusters\Warehouse\Resources\Orders\Pages\EditOrder;
use App\Filament\Clusters\Warehouse\Resources\Orders\Pages\ListOrders;
use App\Filament\Clusters\Warehouse\Resources\Orders\Schemas\OrderForm;
use App\Filament\Clusters\Warehouse\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Common\Constants\User\UserRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_warehouse');
    }

    public static function getModelLabel(): string
    {
        return __('warehouse.order.primary');
    }

    protected static ?string $recordTitleAttribute = 'Order';

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
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
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', $user->organization_id);
        }

        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
