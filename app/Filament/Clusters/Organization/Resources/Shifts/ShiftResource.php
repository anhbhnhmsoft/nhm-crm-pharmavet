<?php

namespace App\Filament\Clusters\Organization\Resources\Shifts;

use App\Common\Constants\User\UserRole;
use App\Filament\Clusters\Organization\OrganizationCluster;
use App\Filament\Clusters\Organization\Resources\Shifts\Pages\CreateShift;
use App\Filament\Clusters\Organization\Resources\Shifts\Pages\EditShift;
use App\Filament\Clusters\Organization\Resources\Shifts\Pages\ListShifts;
use App\Filament\Clusters\Organization\Resources\Shifts\Schemas\ShiftForm;
use App\Filament\Clusters\Organization\Resources\Shifts\Tables\ShiftsTable;
use App\Models\Shift;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = OrganizationCluster::class;

    public static function getModelLabel(): string
    {
        return __('filament.shift.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.shift.label');
    }

    public static function form(Schema $schema): Schema
    {
        return ShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShiftsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->hasRole(UserRole::ADMIN) );
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();


        if ($user?->hasRole(UserRole::ADMIN)) {
            return $query->where('organization_id', $user->organization_id);
        }

        return $query->whereRaw('1 = 0');
    }
    public static function getPages(): array
    {
        return [
            'index' => ListShifts::route('/'),
            'create' => CreateShift::route('/create'),
            'edit' => EditShift::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->whereIn('organization_id', [Auth::user()->organization_id])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
