<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateInventoryTicket extends CreateRecord
{
    protected static string $resource = InventoryTicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['organization_id'] = Auth::user()->organization_id;
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Extract details data
        $details = $data['details'] ?? [];
        unset($data['details']);

        // Create the ticket
        $record = static::getModel()::create($data);

        // Create details
        if (!empty($details)) {
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
