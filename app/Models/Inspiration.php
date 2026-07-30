<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inspiration extends Model
{
    protected $guarded = [];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
}
