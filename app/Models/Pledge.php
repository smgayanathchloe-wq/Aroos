<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pledge row is also the outreach contact. Amount 0 means the person has
 * not pledged yet (status `none`).
 */
class Pledge extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'outreach_consent' => 'boolean',
        'last_contacted_at' => 'datetime',
    ];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function contributions(): HasMany { return $this->hasMany(Contribution::class); }
    public function promises(): HasMany { return $this->hasMany(PledgePromise::class); }
    public function followUps(): HasMany { return $this->hasMany(FollowUp::class); }
    public function conversations(): HasMany { return $this->hasMany(Conversation::class); }

    public function totalContributed(): float
    {
        return (float) $this->contributions->sum('amount');
    }

    public function balance(): float
    {
        return (float) $this->amount - $this->totalContributed();
    }

    public static function statusFor(float $amount, float $paid): string
    {
        return match (true) {
            $amount <= 0 && $paid <= 0 => 'none',
            $paid <= 0 => 'pledged',
            $paid + 0.001 >= $amount => 'fulfilled',
            default => 'partial',
        };
    }

    public function refreshStatus(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }
        // Query the ledger directly: the loaded relation may predate a new payment.
        $this->unsetRelation('contributions');
        $this->status = self::statusFor((float) $this->amount, (float) $this->contributions()->sum('amount'));
        $this->save();
    }

    /** Outreach view of the status: none | pledged | partial | paid. */
    public function outreachState(): string
    {
        $status = self::statusFor((float) $this->amount, $this->totalContributed());

        return $status === 'fulfilled' ? 'paid' : $status;
    }
}
