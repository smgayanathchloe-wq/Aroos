<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimelineEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['public' => 'boolean'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
}
