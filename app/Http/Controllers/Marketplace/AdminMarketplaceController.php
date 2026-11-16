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
