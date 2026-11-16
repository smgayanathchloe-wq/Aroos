<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\Notifier;
use App\Models\Marketplace\AuditLog;
use App\Models\Marketplace\ProviderMedia;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\ServiceEnquiry;
use App\Marketplace\ReviewService;
use App\Models\Marketplace\MarketplaceReport;
use App\Models\Marketplace\ProviderReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Super-admin only (route middleware). Verification is granted here and nowhere else. */
class AdminMarketplaceController extends Controller
{
    public function __construct(private readonly Notifier $notify)
    {
    }

    public function index(Request $request)
    {
        $filter = $request->get('filter', 'queue');
        $providers = ProviderProfile::with(['owner', 'primaryCategory', 'region'])
            ->when($filter === 'queue', fn ($q) => $q->whereIn('verification_status', ['submitted', 'under_review']))
            ->when($filter === 'listed', fn ($q) => $q->listed())
            ->when($filter === 'banned', fn ($q) => $q->where('status', 'banned'))
            ->when($s = $request->get('q'), fn ($q) => $q->where('business_name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'))
            ->latest('updated_at')->paginate(30)->withQueryString();

        return view('marketplace.admin.index', [
            'providers' => $providers,
            'filter' => $filter,
            'stats' => [
                'providers' => ProviderProfile::count(),
                'listed' => ProviderProfile::listed()->count(),
                'queue' => ProviderProfile::whereIn('verification_status', ['submitted', 'under_review'])->count(),
                'enquiries_30d' => ServiceEnquiry::where('created_at', '>=', now()->subDays(30))->count(),
                'bookings_30d' => ServiceBooking::where('created_at', '>=', now()->subDays(30))->whereIn('status', ServiceBooking::ACTIVE)->count(),
            ],
        ]);
    }

    public function show(ProviderProfile $provider)
    {
        $provider->load(['owner', 'categories', 'region', 'services.packages', 'media', 'verifications.documents', 'members.user']);

        return view('marketplace.admin.provider', [
            'provider' => $provider,
            'audit' => AuditLog::where('subject_type', 'ProviderProfile')->where('subject_id', $provider->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function review(Request $request, ProviderProfile $provider)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:under_review,verified,rejected'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($provider, $data, $request) {
            $provider->update([
                'verification_status' => $data['decision'],
                'verified_at' => $data['decision'] === 'verified' ? now() : null,
                'verified_by' => $data['decision'] === 'verified' ? $request->user()->id : null,
            ]);
            $provider->verifications()->first()?->update([
                'status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(),
                'rejection_reason' => $data['decision'] === 'rejected' ? $data['reason'] : null,
            ]);
        });
        AuditLog::record('verification.'.$data['decision'], $provider, ['reason' => $data['reason'] ?? null]);

        if ($data['decision'] !== 'under_review') {
            $verified = $data['decision'] === 'verified';
            $this->notify->provider($provider, $verified ? 'provider.verified' : 'provider.rejected',
                $verified ? 'Your business is verified' : 'Verification not approved',
                $verified ? 'Your profile now shows as verified'.($provider->status === 'active' ? ' and is live in the marketplace.' : '. Publish it to go live.') : $data['reason'],
                route('provider.dashboard'), 'verification.'.$data['decision'].'.'.$provider->id.'.'.now()->timestamp);
        }

        return back()->with('success', 'Verification set to '.str_replace('_', ' ', $data['decision']).'.');
    }

    public function ban(Request $request, ProviderProfile $provider)
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $provider->update(['status' => 'banned', 'ban_reason' => $reason]);
        AuditLog::record('provider.banned', $provider, ['reason' => $reason]);
        $this->notify->provider($provider, 'provider.banned', 'Your business has been suspended', $reason, route('provider.dashboard'), 'banned.'.$provider->id.'.'.now()->timestamp);

        return back()->with('success', 'Provider banned. They are hidden everywhere and cannot take new requests; history is kept.');
    }

    public function unban(ProviderProfile $provider)
    {
        $provider->update(['status' => 'hidden', 'ban_reason' => null]);
        AuditLog::record('provider.unbanned', $provider);

        return back()->with('success', 'Ban lifted. The provider must publish again to be listed.');
    }

    public function moderateMedia(Request $request, ProviderMedia $media)
    {
        $state = $request->validate(['moderation' => ['required', 'in:visible,hidden']])['moderation'];
        $media->update(['moderation' => $state]);
        \App\Marketplace\ProviderStats::refresh($media->provider);
        AuditLog::record('media.'.$state, $media);

        return back()->with('success', 'Media '.$state.'.');
    }

