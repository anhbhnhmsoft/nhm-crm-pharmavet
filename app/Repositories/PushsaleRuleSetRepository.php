<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\PushsaleRuleSet;
use Illuminate\Database\Eloquent\Model;

class PushsaleRuleSetRepository extends BaseRepository
{
    public function model(): Model
    {
        return new PushsaleRuleSet();
    }
}
