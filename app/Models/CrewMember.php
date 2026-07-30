<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewMember extends Model
{
    protected $guarded = [];

    public function team(): BelongsTo { return $this->belongsTo(CrewTeam::class, 'crew_team_id'); }
}
