<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\OrderStatusLog;
use Illuminate\Database\Eloquent\Model;

class OrderStatusLogRepository extends BaseRepository
{
    public function model(): Model
    {
        return new OrderStatusLog();
    }
}