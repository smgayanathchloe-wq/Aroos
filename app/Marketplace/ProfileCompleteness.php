<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;

/** Onboarding checklist computed from the data itself; nothing is stored separately. */
class ProfileCompleteness
{
    /** @return array{items: array<string, bool>, percent: int} */
    public static function for(ProviderProfile $p): array
    {
        $items = [
            'Business information' => filled($p->business_name) && filled($p->description) && $p->primary_category_id !== null,
            'Contact details' => filled($p->phone) && $p->region_id !== null,
            'Logo or cover photo' => filled($p->logo_path) || filled($p->cover_path),
            'At least one published service' => $p->services()->published()->exists(),
            'Pricing or packages' => $p->starting_price !== null || $p->services()->published()->where('pricing_type', 'quote_only')->exists(),
            'Portfolio' => (bool) $p->has_portfolio,
            'Verification' => $p->verification_status === 'verified',
        ];

        return ['items' => $items, 'percent' => (int) round(100 * count(array_filter($items)) / count($items))];
    }
}
