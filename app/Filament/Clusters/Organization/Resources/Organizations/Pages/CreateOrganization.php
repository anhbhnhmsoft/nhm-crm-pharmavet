<?php

namespace App\Filament\Clusters\Organization\Resources\Organizations\Pages;

use App\Filament\Clusters\Organization\Resources\Organizations\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;
}
