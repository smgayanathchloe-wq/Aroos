<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PledgePromise extends Model
{
    protected $table = 'promises';

    protected $guarded = [];

    protected $casts = ['promised_date' => 'date', 'amount' => 'decimal:2'];

    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}
