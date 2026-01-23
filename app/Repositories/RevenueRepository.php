<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Revenue;
use Illuminate\Database\Eloquent\Model;

class RevenueRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Revenue();
    }
}

