<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collaborator extends Model
{
    protected $guarded = [];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
