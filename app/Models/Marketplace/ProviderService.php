<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderService extends Model
{
    use SoftDeletes;

    public const PRICING = ['fixed' => 'Fixed price', 'packages' => 'Packages', 'negotiable' => 'Negotiable', 'quote_only' => 'Request a quotation'];

    protected $guarded = [];

    protected $casts = [
        'coverage_regions' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'is_negotiable' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id'); }
    public function category(): BelongsTo { return $this->belongsTo(ServiceCategory::class); }
    public function packages(): HasMany { return $this->hasMany(ServicePackage::class, 'service_id')->orderBy('sort')->orderBy('id'); }
    public function activePackages(): HasMany { return $this->packages()->where('status', 'active'); }
    public function media(): HasMany { return $this->hasMany(ProviderMedia::class, 'service_id'); }

    public function scopePublished(Builder $q): Builder { return $q->where('status', 'published'); }

    /** Lowest published price, or null when the provider publishes none. */
    public function startingPrice(): ?float
    {
        $prices = $this->activePackages->pluck('price')->filter()->map(fn ($p) => (float) $p);
        if ($this->base_price !== null && $this->pricing_type !== 'quote_only') {
            $prices->push((float) $this->base_price);
        }

        return $prices->isEmpty() ? null : $prices->min();
    }
}
