<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="eyebrow">My business</p>
            <h1 class="font-serif text-3xl mt-1">{{ $provider->business_name }}</h1>
        </div>
        <div class="flex items-center gap-2 text-xs">
            @include('marketplace.provider._badges', ['provider' => $provider])
            <a href="{{ route('marketplace.provider', $provider) }}" class="btn-ghost text-xs">View public profile</a>
        </div>
    </div>
    @if($provider->isBanned())
        <div class="mt-3 px-4 py-3 rounded-lg text-sm" style="background: rgba(220,80,80,.08); border: 1px solid rgba(220,80,80,.3); color: #b04a4a;">Suspended by Aroos: {{ $provider->ban_reason }}. Your history is kept; contact Aroos to resolve.</div>
    @endif
    <nav class="flex gap-1 mt-4 flex-wrap" aria-label="Business sections">
        @foreach(['provider.dashboard' => 'Overview', 'provider.enquiries' => 'Requests', 'provider.bookings' => 'Bookings', 'provider.calendar' => 'Calendar', 'provider.services' => 'Services & packages', 'provider.portfolio' => 'Portfolio', 'provider.profile' => 'Profile', 'provider.verification' => 'Verification'] as $r => $l)
            <a href="{{ route($r) }}" class="text-xs px-3 py-1.5 rounded-full border {{ request()->routeIs($r.'*') ? 'border-gold text-gold' : 'border-line text-muted hover:text-inktext' }}">{{ $l }}</a>
        @endforeach
    </nav>
</div>
