<?php

namespace App\Filament\Clusters\Organization\Resources\Users\Pages;

use App\Filament\Clusters\Organization\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export_excel')
                ->label(__('common.action.export_excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\UsersExport, 'users.xlsx');
                }),
        ];
    }
}
