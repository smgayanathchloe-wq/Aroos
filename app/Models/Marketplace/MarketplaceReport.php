<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceReport extends Model
{
    public const TYPES = ['provider' => ProviderProfile::class, 'review' => ProviderReview::class, 'media' => ProviderMedia::class];

    protected $guarded = [];

    protected $casts = ['handled_at' => 'datetime'];

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_user_id'); }

    public function subject(): ?Model
    {
        $class = self::TYPES[$this->reportable_type] ?? null;

        return $class ? $class::withoutGlobalScopes()->find($this->reportable_id) : null;
    }
}
