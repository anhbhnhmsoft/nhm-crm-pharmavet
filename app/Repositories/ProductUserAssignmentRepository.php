<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ProductUserAssignment;
use Illuminate\Database\Eloquent\Model;

class ProductUserAssignmentRepository extends BaseRepository
{
    public function model(): Model
    {
        return new ProductUserAssignment();
    }
}
