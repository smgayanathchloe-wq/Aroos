<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;

/**
 * Transparent recommendation score. Every point comes with the reason shown
 * to the couple. Sponsorship and payments never add points.
 */
class MatchScorer
{
    public function __construct(private readonly AvailabilityService $availability)
    {
    }

    /**
     * @param  array{category_ids?: int[], region_id?: ?int, region_name?: ?string, budget?: ?float, event_date?: ?string}  $c
     * @return array{score: int, reasons: string[], available: ?bool}
     */
    public function score(ProviderProfile $p, array $c): array
    {
        $score = 0;
        $reasons = [];

        if (! empty($c['region_id'])) {
            $serves = $p->region_id === (int) $c['region_id']
                || $p->services->contains(fn ($s) => in_array((int) $c['region_id'], $s->coverage_regions ?? [], true));
            if ($serves) {
                $score += 30;
                $reasons[] = $p->region_id === (int) $c['region_id'] ? 'Based in '.($c['region_name'] ?? 'your region') : 'Serves '.($c['region_name'] ?? 'your region');
            }
        }

        if (! empty($c['budget'])) {
            if ($p->starting_price === null) {
                $score += 5;
                $reasons[] = 'Prices on request — ask for a quotation';
            } elseif ((float) $p->starting_price <= (float) $c['budget']) {
                $score += 25;
                $reasons[] = 'Starting price '.Money::format($p->starting_price).' is within your budget';
            } else {
                $score -= 10;
                $reasons[] = 'Starting price '.Money::format($p->starting_price).' is above your budget';
            }
        }

        $available = null;
        if (! empty($c['event_date'])) {
            $services = $p->services->filter(fn ($s) => empty($c['category_ids']) || in_array($s->category_id, $c['category_ids'], true));
            $available = $services->contains(fn ($s) => $this->availability->conflict($s, $c['event_date']) === null);
            $score += $available ? 20 : -40;
            $reasons[] = $available ? 'No booking or blocked date on your day yet' : 'Already booked or unavailable on your date';
        }

        if ($p->rating_count > 0) {
            $score += (int) round((float) $p->rating_avg * 4);
            $reasons[] = number_format((float) $p->rating_avg, 1).'★ from '.$p->rating_count.' review'.($p->rating_count === 1 ? '' : 's');
        }
        if ($p->has_portfolio) {
            $score += 5;
            $reasons[] = 'Has a portfolio to look through';
        }

        return ['score' => $score, 'reasons' => $reasons, 'available' => $available];
    }
}
