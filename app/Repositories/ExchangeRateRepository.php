<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Model;

class ExchangeRateRepository extends BaseRepository
{
    public function model(): Model
    {
        return new ExchangeRate();
    }
}

