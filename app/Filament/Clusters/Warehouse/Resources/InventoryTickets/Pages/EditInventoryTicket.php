<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditInventoryTicket extends EditRecord
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
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value)
                ->action(function () {
                    $this->record->update([
                        'status' => StatusTicket::COMPLETED->value,
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title(__('warehouse.ticket.action.approved'))
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('cancel')
                ->label(__('warehouse.ticket.action.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === StatusTicket::COMPLETED->value || $this->record->status === StatusTicket::DRAFT->value)
                ->action(function () {
                    $this->record->update([
                        'status' => StatusTicket::CANCELLED->value,
                    ]);

                    Notification::make()
                        ->success()
                        ->title(__('warehouse.ticket.action.cancelled'))
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),

            DeleteAction::make()
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value),

            ForceDeleteAction::make(),

            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract details data
        $details = $data['details'] ?? [];
        unset($data['details']);

        // Update the ticket
        $record->update($data);

        // Sync details
        if (isset($details)) {
            // Delete existing details
            $record->details()->delete();

            // Create new details
            foreach ($details as $detail) {
                $record->details()->create([
                    'product_id' => $detail['product_id'],
                    'quantity' => $detail['quantity'],
                    'current_quantity' => $detail['current_quantity'] ?? 0,
                ]);
            }
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
