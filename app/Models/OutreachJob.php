<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachJob extends Model
{
    protected $guarded = [];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
}
