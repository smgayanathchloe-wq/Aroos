<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reminder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_rsvp_statuses' => 'array',
        'target_pledge_statuses' => 'array',
        'audience_filter' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function logs(): HasMany { return $this->hasMany(ReminderLog::class); }
}
