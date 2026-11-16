<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Marketplace\Notifier;
use App\Marketplace\ReviewService;
use App\Models\Marketplace\AuditLog;
use App\Models\Marketplace\ProviderMember;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ProviderReview;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Provider reviews, team and analytics. */
class ProviderInsightsController extends Controller
{
    use ResolvesProvider;

    public function reviews(Request $request)
    {
        $provider = $this->provider($request, 'respond');

        return view('marketplace.provider.reviews', [
            'provider' => $provider,
            'reviews' => $provider->reviews()->with(['customer', 'booking.service'])->paginate(20),
        ]);
    }

    public function respond(Request $request, ProviderReview $review, ReviewService $reviews)
    {
        $provider = $this->provider($request, 'respond');
        abort_unless($review->provider_id === $provider->id, 404);
        $reviews->respond($review, $request->validate(['response' => ['required', 'string', 'max:2000']])['response']);

        return back()->with('success', 'Response published.');
    }

    public function team(Request $request)
    {
        $provider = $this->owned($request);

        return view('marketplace.provider.team', ['provider' => $provider, 'members' => $provider->members()->with('user')->orderByRaw("role = 'owner' desc")->get()]);
    }

    public function invite(Request $request, Notifier $notify)
    {
        $provider = $this->owned($request);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['in:'.implode(',', ProviderProfile::PERMISSIONS)],
        ]);
        $email = strtolower($data['email']);
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($provider->members()->where(fn ($q) => $q->where('email', $email)->when($user, fn ($w) => $w->orWhere('user_id', $user->id)))->exists()) {
            return back()->withErrors(['email' => 'This person is already on your team.']);
        }
        $member = $provider->members()->create([
            'user_id' => $user?->id, 'email' => $email, 'role' => 'staff',
            'permissions' => array_values($data['permissions'] ?? []), 'status' => $user ? 'active' : 'invited',
        ]);
        AuditLog::record('team.invited', $provider, ['email' => $email]);
        if ($user) {
            $notify->user($user, 'team.invited', 'You joined '.$provider->business_name, 'You can now help manage this business on Aroos.', route('provider.dashboard'), "team.{$member->id}");
        }

        return back()->with('success', $user ? 'Added to your team.' : 'Invitation saved. They join automatically when they sign up with '.$email.'.');
    }

    public function updateMember(Request $request, ProviderMember $member)
    {
        $provider = $this->owned($request);
        abort_unless($member->provider_id === $provider->id && $member->role !== 'owner', 404);
        $data = $request->validate(['permissions' => ['array'], 'permissions.*' => ['in:'.implode(',', ProviderProfile::PERMISSIONS)]]);
        $member->update(['permissions' => array_values($data['permissions'] ?? [])]);

        return back()->with('success', 'Permissions saved.');
    }

    public function removeMember(Request $request, ProviderMember $member)
    {
        $provider = $this->owned($request);
        abort_unless($member->provider_id === $provider->id && $member->role !== 'owner', 404);
        $member->delete();
        AuditLog::record('team.removed', $provider, ['email' => $member->email ?? $member->user?->email]);

        return back()->with('success', 'Removed from your team.');
    }

    public function analytics(Request $request)
    {
        $provider = $this->provider($request, 'reports');
        $since = CarbonImmutable::now()->subMonths(5)->startOfMonth();
        $month = fn ($column) => DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', $column)" : "DATE_FORMAT($column, '%Y-%m')";

        $views = DB::table('provider_profile_views')->where('provider_id', $provider->id)
            ->where('date', '>=', now()->subDays(29)->toDateString())->pluck('views', 'date');
        $enquiriesByMonth = ServiceEnquiry::where('provider_id', $provider->id)->where('created_at', '>=', $since)
            ->selectRaw($month('created_at').' as m, count(*) as c')->groupBy('m')->pluck('c', 'm');
        $bookingsByMonth = ServiceBooking::where('provider_id', $provider->id)->whereIn('status', ServiceBooking::ACTIVE)->where('created_at', '>=', $since)
            ->selectRaw($month('created_at').' as m, count(*) as c')->groupBy('m')->pluck('c', 'm');
        $months = collect(range(5, 0))->map(fn ($i) => CarbonImmutable::now()->subMonths($i)->format('Y-m'));

        $total = ServiceEnquiry::where('provider_id', $provider->id)->count();
        $booked = ServiceBooking::where('provider_id', $provider->id)->whereIn('status', ServiceBooking::ACTIVE)->count();

        return view('marketplace.provider.analytics', [
            'provider' => $provider,
            'days' => collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString())->mapWithKeys(fn ($d) => [$d => (int) ($views[$d] ?? 0)]),
            'months' => $months->mapWithKeys(fn ($m) => [$m => ['requests' => (int) ($enquiriesByMonth[$m] ?? 0), 'bookings' => (int) ($bookingsByMonth[$m] ?? 0)]]),
            'conversion' => $total ? round(100 * $booked / $total) : null,
            'total' => $total,
            'booked' => $booked,
            'byService' => ServiceEnquiry::where('provider_id', $provider->id)->with('service')->get()->groupBy(fn ($e) => $e->service?->title ?? '—')->map->count()->sortDesc(),
            'byPackage' => ServiceEnquiry::where('provider_id', $provider->id)->whereNotNull('package_id')->with('package')->get()->groupBy(fn ($e) => $e->package?->name ?? '—')->map->count()->sortDesc(),
            'completedValue' => (float) ServiceBooking::where('provider_id', $provider->id)->where('status', 'completed')->sum('agreed_amount'),
            'upcomingValue' => (float) ServiceBooking::where('provider_id', $provider->id)->where('status', 'confirmed')->sum('agreed_amount'),
        ]);
    }

    private function owned(Request $request): ProviderProfile
    {
        $provider = $this->provider($request, 'services');
        abort_unless($provider->memberFor($request->user())?->role === 'owner', 403, 'Only the business owner manages the team.');

        return $provider;
    }
}
