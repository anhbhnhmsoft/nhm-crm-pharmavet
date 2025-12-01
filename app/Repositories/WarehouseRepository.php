<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;

class WarehouseRepository extends BaseRepository
{
    public function model() : Model
    {
        return new Warehouse();
    }
}
