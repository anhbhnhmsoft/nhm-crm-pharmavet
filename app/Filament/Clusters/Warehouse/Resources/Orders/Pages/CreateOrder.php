<?php

namespace App\Filament\Clusters\Warehouse\Resources\Orders\Pages;

use App\Filament\Clusters\Warehouse\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