    /** Paid promotion. Labelled "Sponsored" wherever shown; never affects verification or organic ranking. */
    public function sponsorship(Request $request, ProviderProfile $provider)
    {
        $data = $request->validate(['featured_until' => ['nullable', 'date', 'after:today']]);
        $provider->update(['is_sponsored' => (bool) ($data['featured_until'] ?? null), 'featured_until' => $data['featured_until'] ?? null]);
        AuditLog::record('provider.sponsorship', $provider, $data);

        return back()->with('success', $provider->is_sponsored ? 'Sponsored placement until '.$provider->featured_until->format('j M Y').'.' : 'Sponsored placement removed.');
    }

    public function reports(Request $request)
    {
        $status = $request->get('status', 'open');

        return view('marketplace.admin.reports', [
            'status' => $status,
            'reports' => MarketplaceReport::with('reporter')->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'reviews' => ProviderReview::with(['provider', 'customer'])->latest('id')->limit(30)->get(),
        ]);
    }

    public function resolveReport(Request $request, MarketplaceReport $report, ReviewService $reviews)
    {
        $data = $request->validate([
            'status' => ['required', 'in:resolved,dismissed'],
            'action' => ['nullable', 'in:hide_review,hide_media'],
            'resolution' => ['nullable', 'string', 'max:255'],
        ]);
        $subject = $report->subject();
        if (($data['action'] ?? null) === 'hide_review' && $subject instanceof ProviderReview) {
            $reviews->setStatus($subject, 'hidden', $data['resolution'] ?? 'Hidden after a report');
        }
        if (($data['action'] ?? null) === 'hide_media' && $subject instanceof ProviderMedia) {
            $subject->update(['moderation' => 'hidden']);
            \App\Marketplace\ProviderStats::refresh($subject->provider);
        }
        $report->update(['status' => $data['status'], 'resolution' => $data['resolution'] ?? null, 'handled_by' => $request->user()->id, 'handled_at' => now()]);
        AuditLog::record('report.'.$data['status'], $report, $data);

        return back()->with('success', 'Report '.$data['status'].'.');
    }

    public function moderateReview(Request $request, ProviderReview $review, ReviewService $reviews)
    {
        $data = $request->validate(['status' => ['required', 'in:published,hidden'], 'note' => ['nullable', 'string', 'max:255']]);
        $reviews->setStatus($review, $data['status'], $data['note'] ?? null);
        AuditLog::record('review.'.$data['status'], $review, $data);

        return back()->with('success', 'Review '.$data['status'].'.');
    }

    public function analytics()
    {
        $enquiries = ServiceEnquiry::count();
        $booked = ServiceBooking::whereIn('status', ServiceBooking::ACTIVE)->count();

        return view('marketplace.admin.analytics', [
            'totals' => [
                'Providers' => ProviderProfile::count(),
                'Listed providers' => ProviderProfile::listed()->count(),
                'Requests' => $enquiries,
                'Bookings (confirmed + completed)' => $booked,
                'Request → booking' => $enquiries ? round(100 * $booked / $enquiries).'%' : '—',
                'Reviews' => ProviderReview::where('status', 'published')->count(),
                'Open reports' => MarketplaceReport::where('status', 'open')->count(),
            ],
            'byCategory' => ServiceCategory::topLevel()->withCount(['providers as listed' => fn ($q) => $q->listed()])->orderByDesc('listed')->get(),
            'byRegion' => ProviderProfile::listed()->with('region')->get()->groupBy(fn ($p) => $p->region?->name ?? '—')->map->count()->sortDesc(),
            'agreedValue' => (float) ServiceBooking::where('status', 'completed')->sum('agreed_amount'),
        ]);
    }

    public function categories()
    {
        return view('marketplace.admin.categories', [
            'categories' => ServiceCategory::withCount('providers')->orderBy('parent_id')->orderBy('sort')->get(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $this->categoryData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        ServiceCategory::create($data);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, ServiceCategory $category)
    {
        $data = $this->categoryData($request) + ['is_active' => $request->boolean('is_active')];
        abort_if(($data['parent_id'] ?? null) === $category->id, 422);
        $category->update($data);

        return back()->with('success', 'Category saved.');
    }

    private function categoryData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'name_sw' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'exists:service_categories,id'],
            'scheduling_default' => ['required', 'in:per_day,per_slot'],
            'sort' => ['nullable', 'integer', 'between:0,999'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('marketplace/categories', 'public');
        }
        unset($data['image']);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        return $data;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (ServiceCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
