<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'received_on' => 'date'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
}
