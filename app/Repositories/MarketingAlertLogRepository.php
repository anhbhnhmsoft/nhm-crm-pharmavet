<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\MarketingAlertLog;
use Illuminate\Database\Eloquent\Model;

class MarketingAlertLogRepository extends BaseRepository
{
    protected function model(): Model
    {
        return new MarketingAlertLog();
    }
}
