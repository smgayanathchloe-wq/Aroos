<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Marketplace\MarketplaceException;
use App\Marketplace\NegotiationService;
use App\Marketplace\ProviderSearch;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\TzRegion;
use App\Models\Wedding;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Customer-side discovery: categories, search, provider profiles, favourites, sending requests. */
class MarketplaceController extends Controller
{
    use AuthorizesRequests;

    public function landing()
    {
        return view('marketplace.landing', [
            'categories' => ServiceCategory::active()->topLevel()->orderBy('sort')->get(),
        ]);
    }

    public function index(Request $request, ProviderSearch $search)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'region' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'pricing' => ['nullable', 'in:fixed,packages,negotiable,quote_only'],
            'has_packages' => ['nullable', 'boolean'],
            'has_portfolio' => ['nullable', 'boolean'],
            'multi_service' => ['nullable', 'boolean'],
            'date' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(ProviderSearch::SORTS))],
        ]);
        $searching = collect($filters)->except('sort')->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        return view('marketplace.index', [
            'filters' => $filters,
            'searching' => $searching,
            'results' => $searching ? $search->search($filters) : null,
            'categories' => ServiceCategory::active()->topLevel()->withCount(['providers as listed_count' => fn ($q) => $q->listed()])->orderBy('sort')->get(),
            'regions' => TzRegion::orderBy('name')->get(),
            'favouriteIds' => $request->user()->favouriteProviders()->pluck('provider_profiles.id')->all(),
            'sorts' => ProviderSearch::SORTS,
        ]);
    }

    public function show(Request $request, ProviderProfile $provider)
    {
        $this->authorize('view', $provider);
        $user = $request->user();
        $provider->load(['categories', 'region', 'primaryCategory',
            'services' => fn ($q) => $q->published()->with(['activePackages', 'category']),
            'media' => fn ($q) => $q->where('moderation', 'visible')]);

        if ($provider->isListed() && ! $provider->memberFor($user)) {
            DB::table('provider_profile_views')->upsert(
                [['provider_id' => $provider->id, 'date' => today()->toDateString(), 'views' => 1]],
                ['provider_id', 'date'], ['views' => DB::raw('views + 1')],
            );
        }

        $weddings = $user->accessibleWeddings();
        $hasEnquired = $provider->enquiries()->whereIn('wedding_id', $weddings->pluck('id'))->exists();

        return view('marketplace.provider-profile', [
            'provider' => $provider,
            'weddings' => $weddings,
            'regions' => TzRegion::orderBy('name')->get(),
            'isFavourite' => $user->favouriteProviders()->whereKey($provider->id)->exists(),
            // Provider-controlled visibility: "after_enquiry" contacts appear once this user's wedding has sent a request.
            'showPhone' => $provider->phone_visibility === 'public' || $hasEnquired,
            'showEmail' => $provider->email_visibility === 'public' || $hasEnquired,
            'clientToken' => bin2hex(random_bytes(16)),
        ]);
    }

    public function favourite(Request $request, ProviderProfile $provider)
    {
        $this->authorize('view', $provider);
        $user = $request->user();
        if ($user->favouriteProviders()->whereKey($provider->id)->exists()) {
            $user->favouriteProviders()->detach($provider->id);

            return back()->with('success', 'Removed from your saved providers.');
        }
        $user->favouriteProviders()->attach($provider->id, ['created_at' => now()]);

        return back()->with('success', 'Saved. Find it under Saved providers.');
    }

    public function favourites(Request $request)
    {
        return view('marketplace.favourites', [
            'providers' => $request->user()->favouriteProviders()->with(['primaryCategory', 'region'])->get(),
        ]);
    }

    public function enquire(Request $request, ProviderProfile $provider, NegotiationService $negotiation)
    {
        $this->authorize('view', $provider);
        $data = $request->validate([
            'wedding_id' => ['required', 'integer'],
            'service_id' => ['required', 'integer'],
            'package_id' => ['nullable', 'integer'],
            'event_name' => ['nullable', 'string', 'max:120'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_start' => ['nullable', 'date_format:H:i'],
            'event_end' => ['nullable', 'date_format:H:i', 'after:event_start'],
            'venue' => ['nullable', 'string', 'max:255'],
            'region_id' => ['nullable', 'exists:tz_regions,id'],
            'guests' => ['nullable', 'integer', 'between:1,100000'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'preferred_contact' => ['required', 'in:phone,whatsapp,email,aroos'],
            'contact_phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9 ()-]{7,32}$/'],
            'client_token' => ['required', 'string', 'size:32'],
        ]);
        $wedding = Wedding::findOrFail($data['wedding_id']);
        // Anyone on the wedding team may request services for it (couples included).
        abort_unless($request->user()->canAccessWedding($wedding), 403);
        if ($provider->memberFor($request->user())) {
            return back()->withErrors(['marketplace' => 'You cannot send a request to your own business.']);
        }

        try {
            $enquiry = $negotiation->createEnquiry($request->user(), $wedding, $provider, $data);
        } catch (MarketplaceException $e) {
            return back()->withInput()->withErrors(['marketplace' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            // Same form submitted twice: show the request that already exists.
            $enquiry = $provider->enquiries()->where('customer_user_id', $request->user()->id)->where('client_token', $data['client_token'])->firstOrFail();
        }

        return redirect()->route('services.enquiry', [$wedding, $enquiry])->with('success', 'Request sent to '.$provider->business_name.'. You will be notified when they reply.');
    }
}
