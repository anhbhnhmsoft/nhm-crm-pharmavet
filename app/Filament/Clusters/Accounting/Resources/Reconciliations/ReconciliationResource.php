<?php

namespace App\Filament\Clusters\Accounting\Resources\Reconciliations;

use App\Common\Constants\Accounting\ReconciliationStatus;
use App\Common\Constants\GateKey;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Pages\ListReconciliations;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Schemas\ReconciliationForm;
use App\Filament\Clusters\Accounting\Resources\Reconciliations\Tables\ReconciliationsTable;
use App\Models\Reconciliation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReconciliationResource extends Resource
{
    protected static ?string $model = Reconciliation::class;

    protected static string|BackedEnum|null $navigationIcon = '';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.reconciliation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.reconciliation.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('accounting.reconciliation.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_SUPER_ADMIN->name)
            || Gate::allows(GateKey::IS_ADMIN->name)
            || Gate::allows(GateKey::HAS_ROLE->name, UserRole::ACCOUNTING);
    }

    public static function form(Schema $schema): Schema
    {
        return ReconciliationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReconciliationsTable::configure($table);
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
            'index' => ListReconciliations::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $organizationId = Auth::user()->organization_id;
        return $query
            ->where('organization_id', $organizationId)
            ->with([
                'organization:id,is_foreign',
                'exchangeRate:id,from_currency,to_currency,rate,rate_date,source',
                'order' => fn ($query) => $query->select([
                    'id',
                    'organization_id',
                    'customer_id',
                    'warehouse_id',
                    'created_by',
                    'care_by_id',
                    'care_status',
                    'code',
                    'ghn_order_code',
                    'provider_shipping',
                    'shipping_method',
                    'care_updated_at',
                    'ghn_status',
                    'ghn_posted_at',
                    'created_at',
                    'total_amount',
                    'discount',
                    'shipping_fee',
                    'deposit',
                    'amount_recived_from_customer',
                    'amout_support_fee',
                    'shipping_address',
                    'note',
                    'is_printed',
                    'status',
                ]),
                'order.warehouse:id,name',
                'order.customer:id,username,phone,assigned_staff_id',
                'order.customer.assignedStaffPrimary:id,name',
                'order.items:id,order_id,product_id,quantity,price',
                'order.items.product:id,name',
                'order.createdBy:id,name,username',
                'order.careBy:id,name',
            ]);
    }
}
