<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\Combo;
use Illuminate\Database\Eloquent\Model;

class ComboRepository extends BaseRepository
{
    public function model(): Model
    {
        return new Combo();
    }
}
