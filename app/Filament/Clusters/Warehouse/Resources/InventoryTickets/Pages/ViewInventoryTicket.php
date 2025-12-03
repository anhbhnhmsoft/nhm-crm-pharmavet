<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewInventoryTicket extends ViewRecord
{
    protected static string $resource = InventoryTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('warehouse.ticket.action.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('warehouse.ticket.action.approve'))
                ->modalDescription(__('warehouse.ticket.action.approve_description'))
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value)
                ->action(function () {
                    $this->record->update([
                        'status' => StatusTicket::COMPLETED->value,
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title(__('warehouse.ticket.action.approve'))
                        ->body(__('warehouse.ticket.action.approve_description'))
                        ->send();

                    return redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            Action::make('cancel')
                ->label(__('warehouse.ticket.action.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('warehouse.ticket.action.cancel'))
                ->modalDescription(__('warehouse.ticket.action.cancel_description'))
                ->visible(fn() => $this->record->status === StatusTicket::COMPLETED->value)
                ->action(function () {
                    $this->record->update([
                        'status' => StatusTicket::CANCELLED->value,
                    ]);

                    Notification::make()
                        ->success()
                        ->title(__('warehouse.ticket.action.cancel'))
                        ->body(__('warehouse.ticket.action.cancel_description'))
                        ->send();

                    return redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                }),

            EditAction::make()
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value),

            DeleteAction::make()
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value)
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }
}
