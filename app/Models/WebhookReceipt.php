<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class WebhookReceipt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** Record an inbound event id; false when it was already processed. */
    public static function firstTime(string $provider, string $externalId): bool
    {
        try {
            static::create(['provider' => $provider, 'external_id' => $externalId]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
