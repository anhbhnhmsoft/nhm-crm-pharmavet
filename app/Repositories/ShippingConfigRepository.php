<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ShippingConfig;
use Illuminate\Database\Eloquent\Model;

class ShippingConfigRepository extends BaseRepository
{
    public function model(): Model
    {
        return new ShippingConfig();
    }
}
