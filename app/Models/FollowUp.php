<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUp extends Model
{
    protected $guarded = [];

    protected $casts = ['due_at' => 'datetime'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
}
