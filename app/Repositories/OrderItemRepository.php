<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;

class OrderItemRepository extends BaseRepository
{
    public function model(): Model
    {
        return new OrderItem();
    }
}   