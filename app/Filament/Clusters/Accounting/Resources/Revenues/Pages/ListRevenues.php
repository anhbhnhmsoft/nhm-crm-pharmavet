<?php

namespace App\Filament\Clusters\Accounting\Resources\Revenues\Pages;

use App\Filament\Clusters\Accounting\Resources\Revenues\RevenueResource;
use App\Filament\Clusters\Accounting\Widgets\RevenueSummaryWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListRevenues extends ListRecords
{
    protected static string $resource = RevenueResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RevenueSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['organization_id'] = Auth::user()->organization_id;
                    $data['created_by'] = Auth::id();
                    return $data;
                }),
        ];
    }
}
