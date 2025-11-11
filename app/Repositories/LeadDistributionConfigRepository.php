<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\LeadDistributionConfig;
use Illuminate\Database\Eloquent\Model;

class LeadDistributionConfigRepository extends BaseRepository
{

    public function model(): Model
    {
        return new LeadDistributionConfig();
    }
}
