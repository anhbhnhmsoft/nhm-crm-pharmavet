<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, GenerateIdSnowflake, SoftDeletes;

    protected $table = 'teams';
    protected $fillable = [
        "name",
        "organization_id",
        "code",
        "description",
        "type",
        "created_by",
        "updated_by",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'team_id', 'id');
    }
}
