<?php

namespace App\Repositories;

use App\Models\Combo;
use Illuminate\Database\Eloquent\Model;

class ComboRepository
{
    public function model(): Model
    {
        return new Combo();
    }
}
