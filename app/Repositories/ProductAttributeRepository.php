<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\ProductAttribute;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeRepository extends BaseRepository
{
    public function model(): Model
    {
        return new ProductAttribute();
    }
}
