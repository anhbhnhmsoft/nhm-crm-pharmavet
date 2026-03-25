<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\InventoryTicketLog;
use Illuminate\Database\Eloquent\Model;

class InventoryTicketLogRepository extends BaseRepository
{
    public function model(): Model
    {
        return new InventoryTicketLog();
    }
}
