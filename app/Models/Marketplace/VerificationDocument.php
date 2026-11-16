<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stored on the private "local" disk; only served through an authorized controller. */
class VerificationDocument extends Model
{
    protected $guarded = [];

    public function verification(): BelongsTo { return $this->belongsTo(ProviderVerification::class, 'verification_id'); }
}
