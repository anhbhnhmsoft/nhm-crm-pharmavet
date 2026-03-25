<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\TeamReportScope; 
use Illuminate\Database\Eloquent\Model;

class TeamReportScopeRepository extends BaseRepository
{
    public function model(): Model
    {
        return new TeamReportScope();
    }
}
