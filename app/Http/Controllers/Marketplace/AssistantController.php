<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\BudgetEstimator;
use App\Marketplace\MarketplaceAssistant;
use App\Marketplace\MatchScorer;
use App\Marketplace\ProviderSearch;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\Marketplace\TzRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * "Plan my services": describe your needs (AI reads them when enabled, or fill
 * the form yourself), get providers from the database ranked by a transparent
 * score, real price ranges, and a drafted request to review before sending.
 */
class AssistantController extends Controller
{
    use ResolvesProvider;

    public function show(Request $request, MarketplaceAssistant $ai, MatchScorer $scorer, BudgetEstimator $budget, ProviderSearch $search)
    {
        $criteria = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'category_ids' => ['array'], 'category_ids.*' => ['integer'],
            'region_id' => ['nullable', 'integer'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'guests' => ['nullable', 'integer', 'min:1'],
        ]);
        $aiNote = null;
        $requestText = null;

        if (filled($criteria['description'] ?? null) && empty($criteria['category_ids'])) {
            if ($ai->enabled()) {
                try {
                    $parsed = $ai->interpret($criteria['description']);
                    $criteria = array_merge($criteria, array_filter($parsed, fn ($v) => $v !== null && $v !== [] && $v !== ''));
                    $requestText = $parsed['request_text'] ?: null;
                    $aiNote = $parsed['summary'] ?: null;
                } catch (\Throwable $e) {
                    Log::warning('Marketplace assistant failed', ['error' => $e->getMessage()]);
                    $aiNote = 'The assistant is unavailable right now — choose the services below instead.';
                }
            } else {
                $aiNote = 'Choose the services you need below.';
            }
        }

        $categoryIds = array_map('intval', $criteria['category_ids'] ?? []);
        $regions = TzRegion::orderBy('name')->get();
        $regionName = $regions->firstWhere('id', $criteria['region_id'] ?? null)?->name;
        $recommendations = collect();

        foreach ($categoryIds as $categoryId) {
            $providers = $search->query(['category' => $categoryId])->with('services')->limit(60)->get();
            $scored = $providers->map(fn (ProviderProfile $p) => ['provider' => $p] + $scorer->score($p, [
                'category_ids' => [$categoryId], 'region_id' => $criteria['region_id'] ?? null, 'region_name' => $regionName,
                'budget' => $criteria['budget'] ?? null, 'event_date' => $criteria['event_date'] ?? null,
            ]))->filter(fn ($r) => $r['available'] !== false)->sortByDesc('score')->take(4)->values();
            $recommendations[$categoryId] = $scored;
        }

        $requestText ??= filled($criteria['description'] ?? null) ? $criteria['description'] : null;

        return view('marketplace.assistant', [
            'criteria' => $criteria + ['category_ids' => $categoryIds],
            'categories' => ServiceCategory::active()->topLevel()->orderBy('sort')->get(),
            'regions' => $regions,
            'recommendations' => $recommendations,
            'budgets' => $categoryIds ? $budget->ranges($categoryIds) : collect(),
            'aiEnabled' => $ai->enabled(),
            'aiNote' => $aiNote,
            'prefill' => array_filter([
                'draft' => $requestText,
                'guests' => $criteria['guests'] ?? null,
                'budget' => $criteria['budget'] ?? null,
                'event_date' => $criteria['event_date'] ?? null,
                'region_id' => $criteria['region_id'] ?? null,
            ]),
        ]);
    }

    /** Provider writing help. Returns text for an editable field; never saves or sends anything. */
    public function draft(Request $request, MarketplaceAssistant $ai): JsonResponse
    {
        $provider = $this->provider($request, 'respond');
        if (! $ai->enabled()) {
            return response()->json(['message' => 'AI help is not enabled on this Aroos installation.'], 503);
        }
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_keys(MarketplaceAssistant::DRAFT_KINDS))],
            'language' => ['nullable', 'in:en,sw'],
            'enquiry_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $context = ['notes' => $data['notes'] ?? null];
        if ($data['enquiry_id'] ?? null) {
            $enquiry = ServiceEnquiry::with(['service', 'versions', 'messages'])->where('provider_id', $provider->id)->findOrFail($data['enquiry_id']);
            $context['request'] = [
                'service' => $enquiry->service?->title, 'event_date' => $enquiry->event_date->toDateString(), 'guests' => $enquiry->guests,
                'venue' => $enquiry->venue, 'budget' => $enquiry->budget, 'requirements' => $enquiry->requirements, 'couple_first_name' => strtok((string) $enquiry->contact_name, ' '),
                'offers' => $enquiry->versions->map(fn ($v) => ['by' => $v->author_side, 'amount' => (float) $v->amount, 'status' => $v->status])->all(),
                'messages' => $enquiry->messages->where('kind', '!=', 'event')->map(fn ($m) => ['by' => $m->side, 'text' => $m->body])->values()->all(),
            ];
        }
        $context['services'] = $provider->services()->published()->with('activePackages')->get()
            ->map(fn ($s) => ['title' => $s->title, 'packages' => $s->activePackages->map(fn ($p) => ['name' => $p->name, 'price' => $p->price, 'features' => $p->features])->all()])->all();

        try {
            return response()->json(['text' => $ai->draft($data['kind'], $provider, $context, $data['language'] ?? 'en')]);
        } catch (\Throwable $e) {
            Log::warning('Provider AI draft failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The assistant could not write a draft right now. Please try again or write it yourself.'], 503);
        }
    }
}
