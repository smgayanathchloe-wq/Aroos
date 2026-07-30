<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pledge extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'due_date' => 'date'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function guest(): BelongsTo { return $this->belongsTo(Guest::class); }
    public function contributions(): HasMany { return $this->hasMany(Contribution::class); }

    public function totalContributed(): float
    {
        return (float) $this->contributions->sum('amount');
    }

    public function balance(): float
    {
        return (float) $this->amount - $this->totalContributed();
    }

    public function refreshStatus(): void
    {
        $paid = $this->totalContributed();
        if ($paid <= 0) {
            $this->status = 'pledged';
        } elseif ($paid >= (float) $this->amount) {
            $this->status = 'fulfilled';
        } else {
            $this->status = 'partial';
        }
        $this->save();
    }
}
