<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ShippingConfigForWarehouse;
use Illuminate\Database\Eloquent\Model;

class ShippingConfigForWareHouseRepository extends BaseRepository
{
    public function model(): Model
    {
        return new ShippingConfigForWarehouse();
    }
}
