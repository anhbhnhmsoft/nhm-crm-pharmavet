<?php

namespace App\Filament\Clusters\Accounting\Resources\BadDebtResource\Pages;

use App\Filament\Clusters\Accounting\Resources\BadDebtResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBadDebts extends ListRecords
{
    protected static string $resource = BadDebtResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('accounting.bad_debt.aging.all')),
            'aging_30' => Tab::make(__('accounting.bad_debt.aging.above_30'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '<=', now()->subDays(30))),
            'aging_60' => Tab::make(__('accounting.bad_debt.aging.above_60'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '<=', now()->subDays(60))),
            'aging_90' => Tab::make(__('accounting.bad_debt.aging.above_90'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('created_at', '<=', now()->subDays(90))),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
