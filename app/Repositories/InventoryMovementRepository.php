<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;

class InventoryMovementRepository extends BaseRepository
{
    public function model(): Model
    {
        return new InventoryMovement();
    }
}
