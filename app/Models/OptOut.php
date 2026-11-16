<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Do-not-contact entry. Append-only: once a number opts out it is never
 * contacted again by this wedding, so rows cannot be edited or removed.
 */
class OptOut extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'outreach_opt_outs';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Opt-outs are immutable.'));
        static::deleting(fn () => throw new \LogicException('Opt-outs are immutable.'));
    }
}
