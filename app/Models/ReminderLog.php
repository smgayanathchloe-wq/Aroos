<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    protected $guarded = [];

    public function reminder(): BelongsTo { return $this->belongsTo(Reminder::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
}
