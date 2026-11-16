@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
@include('marketplace.provider._nav')

@php
    $start = $month->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
    $end = $month->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $blocked = fn ($day) => $data['blackouts']->filter(fn ($b) => $b->starts_on->lte($day) && $b->ends_on->gte($day));
@endphp

<div class="grid lg:grid-cols-[1fr_320px] gap-6">
    <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
            <a href="{{ route('provider.calendar', ['month' => $month->subMonth()->format('Y-m')]) }}" class="btn-ghost text-xs" aria-label="Previous month">←</a>
            <h2 class="font-serif text-2xl">{{ $month->format('F Y') }}</h2>
            <a href="{{ route('provider.calendar', ['month' => $month->addMonth()->format('Y-m')]) }}" class="btn-ghost text-xs" aria-label="Next month">→</a>
        </div>
        <div class="grid grid-cols-7 gap-1 text-xs">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div class="text-center text-muted py-1">{{ $d }}</div>@endforeach
            @for($day = $start; $day->lte($end); $day = $day->addDay())
                @php $key = $day->toDateString(); $bookings = $data['bookings'][$key] ?? collect(); $tentative = $data['tentative'][$key] ?? collect(); $blocks = $blocked($day); @endphp
                <div class="min-h-24 rounded-lg border p-1.5 {{ $day->month !== $month->month ? 'opacity-40' : '' }} {{ $blocks->isNotEmpty() ? 'bg-canvas border-dashed' : 'border-line' }} {{ $day->isToday() ? 'ring-1 ring-gold' : '' }}">
                    <p class="text-right text-muted">{{ $day->day }}</p>
                    @foreach($bookings as $b)<p class="truncate rounded px-1 mt-0.5 {{ $b->status === 'completed' ? 'bg-gold/10 text-gold' : 'bg-green-600/10 text-green-800' }}" title="{{ $b->service?->title }} · {{ $b->wedding?->coupleNames() }}">{{ $b->starts_at ? substr($b->starts_at, 0, 5).' ' : '' }}{{ $b->service?->title }}</p>@endforeach
                    @foreach($tentative as $t)<a href="{{ route('provider.enquiries.show', $t) }}" class="block truncate rounded px-1 mt-0.5 border border-dashed border-gold/50 text-muted" title="Tentative: open quotation">? {{ $t->contact_name }}</a>@endforeach
                    @foreach($blocks as $b)<p class="truncate text-muted mt-0.5">⛔ {{ $b->note ?: ($b->service?->title ?? 'Unavailable') }}</p>@endforeach
                </div>
            @endfor
        </div>
        <p class="text-xs text-muted mt-3"><span class="text-green-800">■</span> confirmed · <span class="text-gold">■</span> completed · dashed = tentative (quote open, not reserved) · ⛔ blocked</p>
    </div>

    <div class="space-y-4">
        <form method="POST" action="{{ route('provider.blackouts.store') }}" class="card p-5 space-y-2">@csrf
            <p class="eyebrow">Block dates</p>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-muted">From<input type="date" name="starts_on" required class="lux-input mt-1"></label>
                <label class="text-xs text-muted">To<input type="date" name="ends_on" required class="lux-input mt-1"></label>
            </div>
            <select name="service_id" class="lux-input" aria-label="Service"><option value="">All services</option>@foreach($services as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select>
            <input name="note" placeholder="Note (only you see it)" class="lux-input" aria-label="Note">
            <button class="btn-gold w-full">Block</button>
        </form>
        <div class="card p-5">
            <p class="eyebrow mb-2">Blocked this month</p>
            @forelse($data['blackouts'] as $b)
                <form method="POST" action="{{ route('provider.blackouts.destroy', $b) }}" class="flex justify-between text-sm py-1">@csrf @method('DELETE')
                    <span>{{ $b->starts_on->format('j M') }}{{ $b->ends_on->ne($b->starts_on) ? '–'.$b->ends_on->format('j M') : '' }} · {{ $b->service?->title ?? 'All' }}</span>
                    <button class="text-xs text-muted hover:text-red-500">Unblock</button>
                </form>
            @empty
                <p class="text-sm text-muted">No blocked dates.</p>
            @endforelse
        </div>
        <p class="text-xs text-muted">Capacity and time-slot rules are set per service under Services. Double bookings are prevented when a couple accepts a quotation.</p>
    </div>
</div>
@endsection
