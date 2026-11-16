<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeQuery extends Model
{
    protected $guarded = [];

    protected $casts = ['fell_back' => 'boolean', 'resolved_at' => 'datetime'];

    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
}
