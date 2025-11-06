<?php

namespace App\Repositories;

use App\Models\ProductUserAssignment;
use Illuminate\Database\Eloquent\Model;

class ProductUserAssignmentRepository extends ProductUserAssignmentRepository
{
    public function model(): Model
    {
        return new ProductUserAssignment();
    }
}
