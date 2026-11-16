@extends('layouts.app')

@section('title', 'My business')

@use('App\Marketplace\Money')

@section('content')
@include('marketplace.provider._nav')

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    <div class="space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach([['New requests', $stats['open_requests']], ['In negotiation', $stats['negotiating']], ['Confirmed bookings', $stats['confirmed']], ['Completed', $stats['completed']], ['Cancelled', $stats['cancelled']], ['Profile views (30d)', $stats['views_30d']], ['Requests (all time)', $stats['enquiries_total']], ['Request → booking', $stats['conversion'] === null ? '—' : $stats['conversion'].'%']] as [$label, $value])
                <div class="card p-4"><p class="eyebrow">{{ $label }}</p><p class="font-serif text-2xl mt-2">{{ $value }}</p></div>
            @endforeach
        </div>

        <div class="card p-5">
            <div class="flex justify-between mb-3"><p class="eyebrow">Latest requests</p><a href="{{ route('provider.enquiries') }}" class="text-xs underline">All requests</a></div>
            @forelse($latestRequests as $e)
                <a href="{{ route('provider.enquiries.show', $e) }}" class="flex justify-between gap-3 py-2 border-t border-line first:border-0 text-sm hover:text-gold">
                    <span>{{ $e->contact_name }} · {{ $e->service?->title }} · {{ $e->event_date->format('j M Y') }}</span>
                    @include('marketplace._status', ['status' => $e->status])
                </a>
            @empty
                <p class="text-sm text-muted">No open requests. Complete your profile and get verified to start receiving them.</p>
            @endforelse
        </div>

        <div class="card p-5">
            <div class="flex justify-between mb-3"><p class="eyebrow">Upcoming events</p><a href="{{ route('provider.calendar') }}" class="text-xs underline">Calendar</a></div>
            @forelse($upcoming as $b)
                <div class="flex justify-between py-2 border-t border-line first:border-0 text-sm">
                    <span>{{ $b->event_date->format('D j M Y') }} · {{ $b->service?->title }} · {{ $b->wedding?->coupleNames() }}</span>
                    <span>{{ Money::format($b->agreed_amount, $b->currency) }}</span>
                </div>
            @empty
                <p class="text-sm text-muted">No upcoming bookings.</p>
            @endforelse
        </div>
    </div>

    <aside class="space-y-4">
        <div class="card p-5">
            <p class="eyebrow mb-2">Profile {{ $completeness['percent'] }}% complete</p>
            <div class="h-2 rounded-full bg-canvas overflow-hidden mb-4"><div class="h-2 gold-gradient" style="width: {{ $completeness['percent'] }}%"></div></div>
            <ul class="text-sm space-y-1.5">
                @foreach($completeness['items'] as $item => $done)
                    <li class="{{ $done ? 'text-inktext' : 'text-muted' }}">{{ $done ? '✓' : '○' }} {{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div class="card p-5 text-sm">
            <p class="eyebrow mb-2">Visibility</p>
            @if($provider->isBanned())
                <p class="text-red-500">Suspended by Aroos.</p>
            @else
                <p class="text-muted mb-3">
                    @if($provider->isListed()) Couples can find you in the marketplace.
                    @elseif($provider->status === 'active') Published — you will appear once Aroos verifies your business.
                    @else Not published. Customers cannot find you yet. @endif
                </p>
                <form method="POST" action="{{ route('provider.visibility') }}">@csrf
                    @if($provider->status === 'active')
                        <input type="hidden" name="status" value="hidden"><button class="btn-ghost w-full">Hide my profile</button>
                    @else
                        <input type="hidden" name="status" value="active"><button class="btn-gold w-full">Publish my profile</button>
                    @endif
                </form>
                @if(in_array($provider->verification_status, ['none', 'rejected'], true))
                    <a href="{{ route('provider.verification') }}" class="btn-ghost w-full text-center mt-2">Get verified</a>
                @endif
            @endif
        </div>
    </aside>
</div>
@endsection
