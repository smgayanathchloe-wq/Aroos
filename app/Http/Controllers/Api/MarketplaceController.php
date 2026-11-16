<?php

namespace App\Http\Controllers\Api;

use App\Marketplace\AvailabilityService;
use App\Marketplace\BookingService;
use App\Marketplace\MarketplaceException;
use App\Marketplace\NegotiationService;
use App\Marketplace\ProfileCompleteness;
use App\Marketplace\ProviderSearch;
use App\Marketplace\ReviewService;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\QuotationVersion;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\Marketplace\TzRegion;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Marketplace for the mobile app. Same services and rules as the web; the
 * wedding side uses the X-Wedding-Id header (read access is enough, so
 * couples can act), the provider side uses the caller's own business.
 */
class MarketplaceController extends ApiController
{
    public function __construct(
        private readonly NegotiationService $negotiation,
        private readonly BookingService $bookings,
    ) {
    }

    // ---- Discovery -------------------------------------------------------

    public function categories(): JsonResponse
    {
        return $this->ok(ServiceCategory::active()->orderBy('sort')->get()->map(fn ($c) => [
            'id' => $c->id, 'parent_id' => $c->parent_id, 'name' => $c->name, 'name_sw' => $c->name_sw,
            'description' => $c->description, 'image_url' => $c->image_path ? asset('storage/'.$c->image_path) : null,
        ]));
    }

    public function regions(): JsonResponse
    {
        return $this->ok(TzRegion::orderBy('name')->get(['id', 'name']));
    }

