<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders\Pages;

use App\Filament\Clusters\Warehouse\Resources\Orders\OrderResource;
use App\Utils\AccountingPeriodGuard;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->disabled($this->isRecordReadOnly());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn(): bool => ! $this->isRecordReadOnly())
                ->tooltip(fn(): ?string => $this->isRecordReadOnly() ? __('accounting.accounting_period.period_closed') : null),
            ForceDeleteAction::make()
                ->visible(fn(): bool => ! $this->isRecordReadOnly())
                ->tooltip(fn(): ?string => $this->isRecordReadOnly() ? __('accounting.accounting_period.period_closed') : null),
            RestoreAction::make()
                ->visible(fn(): bool => ! $this->isRecordReadOnly())
                ->tooltip(fn(): ?string => $this->isRecordReadOnly() ? __('accounting.accounting_period.period_closed') : null),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->disabled($this->isRecordReadOnly())
            ->tooltip($this->isRecordReadOnly() ? __('accounting.accounting_period.period_closed') : null);
    }

    protected function isRecordReadOnly(): bool
    {
        return AccountingPeriodGuard::isClosedForRecord($this->record, 'created_at');
    }
}
