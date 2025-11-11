<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\LeadDistributionRule;
use Illuminate\Database\Eloquent\Model;

class LeadDistributionRuleRepository extends BaseRepository
{
    public function model(): Model
    {
        return new LeadDistributionRule();
    }
}
