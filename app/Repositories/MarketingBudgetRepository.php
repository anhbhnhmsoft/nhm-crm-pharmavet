<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\MarketingBudget;
use Illuminate\Database\Eloquent\Model;

class MarketingBudgetRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new MarketingBudget();
    }
}
