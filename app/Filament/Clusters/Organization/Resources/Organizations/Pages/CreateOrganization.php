<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations\Pages;

use App\Filament\Clusters\Organization\Resources\Organizations\OrganizationResource;
use App\Filament\Components\CommonAction;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        return [
            CommonAction::backAction(self::getResource()),
            $this->getCreateFormAction(),
        ];
    }
}