    public function providers(Request $request, ProviderSearch $search): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', 'integer'], 'region' => ['nullable', 'integer'],
            'min_price' => ['nullable', 'numeric'], 'max_price' => ['nullable', 'numeric'],
            'pricing' => ['nullable', 'in:fixed,packages,negotiable,quote_only'], 'has_packages' => ['nullable', 'boolean'],
            'has_portfolio' => ['nullable', 'boolean'], 'min_rating' => ['nullable', 'integer', 'between:1,5'], 'date' => ['nullable', 'date'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(ProviderSearch::SORTS))],
        ]);

        return $this->paginated($search->query($filters)->paginate($this->perPage($request, 20)), fn ($p) => $this->providerCard($p));
    }

    public function provider(Request $request, string $slug): JsonResponse
    {
        $provider = $this->listedProvider($request, $slug);
        $provider->load(['categories', 'region', 'services' => fn ($q) => $q->published()->with('activePackages'), 'media' => fn ($q) => $q->where('moderation', 'visible')]);
        $hasEnquired = $provider->enquiries()->whereIn('wedding_id', $request->user()->accessibleWeddings()->pluck('id'))->exists();

        return $this->ok($this->providerCard($provider) + [
            'description' => $provider->description,
            'categories' => $provider->categories->pluck('name'),
            'phone' => $provider->phone_visibility === 'public' || $hasEnquired ? $provider->phone : null,
            'whatsapp' => $provider->phone_visibility === 'public' || $hasEnquired ? $provider->whatsapp : null,
            'email' => $provider->email_visibility === 'public' || $hasEnquired ? $provider->email : null,
            'website' => $provider->website,
            'social_links' => $provider->social_links ?? (object) [],
            'operating_hours' => $provider->operating_hours['text'] ?? null,
            'services' => $provider->services->map(fn ($s) => [
                'id' => $s->id, 'title' => $s->title, 'description' => $s->description, 'pricing_type' => $s->pricing_type,
                'base_price' => $s->base_price !== null ? (float) $s->base_price : null, 'is_negotiable' => $s->is_negotiable,
                'inclusions' => $s->inclusions ?? [], 'exclusions' => $s->exclusions ?? [],
                'packages' => $s->activePackages->map(fn ($p) => [
                    'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => $p->price !== null ? (float) $p->price : null,
                    'features' => $p->features ?? [], 'extras' => $p->extras ?? [], 'deposit_percent' => $p->deposit_percent,
                    'terms' => $p->terms, 'cancellation_policy' => $p->cancellation_policy,
                ]),
            ]),
            'media' => $provider->media->map(fn ($m) => ['type' => $m->type, 'url' => $m->type === 'video_link' ? $m->url : $m->fileUrl(), 'caption' => $m->caption, 'album' => $m->album]),
            'reviews' => $provider->publishedReviews()->with('customer')->limit(20)->get()->map(fn ($r) => [
                'overall' => $r->overall, 'body' => $r->body, 'reviewer' => $r->reviewerName(), 'date' => $r->created_at->toDateString(), 'response' => $r->provider_response,
            ]),
        ]);
    }

    public function favourite(Request $request, string $slug): JsonResponse
    {
        $provider = $this->listedProvider($request, $slug);
        $user = $request->user();
        $saved = ! $user->favouriteProviders()->whereKey($provider->id)->exists();
        $saved ? $user->favouriteProviders()->attach($provider->id, ['created_at' => now()]) : $user->favouriteProviders()->detach($provider->id);

        return $this->ok(['saved' => $saved]);
    }

    public function favourites(Request $request): JsonResponse
    {
        return $this->ok($request->user()->favouriteProviders()->listed()->with(['primaryCategory', 'region'])->get()->map(fn ($p) => $this->providerCard($p)));
    }

    // ---- Wedding side ----------------------------------------------------

    public function createRequest(Request $request, string $slug): JsonResponse
    {
        $provider = $this->listedProvider($request, $slug);
        $wedding = $this->wedding($request);
        if ($provider->memberFor($request->user())) {
            return $this->fail('You cannot send a request to your own business.', 422);
        }
        $data = $request->validate([
            'service_id' => ['required', 'integer'], 'package_id' => ['nullable', 'integer'],
            'event_name' => ['nullable', 'string', 'max:120'], 'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_start' => ['nullable', 'date_format:H:i'], 'event_end' => ['nullable', 'date_format:H:i', 'after:event_start'],
            'venue' => ['nullable', 'string', 'max:255'], 'region_id' => ['nullable', 'exists:tz_regions,id'],
            'guests' => ['nullable', 'integer', 'between:1,100000'], 'budget' => ['nullable', 'numeric', 'min:0'],
            'requirements' => ['nullable', 'string', 'max:5000'], 'preferred_contact' => ['nullable', 'in:phone,whatsapp,email,aroos'],
            'contact_phone' => ['nullable', 'string', 'max:32'], 'client_token' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $enquiry = $this->negotiation->createEnquiry($request->user(), $wedding, $provider, $data);
        } catch (MarketplaceException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (UniqueConstraintViolationException) {
            $enquiry = ServiceEnquiry::where('customer_user_id', $request->user()->id)->where('client_token', $data['client_token'])->firstOrFail();
        }

        return $this->ok($this->enquiry($enquiry->fresh()));
    }

    public function requests(Request $request): JsonResponse
    {
        $wedding = $this->wedding($request);

        return $this->paginated(ServiceEnquiry::with(['provider', 'service', 'versions'])->where('wedding_id', $wedding->id)
            ->latest('last_activity_at')->paginate($this->perPage($request)), fn ($e) => $this->enquiry($e, false));
    }

    public function showRequest(Request $request, int $id): JsonResponse
    {
        return $this->ok($this->enquiry($this->weddingEnquiry($request, $id)));
    }

    public function counter(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->weddingEnquiry($request, $id);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1'], 'message' => ['nullable', 'string', 'max:3000']]);

        return $this->attempt(fn () => $this->negotiation->counter($enquiry, $request->user(), $data), $enquiry);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->weddingEnquiry($request, $id);
        $data = $request->validate(['version_id' => ['required', 'integer'], 'confirm' => ['accepted']]);

        try {
            $booking = $this->bookings->accept($enquiry, (int) $data['version_id'], $request->user());
        } catch (MarketplaceException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->booking($booking));
    }

    public function message(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->weddingEnquiry($request, $id);
        $body = $request->validate(['body' => ['required', 'string', 'max:3000']])['body'];

        return $this->attempt(fn () => $this->negotiation->message($enquiry, $request->user(), 'customer', $body), $enquiry);
    }

    public function withdraw(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->weddingEnquiry($request, $id);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->attempt(fn () => $this->negotiation->decline($enquiry, $request->user(), 'customer', $reason), $enquiry);
    }

    public function weddingBookings(Request $request): JsonResponse
    {
        $wedding = $this->wedding($request);

        return $this->ok(ServiceBooking::with(['provider', 'service', 'package', 'review'])->where('wedding_id', $wedding->id)->orderBy('event_date')->get()->map(fn ($b) => $this->booking($b)));
    }

    public function cancelWeddingBooking(Request $request, int $id): JsonResponse
    {
        $booking = ServiceBooking::where('wedding_id', $this->wedding($request)->id)->findOrFail($id);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        try {
            $this->bookings->cancel($booking, $request->user(), 'customer', $reason);
        } catch (MarketplaceException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->booking($booking->fresh()));
    }

    public function review(Request $request, int $id, ReviewService $reviews): JsonResponse
    {
        $booking = ServiceBooking::where('wedding_id', $this->wedding($request)->id)->findOrFail($id);
        $rating = ['nullable', 'integer', 'between:1,5'];
        $data = $request->validate(['overall' => ['required', 'integer', 'between:1,5'], 'quality' => $rating, 'communication' => $rating, 'professionalism' => $rating, 'value' => $rating, 'body' => ['nullable', 'string', 'max:3000']]);
        try {
            $review = $reviews->create($booking, $request->user(), $data);
        } catch (MarketplaceException $e) {
            return $this->fail($e->getMessage(), 422);
        } catch (UniqueConstraintViolationException) {
            return $this->fail('This booking has already been reviewed.', 422);
        }

        return $this->ok(['id' => $review->id, 'overall' => $review->overall]);
    }

    // ---- Provider side ---------------------------------------------------

    public function business(Request $request): JsonResponse
    {
        $provider = $this->ownBusiness($request, 'enquiries');

        return $this->ok($this->providerCard($provider) + [
            'status' => $provider->status, 'verification_status' => $provider->verification_status,
            'completeness' => ProfileCompleteness::for($provider),
            'open_requests' => $provider->enquiries()->whereIn('status', ServiceEnquiry::OPEN)->count(),
            'upcoming_bookings' => $provider->bookings()->where('status', 'confirmed')->whereDate('event_date', '>=', today())->count(),
        ]);
    }

    public function businessRequests(Request $request): JsonResponse
    {
        $provider = $this->ownBusiness($request, 'enquiries');

        return $this->paginated($provider->enquiries()->with(['service', 'versions', 'provider'])
            ->when($request->query('open'), fn ($q) => $q->whereIn('status', ServiceEnquiry::OPEN))
            ->latest('last_activity_at')->paginate($this->perPage($request)), fn ($e) => $this->enquiry($e, false, true));
    }

    public function businessRequest(Request $request, int $id): JsonResponse
    {
        return $this->ok($this->enquiry($this->providerEnquiry($request, $id, 'enquiries'), true, true));
    }

    public function quote(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->providerEnquiry($request, $id, 'respond');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'], 'package_id' => ['nullable', 'integer'],
            'proposed_date' => ['nullable', 'date', 'after_or_equal:today'], 'terms' => ['nullable', 'string', 'max:3000'],
            'message' => ['nullable', 'string', 'max:3000'], 'valid_days' => ['nullable', 'integer', 'between:1,60'],
        ]);

        return $this->attempt(fn () => $this->negotiation->quote($enquiry, $request->user(), $data), $enquiry, true);
    }

    public function providerMessage(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->providerEnquiry($request, $id, 'respond');
        $data = $request->validate(['body' => ['required', 'string', 'max:3000'], 'kind' => ['nullable', 'in:message,info_request,meeting_request']]);

        return $this->attempt(fn () => $this->negotiation->message($enquiry, $request->user(), 'provider', $data['body'], $data['kind'] ?? 'message'), $enquiry, true);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $enquiry = $this->providerEnquiry($request, $id, 'respond');
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        return $this->attempt(fn () => $this->negotiation->decline($enquiry, $request->user(), 'provider', $reason), $enquiry, true);
    }

    public function businessBookings(Request $request): JsonResponse
    {
        $provider = $this->ownBusiness($request, 'bookings');

        return $this->ok($provider->bookings()->with(['service', 'package', 'provider', 'enquiry'])->orderBy('event_date')->get()->map(fn ($b) => $this->booking($b, true)));
    }

    public function businessBookingAction(Request $request, int $id, string $action): JsonResponse
    {
        $provider = $this->ownBusiness($request, 'bookings');
        $booking = $provider->bookings()->findOrFail($id);
        try {
            $action === 'complete'
                ? $this->bookings->complete($booking, $request->user())
                : $this->bookings->cancel($booking, $request->user(), 'provider', $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);
        } catch (MarketplaceException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->booking($booking->fresh(), true));
    }

    public function calendar(Request $request, AvailabilityService $availability): JsonResponse
    {
        $provider = $this->ownBusiness($request, 'calendar');
        $month = CarbonImmutable::parse(($request->query('month') ?: now()->format('Y-m')).'-01');
        $data = $availability->month($provider->id, $month);

        return $this->ok([
            'month' => $month->format('Y-m'),
            'bookings' => $data['bookings']->flatten()->map(fn ($b) => ['id' => $b->id, 'date' => $b->event_date->toDateString(), 'start' => $b->starts_at, 'service' => $b->service?->title, 'status' => $b->status])->values(),
            'tentative' => $data['tentative']->flatten()->map(fn ($e) => ['request_id' => $e->id, 'date' => $e->event_date->toDateString()])->values(),
            'blocked' => $data['blackouts']->map(fn ($b) => ['from' => $b->starts_on->toDateString(), 'to' => $b->ends_on->toDateString(), 'service' => $b->service?->title])->values(),
        ]);
    }

    // ---- Helpers ---------------------------------------------------------

    private function listedProvider(Request $request, string $slug): ProviderProfile
    {
        $provider = ProviderProfile::with('members')->where('slug', $slug)->firstOrFail();
        abort_unless($request->user()->can('view', $provider), 404);

        return $provider;
    }

    private function weddingEnquiry(Request $request, int $id): ServiceEnquiry
    {
        return ServiceEnquiry::with(['provider', 'service', 'package', 'versions', 'messages', 'booking'])
            ->where('wedding_id', $this->wedding($request)->id)->findOrFail($id);
    }

    private function ownBusiness(Request $request, string $permission): ProviderProfile
    {
        $provider = $request->user()->providerProfile();
        abort_unless($provider, 404, 'You have not registered a business.');
        abort_unless($provider->allows($request->user(), $permission), 403);
        abort_if($provider->isBanned() && ! $request->isMethodSafe(), 403, 'This business has been suspended by Aroos.');

        return $provider;
    }

    private function providerEnquiry(Request $request, int $id, string $permission): ServiceEnquiry
    {
        $provider = $this->ownBusiness($request, $permission);

        return ServiceEnquiry::with(['provider', 'service', 'package', 'versions', 'messages', 'booking'])->where('provider_id', $provider->id)->findOrFail($id);
    }

    private function attempt(callable $action, ServiceEnquiry $enquiry, bool $providerView = false): JsonResponse
    {
        try {
            $action();
        } catch (MarketplaceException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($this->enquiry($enquiry->fresh(['provider', 'service', 'package', 'versions', 'messages', 'booking']), true, $providerView));
    }

    private function providerCard(ProviderProfile $p): array
    {
        return [
            'slug' => $p->slug, 'business_name' => $p->business_name, 'category' => $p->primaryCategory?->name,
            'region' => $p->region?->name, 'city' => $p->city, 'verified' => $p->verification_status === 'verified',
            'logo_url' => $p->logoUrl(), 'cover_url' => $p->coverUrl(),
            'starting_price' => $p->starting_price !== null ? (float) $p->starting_price : null, 'currency' => config('marketplace.currency'),
            'rating' => $p->rating_count ? (float) $p->rating_avg : null, 'review_count' => (int) $p->rating_count,
            'sponsored' => $p->isSponsoredNow(), 'has_portfolio' => (bool) $p->has_portfolio, 'has_packages' => (bool) $p->has_packages,
        ];
    }

    private function enquiry(ServiceEnquiry $e, bool $full = true, bool $providerView = false): array
    {
        $latest = $e->versions->sortByDesc('version_no')->first();
        $out = [
            'id' => $e->id, 'reference' => $e->reference, 'status' => $e->status,
            'provider' => ['slug' => $e->provider->slug, 'business_name' => $e->provider->business_name],
            'service' => $e->service?->title, 'package_id' => $e->package_id,
            'event_date' => $e->event_date->toDateString(), 'event_start' => $e->event_start, 'event_end' => $e->event_end,
            'venue' => $e->venue, 'guests' => $e->guests, 'budget' => $e->budget !== null ? (float) $e->budget : null,
            'latest_offer' => $latest ? $this->version($latest) : null,
            'can_accept' => ! $providerView && $e->isOpen() && $latest && $latest->author_side === 'provider' && $latest->status === 'pending' && ! $latest->isExpired(),
            'last_activity_at' => $e->last_activity_at?->toIso8601String(),
        ];
        if ($providerView) {
            // Providers see the wedding's contact only on requests sent to them.
            $out['contact'] = ['name' => $e->contact_name, 'phone' => $e->contact_phone, 'email' => $e->contact_email, 'preferred' => $e->preferred_contact];
        }
        if ($full) {
            $out += [
                'requirements' => $e->requirements,
                'offers' => $e->versions->map(fn ($v) => $this->version($v))->values(),
                'messages' => $e->messages->map(fn ($m) => ['side' => $m->side, 'kind' => $m->kind, 'body' => $m->body, 'at' => $m->created_at->toIso8601String()])->values(),
                'booking' => $e->booking ? $this->booking($e->booking, $providerView) : null,
            ];
        }

        return $out;
    }

    private function version(QuotationVersion $v): array
    {
        return [
            'id' => $v->id, 'version' => $v->version_no, 'by' => $v->author_side, 'amount' => (float) $v->amount, 'currency' => $v->currency,
            'previous_amount' => $v->previous_amount !== null ? (float) $v->previous_amount : null, 'package_id' => $v->package_id,
            'proposed_date' => $v->proposed_date?->toDateString(), 'message' => $v->message, 'terms' => $v->terms,
            'status' => $v->status === 'pending' && $v->isExpired() ? 'expired' : $v->status,
            'valid_until' => $v->valid_until?->toIso8601String(), 'created_at' => $v->created_at?->toIso8601String(),
        ];
    }

    private function booking(ServiceBooking $b, bool $providerView = false): array
    {
        return [
            'id' => $b->id, 'reference' => $b->reference, 'status' => $b->status, 'request_id' => $b->enquiry_id,
            'provider' => ['slug' => $b->provider?->slug, 'business_name' => $b->provider?->business_name],
            'service' => $b->service?->title, 'package' => $b->package?->name,
            'event_date' => $b->event_date->toDateString(), 'starts_at' => $b->starts_at, 'ends_at' => $b->ends_at,
            'agreed_amount' => (float) $b->agreed_amount, 'currency' => $b->currency, 'payment_status' => $b->payment_status,
            'cancel_reason' => $b->cancel_reason,
            'reviewed' => $providerView ? null : $b->review()->exists(),
            'event' => $b->terms_snapshot['event'] ?? null,
        ];
    }
}
