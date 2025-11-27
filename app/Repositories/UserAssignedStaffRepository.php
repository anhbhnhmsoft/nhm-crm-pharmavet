<?php

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Models\UserAssignedStaff;
use Illuminate\Database\Eloquent\Model;

class UserAssignedStaffRepository extends BaseRepository
{
    public function model(): Model
    {
        return new UserAssignedStaff();
    }
}
