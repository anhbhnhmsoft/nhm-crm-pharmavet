<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\SaleLevel;
use Illuminate\Database\Eloquent\Model;

class SaleLevelRepository extends BaseRepository
{
    public function model(): Model
    {
        return new SaleLevel();
    }
}
