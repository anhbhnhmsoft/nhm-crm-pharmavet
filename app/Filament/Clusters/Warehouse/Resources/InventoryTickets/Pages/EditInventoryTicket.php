<?php

namespace App\Filament\Clusters\Warehouse\Resources\InventoryTickets\Pages;

use App\Common\Constants\Warehouse\StatusTicket;
use App\Exports\SimpleArrayExport;
use App\Filament\Clusters\Warehouse\Resources\InventoryTickets\InventoryTicketResource;
use App\Services\Warehouse\InventoryMovementService;
use App\Utils\AccountingPeriodGuard;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Warehouse\InventoryTicketExcelService;

class EditInventoryTicket extends EditRecord
{
    protected static string $resource = InventoryTicketResource::class;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->disabled($this->isRecordReadOnly());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_detail_template')
                ->label(__('warehouse.ticket.excel.download_template'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value)
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
                    try {
                        $rawState = $this->form->getRawState();
                        $details = $service->resolveExportDetails(
                            $this->data['details'] ?? ($rawState['details'] ?? []),
                            $this->record?->details ?? [],
                        );

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
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title(__('warehouse.ticket.excel.export_failed'))
                            ->body($service->formatImportException($exception))
                            ->send();

                        return null;
                    }
                }),
            Action::make('import_detail_lines')
                ->label(__('warehouse.ticket.excel.import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value)
                ->modalDescription(__('warehouse.ticket.excel.import_description'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('warehouse.ticket.excel.file'))
                        ->helperText(__('warehouse.ticket.excel.import_file_helper'))
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ]),
                ])
                ->action(function (array $data, InventoryTicketExcelService $service) {
                    try {
                        if ((int) $this->record->status !== StatusTicket::DRAFT->value) {
                            Notification::make()
                                ->danger()
                                ->title(__('warehouse.ticket.excel.errors.import_completed_ticket'))
                                ->send();

                            return;
                        }

                        $state = $this->form->getRawState();
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
                            ->body($service->formatImportException($exception))
                            ->send();
                    }
                }),
            Action::make('approve')
                ->label(__('warehouse.ticket.action.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalSubmitAction(fn (Action $action) => $action->extraAttributes(['formnovalidate' => true]))
                ->form([
                    Select::make('reason_code')
                        ->label(__('warehouse.order.form.reason_code'))
                        ->options(__('warehouse.ticket.reason_codes'))
                        ->markAsRequired()
                        ->rule('required')
                        ->extraInputAttributes(['required' => false])
                        ->native(false)
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),
                    Textarea::make('reason_note')
                        ->label(__('warehouse.order.form.reason_note'))
                        ->markAsRequired()
                        ->rule('required')
                        ->extraInputAttributes(['required' => false])
                        ->rows(2)
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),
                ])
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value && ! $this->isRecordReadOnly())
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
                ->modalSubmitAction(fn (Action $action) => $action->extraAttributes(['formnovalidate' => true]))
                ->form([
                    Select::make('reason_code')
                        ->label(__('warehouse.order.form.reason_code'))
                        ->options(__('warehouse.ticket.reason_codes'))
                        ->markAsRequired()
                        ->rule('required')
                        ->extraInputAttributes(['required' => false])
                        ->native(false)
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),
                    Textarea::make('reason_note')
                        ->label(__('warehouse.order.form.reason_note'))
                        ->markAsRequired()
                        ->rule('required')
                        ->extraInputAttributes(['required' => false])
                        ->rows(2)
                        ->validationMessages([
                            'required' => __('common.error.required'),
                        ]),
                ])
                ->visible(fn() => ($this->record->status === StatusTicket::COMPLETED->value || $this->record->status === StatusTicket::DRAFT->value) && ! $this->isRecordReadOnly())
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
                ->visible(fn() => $this->record->status === StatusTicket::DRAFT->value && ! $this->isRecordReadOnly()),

            ForceDeleteAction::make()
                ->visible(fn() => ! $this->isRecordReadOnly()),

            RestoreAction::make()
                ->visible(fn() => ! $this->isRecordReadOnly()),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled($this->isRecordReadOnly())
            ->tooltip($this->isRecordReadOnly() ? __('accounting.accounting_period.period_closed') : null);
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

        $originalSnapshot = [
            'type' => $record->type,
            'status' => $record->status,
            'warehouse_id' => $record->warehouse_id,
            'source_warehouse_id' => $record->source_warehouse_id,
            'target_warehouse_id' => $record->target_warehouse_id,
            'note' => $record->note,
            'details_count' => $record->details()->count(),
        ];

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

        $record->loadMissing('details');

        foreach ($record->details as $detail) {
            $record->logs()->create([
                'product_id' => (int) $detail->product_id,
                'action' => 'update',
                'note' => $record->note,
                'old_status' => $originalSnapshot['status'],
                'new_status' => $record->status,
                'metadata_json' => [
                    'before' => $originalSnapshot,
                    'after' => [
                        'type' => $record->type,
                        'status' => $record->status,
                        'warehouse_id' => $record->warehouse_id,
                        'source_warehouse_id' => $record->source_warehouse_id,
                        'target_warehouse_id' => $record->target_warehouse_id,
                        'note' => $record->note,
                        'details_count' => $record->details->count(),
                    ],
                    'quantity' => (int) $detail->quantity,
                ],
                'user_id' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function isRecordReadOnly(): bool
    {
        return AccountingPeriodGuard::isClosedForRecord($this->record, ['approved_at', 'created_at']);
    }
}
