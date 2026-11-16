<?php

namespace App\Marketplace;

use App\Models\Marketplace\ServiceCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Price ranges per category from real marketplace data: active package prices
 * of listed providers and amounts of accepted quotations. Shown only with
 * enough samples, and always labelled as an estimate.
 */
class BudgetEstimator
{
    /** @return Collection<int, array{category: ServiceCategory, samples: int, low: ?float, median: ?float, high: ?float}> */
    public function ranges(array $categoryIds = []): Collection
    {
        $categories = ServiceCategory::active()->topLevel()->when($categoryIds, fn ($q) => $q->whereIn('id', $categoryIds))->orderBy('sort')->get();

        $packagePrices = DB::table('service_packages')
            ->join('provider_services', 'provider_services.id', '=', 'service_packages.service_id')
            ->join('provider_profiles', 'provider_profiles.id', '=', 'provider_services.provider_id')
            ->where('service_packages.status', 'active')->whereNull('service_packages.deleted_at')->whereNotNull('service_packages.price')
            ->where('provider_services.status', 'published')->whereNull('provider_services.deleted_at')
            ->where('provider_profiles.status', 'active')->where('provider_profiles.verification_status', 'verified')
            ->get(['provider_services.category_id', 'service_packages.price as amount']);

        $accepted = DB::table('quotation_versions')
            ->join('service_enquiries', 'service_enquiries.id', '=', 'quotation_versions.enquiry_id')
            ->join('provider_services', 'provider_services.id', '=', 'service_enquiries.service_id')
            ->where('quotation_versions.status', 'accepted')
            ->get(['provider_services.category_id', 'quotation_versions.amount']);

        $all = $packagePrices->concat($accepted)->groupBy('category_id');
        $min = (int) config('marketplace.budget_min_samples', 3);

        return $categories->map(function (ServiceCategory $c) use ($all, $min) {
            $childIds = $c->children()->pluck('id')->push($c->id);
            $values = $childIds->flatMap(fn ($id) => $all->get($id, collect())->pluck('amount'))->map(fn ($v) => (float) $v)->sort()->values();
            $enough = $values->count() >= $min;

            return [
                'category' => $c,
                'samples' => $values->count(),
                'low' => $enough ? $values->first() : null,
                'median' => $enough ? $values->median() : null,
                'high' => $enough ? $values->last() : null,
            ];
        });
    }
}
