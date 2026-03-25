<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\SaleKpiTarget;
use Illuminate\Database\Eloquent\Model;

class SaleKpiTargetRepository extends BaseRepository
{
    public function model(): Model
    {
        return new SaleKpiTarget();
    }
}
