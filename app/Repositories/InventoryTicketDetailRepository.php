<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\InventoryTicketDetail;
use Illuminate\Database\Eloquent\Model;

class InventoryTicketDetailRepository extends BaseRepository
{
    public function model() : Model
    {
        return new InventoryTicketDetail();
    }
}
