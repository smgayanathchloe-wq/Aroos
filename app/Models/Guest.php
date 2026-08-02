<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Guest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'save_the_date_sent_at' => 'datetime',
        'invite_sent_at' => 'datetime',
        'rsvp_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'is_special_guest' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            if (! $guest->invite_token) {
                $guest->invite_token = Str::random(40);
            }
            if (! $guest->checkin_token) {
                $guest->checkin_token = Str::random(48);
            }
        });
    }

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function seatingTable(): BelongsTo { return $this->belongsTo(SeatingTable::class); }
    public function pledges(): HasMany { return $this->hasMany(Pledge::class); }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    /**
     * Get contribution status: pledged, partial, fulfilled, none
     */
    public function getContributionStatus(): string
    {
        if ($this->is_special_guest) {
            return 'special';
        }

        $pledged = $this->pledges()->sum('amount') ?? 0;
        if ($pledged === 0) {
            return 'none';
        }

        $contributed = $this->pledges()
            ->with('contributions')
            ->get()
            ->sum(fn($p) => $p->contributions->sum('amount')) ?? 0;

        if ($contributed === 0) {
            return 'pledged';
        }

        if ($contributed >= $pledged) {
            return 'fulfilled';
        }

        return 'partial';
    }
}
