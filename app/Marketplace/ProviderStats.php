<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;

/** Keeps the denormalised listing fields in step with services, packages and media. */
class ProviderStats
{
    public static function refresh(ProviderProfile $provider): void
    {
        $services = $provider->services()->published()->with('activePackages')->get();
        $prices = $services->map->startingPrice()->filter(fn ($p) => $p !== null);

        $provider->forceFill([
            'services_count' => $services->count(),
            'starting_price' => $prices->isEmpty() ? null : $prices->min(),
            'has_packages' => $services->contains(fn ($s) => $s->activePackages->isNotEmpty()),
            'has_portfolio' => $provider->media()->where('moderation', 'visible')->exists(),
        ])->saveQuietly();
    }
}
