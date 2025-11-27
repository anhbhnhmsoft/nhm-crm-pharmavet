<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

class OrderRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Order();
    }
}