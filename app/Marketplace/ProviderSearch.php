<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderBlackout;
use App\Models\Marketplace\ProviderProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Marketplace discovery over listed (published + verified) providers only.
 * Every filter maps to stored data; nothing is ranked on data we do not have.
 */
class ProviderSearch
{
    public const SORTS = ['recommended' => 'Recommended', 'rating' => 'Highest rated', 'reviews' => 'Most reviewed', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'newest' => 'Recently added'];

    public function query(array $f): Builder
    {
        $q = ProviderProfile::query()->listed()->with(['primaryCategory', 'region'])
            ->withCount(['media as portfolio_count' => fn ($m) => $m->where('moderation', 'visible')]);

        if ($text = trim((string) ($f['q'] ?? ''))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $text).'%';
            $q->where(fn ($w) => $w->where('business_name', 'like', $like)->orWhere('description', 'like', $like)
                ->orWhereHas('services', fn ($s) => $s->published()->where('title', 'like', $like)));
        }
        if ($category = $f['category'] ?? null) {
            $q->whereHas('categories', fn ($c) => $c->where('service_categories.id', $category)->orWhere('service_categories.parent_id', $category));
        }
        if ($region = $f['region'] ?? null) {
            $q->where(fn ($w) => $w->where('region_id', $region)
                ->orWhereHas('services', fn ($s) => $s->published()->whereJsonContains('coverage_regions', (int) $region)));
        }
        if (is_numeric($f['min_price'] ?? null)) {
            $q->where('starting_price', '>=', (float) $f['min_price']);
        }
        if (is_numeric($f['max_price'] ?? null)) {
            $q->where('starting_price', '<=', (float) $f['max_price']);
        }
        if ($pricing = $f['pricing'] ?? null) {
            $q->whereHas('services', fn ($s) => $s->published()->where(fn ($w) => $pricing === 'negotiable'
                ? $w->where('pricing_type', 'negotiable')->orWhere('is_negotiable', true)
                : $w->where('pricing_type', $pricing)));
        }
        if (! empty($f['has_packages'])) {
            $q->where('has_packages', true);
        }
        if (! empty($f['has_portfolio'])) {
            $q->where('has_portfolio', true);
        }
        if (! empty($f['multi_service'])) {
            $q->where('services_count', '>', 1);
        }
        if ($minRating = $f['min_rating'] ?? null) {
            $q->where('rating_count', '>', 0)->where('rating_avg', '>=', (int) $minRating);
        }
        if ($date = $f['date'] ?? null) {
            // Exclude providers who blocked the whole date. Capacity is re-checked on booking.
            $q->whereNotIn('id', ProviderBlackout::whereNull('service_id')->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->select('provider_id'));
        }

        return match ($f['sort'] ?? 'recommended') {
            'price_asc' => $q->orderByRaw('starting_price is null')->orderBy('starting_price'),
            'price_desc' => $q->orderByRaw('starting_price is null')->orderByDesc('starting_price'),
            'newest' => $q->latest('id'),
            // Only providers with reviews rank by rating; the rest follow, unranked.
            'rating' => $q->orderByRaw('rating_count = 0')->orderByDesc('rating_avg')->orderByDesc('rating_count'),
            'reviews' => $q->orderByDesc('rating_count')->orderByDesc('rating_avg'),
            // Recommended = richest profiles first. Sponsored placement is shown separately and labelled.
            default => $q->orderByDesc('rating_avg')->orderByDesc('has_portfolio')->orderByDesc('has_packages')->orderByDesc('services_count')->latest('verified_at'),
        };
    }

    /** Up to three currently sponsored providers matching the same filters (labelled in the UI). */
    public function sponsored(array $filters): \Illuminate\Support\Collection
    {
        return $this->query($filters)->where('is_sponsored', true)->where('featured_until', '>', now())->reorder()->inRandomOrder()->limit(3)->get();
    }

    public function search(array $filters, int $perPage = 18): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }
}
