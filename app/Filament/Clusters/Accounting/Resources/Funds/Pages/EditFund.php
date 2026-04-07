<?php

namespace App\Filament\Clusters\Accounting\Resources\Funds\Pages;

use App\Filament\Clusters\Accounting\Resources\Funds\FundResource;
use App\Services\FundService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFund extends EditRecord
{
    protected static string $resource = FundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn () => $this->record->hasTransactions())
                ->action(function (FundService $service) {
                    $result = $service->deleteFund($this->record);
                    if ($result->isError()) {
                        Notification::make()->title($result->getMessage())->danger()->send();
                        return;
                    }
                    Notification::make()->title(__('common.notification.success'))->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }
}
