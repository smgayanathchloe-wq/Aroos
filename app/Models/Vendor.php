<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    protected $guarded = [];

    protected $casts = ['price_estimate' => 'decimal:2', 'amount_paid' => 'decimal:2', 'deposit_paid' => 'boolean'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
}
