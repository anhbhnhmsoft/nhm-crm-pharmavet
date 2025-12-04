<?php

namespace App\Models;

use App\Core\GenerateId\GenerateIdSnowflake;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, GenerateIdSnowflake, SoftDeletes;

    protected $table = 'teams';
    protected $fillable = [
        "name",
        "organization_id",
        "type",
        "code",
        "description",
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_team')->using(UserTeam::class)->withTimestamps();
    }
}
