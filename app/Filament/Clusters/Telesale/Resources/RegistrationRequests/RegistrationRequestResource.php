<?php

namespace App\Filament\Clusters\Telesale\Resources\RegistrationRequests;

use App\Common\Constants\Customer\CustomerType;
use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Telesale\Resources\RegistrationRequests\Pages\EditRegistrationRequest;
use App\Filament\Clusters\Telesale\Resources\RegistrationRequests\Pages\ListRegistrationRequests;
use App\Filament\Clusters\Telesale\Resources\RegistrationRequests\Pages\ViewRegistrationRequest;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Schemas\RegistrationRequestForm;
use App\Filament\Clusters\Telesale\Resources\TelesaleOperations\Tables\TelesaleOperationsTable;
use App\Models\Customer;
use App\Utils\Helper;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RegistrationRequestResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'registration-requests';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): \UnitEnum|string|null
    {
        return __('filament.navigation.unit_telesale');
    }

    public static function getModelLabel(): string
    {
        return __('telesale.registration_request_title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('telesale.registration_request_navigation');
    }

    public static function getNavigationLabel(): string
    {
        return __('telesale.registration_request_navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(RegistrationRequestForm::getComponents());
    }

    public static function table(Table $table): Table
    {
        return TelesaleOperationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()->role !== UserRole::SUPER_ADMIN->value) {
            $query->where('organization_id', Auth::user()->organization_id);
        }

        return $query->where('customer_type', CustomerType::PARTNER_REQUEST->value);
    }

    public static function canAccess(): bool
    {
        return Helper::checkPermission([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::SALE->value,
        ], Auth::user()->role);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrationRequests::route('/'),
            'edit' => EditRegistrationRequest::route('/{record}/edit'),
            'view' => ViewRegistrationRequest::route('/{record}'),
        ];
    }
}
