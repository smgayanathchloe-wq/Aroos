<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderVerification extends Model
{
    protected $guarded = [];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id'); }
    public function documents(): HasMany { return $this->hasMany(VerificationDocument::class, 'verification_id'); }
}
