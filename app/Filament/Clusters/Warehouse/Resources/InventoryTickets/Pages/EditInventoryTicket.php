<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use App\Services\Warehouse\InventoryMovementService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                        ->title(__('warehouse.ticket.action.approved'))
                        ->send();

                    return redirect($this->getResource()::getUrl('index'));
                }),

            Action::make('cancel')
                ->label(__('warehouse.ticket.action.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
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
                ->visible(fn() => $this->record->status === StatusTicket::COMPLETED->value || $this->record->status === StatusTicket::DRAFT->value)
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
        if ($this->record->status !== StatusTicket::DRAFT->value) {
            return $this->record->toArray();
        }

        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ((int) $record->status !== StatusTicket::DRAFT->value) {
            return $record;
        }

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
                    'unit_price' => $detail['unit_price'] ?? 0,
                    'batch_no' => $detail['batch_no'] ?? null,
                    'expired_at' => $detail['expired_at'] ?? null,
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
