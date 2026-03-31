<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\MarketingScoringRuleSet;
use Illuminate\Database\Eloquent\Model;

class MarketingScoringRuleSetRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new MarketingScoringRuleSet();
    }
}
