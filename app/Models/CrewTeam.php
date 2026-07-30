<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrewTeam extends Model
{
    protected $guarded = [];

    protected $casts = ['responsibilities' => 'array'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function members(): HasMany { return $this->hasMany(CrewMember::class); }
}
