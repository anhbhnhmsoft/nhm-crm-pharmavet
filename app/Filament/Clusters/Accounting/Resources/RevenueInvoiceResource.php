<?php

namespace App\Filament\Clusters\Accounting\Resources;

use App\Common\Constants\GateKey;
use App\Filament\Clusters\Accounting\Resources\RevenueInvoiceResource\Pages\ListRevenueInvoices;
use App\Filament\Clusters\Accounting\Resources\RevenueInvoiceResource\Tables\RevenueInvoicesTable;
use App\Models\Order;
use App\Common\Constants\User\UserRole;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use BackedEnum;

class RevenueInvoiceResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'revenue-invoices';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-dollar';

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_accounting');
    }

    public static function getModelLabel(): string
    {
        return __('accounting.revenue_invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('accounting.revenue_invoice.plural_label');
    }
    
    public static function getNavigationLabel(): string
    {
        return __('accounting.revenue_invoice.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(GateKey::IS_ACCOUNTING->value);
    }

    protected static ?string $recordTitleAttribute = 'code';

    public static function table(Table $table): Table
    {
        return RevenueInvoicesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRevenueInvoices::route('/'),
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
}
