<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatingTable extends Model
{
    protected $guarded = [];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function guests(): HasMany { return $this->hasMany(Guest::class); }

    public function seatsUsed(): int
    {
        return (int) $this->guests->sum('party_count');
    }
}
