<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;

class TeamRepository extends BaseRepository {

    public function model() : Model {
        return new Team();
    }

}
