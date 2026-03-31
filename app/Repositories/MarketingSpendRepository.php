<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\MarketingSpend;
use Illuminate\Database\Eloquent\Model;

class MarketingSpendRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new MarketingSpend();
    }
}
