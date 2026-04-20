<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Exports\SimpleArrayExport;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use App\Services\Warehouse\InventoryTicketExcelService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class CreateInventoryTicket extends CreateRecord
{
    protected static string $resource = InventoryTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_detail_template')
                ->label(__('warehouse.ticket.excel.download_template'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (InventoryTicketExcelService $service) {
                    return Excel::download(
                        new SimpleArrayExport($service->templateHeadings(), $service->templateRows()),
                        'inventory-ticket-lines-template.xlsx'
                    );
                }),
            Action::make('export_detail_lines')
                ->label(__('warehouse.ticket.excel.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (InventoryTicketExcelService $service) {
                    $state = $this->form->getState();
                    $details = $state['details'] ?? [];

                    if ($details === []) {
                        Notification::make()
                            ->warning()
                            ->title(__('warehouse.ticket.excel.errors.no_details_to_export'))
                            ->send();

                        return null;
                    }

                    return Excel::download(
                        new SimpleArrayExport($service->exportHeadings(), $service->buildExportRows($details)),
                        'inventory-ticket-lines-' . now()->format('YmdHis') . '.xlsx'
                    );
                }),
            Action::make('import_detail_lines')
                ->label(__('warehouse.ticket.excel.import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->form([
                    FileUpload::make('file')
                        ->label(__('warehouse.ticket.excel.file'))
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ]),
                ])
                ->action(function (array $data, InventoryTicketExcelService $service) {
                    try {
                        $state = $this->form->getState();
                        $state['details'] = $service->importRows(
                            $data['file'],
                            $state,
                            (int) Auth::user()->organization_id,
                        );

                        $this->form->fill($state);

                        Notification::make()
                            ->success()
                            ->title(__('warehouse.ticket.excel.import_success'))
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title(__('warehouse.ticket.excel.import_failed'))
                            ->body($exception->getMessage())
                            ->send();
                    }
                }),
        ];
    }

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

        $record->logs()->create([
            'action' => 'create',
            'note' => $record->note,
            'new_status' => $record->status,
            'metadata_json' => [
                'type' => $record->type,
                'details_count' => count($details),
            ],
            'user_id' => Auth::id(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
