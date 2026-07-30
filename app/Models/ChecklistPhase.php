<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistPhase extends Model
{
    protected $guarded = [];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function tasks(): HasMany { return $this->hasMany(ChecklistTask::class)->orderBy('sort_order'); }
}
