<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations\Pages;

use App\Filament\Clusters\Organization\Resources\Organizations\OrganizationResource;
use App\Filament\Components\CommonAction;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),

            Action::make('toggle_disable')
                ->label(
                    fn($record) => $record->disable
                        ? __('organization.form.enable')
                        : __('organization.form.disable_action')
                )
                ->hidden(fn($livewire) => $livewire instanceof ViewRecord)
                ->icon(fn ($record) => $record->disable ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading(__('organization.form.confirm_change'))
                ->modalDescription(
                    fn($record) => $record->disable
                        ? __('organization.form.enable_warning')
                        : __('organization.form.disable_warning')
                )
                ->action(function ($record) {
                    $record->disable = !$record->disable;
                    $record->save();
                })
                ->color(fn($record) => $record->disable ? 'primary' : 'danger'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getSaveFormAction(),
        ];
    }
}
