<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ProductWarehouse;
use Illuminate\Database\Eloquent\Model;

class ProductWarehouseRepository extends BaseRepository
{
    public function model() : Model
    {
        return new ProductWarehouse();
    }
}   