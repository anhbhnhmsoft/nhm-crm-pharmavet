<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use App\Services\Warehouse\InventoryMovementService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                ->form([
                    Select::make('reason_code')
                        ->label(__('warehouse.order.form.reason_code'))
                        ->options(__('warehouse.ticket.reason_codes'))
                        ->required()
                        ->native(false),
                    Textarea::make('reason_note')
                        ->label(__('warehouse.order.form.reason_note'))
                        ->required()
                        ->rows(2),
                ])
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value)
                ->action(function (array $data) {
                    /** @var InventoryMovementService $inventoryMovementService */
                    $inventoryMovementService = app(InventoryMovementService::class);
                    $result = $inventoryMovementService->approveTicket(
                        ticket: $this->record,
                        actorId: (int) Auth::id(),
                        reasonCode: $data['reason_code'] ?? null,
                        reasonNote: $data['reason_note'] ?? null,
                    );

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title($result->getMessage())
                            ->send();

                        return;
                    }

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
                ->form([
                    Select::make('reason_code')
                        ->label(__('warehouse.order.form.reason_code'))
                        ->options(__('warehouse.ticket.reason_codes'))
                        ->required()
                        ->native(false),
                    Textarea::make('reason_note')
                        ->label(__('warehouse.order.form.reason_note'))
                        ->required()
                        ->rows(2),
                ])
                ->visible(fn() => $this->record->status === StatusTicket::COMPLETED->value)
                ->action(function (array $data) {
                    /** @var InventoryMovementService $inventoryMovementService */
                    $inventoryMovementService = app(InventoryMovementService::class);
                    $result = $inventoryMovementService->cancelTicket(
                        ticket: $this->record,
                        actorId: (int) Auth::id(),
                        reasonCode: (string) ($data['reason_code'] ?? ''),
                        reasonNote: (string) ($data['reason_note'] ?? ''),
                    );

                    if ($result->isError()) {
                        Notification::make()
                            ->danger()
                            ->title($result->getMessage())
                            ->send();

                        return;
                    }

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
