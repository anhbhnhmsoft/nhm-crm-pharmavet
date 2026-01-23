<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\InventoryTicket;
use Illuminate\Database\Eloquent\Model;

class InventoryTicketRepository extends BaseRepository
{
    public function model() : Model
    {
        return new InventoryTicket();
    }
}
