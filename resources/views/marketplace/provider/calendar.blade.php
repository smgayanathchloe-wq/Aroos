@extends('layouts.app')

@section('title', 'Calendar')

@section('content')
@include('marketplace.provider._nav')

@php
    $step = ['month' => 'addMonth', 'week' => 'addWeek', 'day' => 'addDay'][$view];
    $back = ['month' => 'subMonth', 'week' => 'subWeek', 'day' => 'subDay'][$view];
    $title = match ($view) {
        'day' => $date->format('l j F Y'),
        'week' => $from->format('j M').' – '.$to->format('j M Y'),
        default => $date->format('F Y'),
    };
    $blocked = fn ($day) => $data['blackouts']->filter(fn ($b) => $b->starts_on->lte($day) && $b->ends_on->gte($day));
    $link = fn ($v, $d) => route('provider.calendar', ['view' => $v, 'date' => $d->toDateString()]);
@endphp

<div class="grid lg:grid-cols-[1fr_320px] gap-6">
    <div class="card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-2">
                <a href="{{ $link($view, $date->{$back}()) }}" class="btn-ghost text-xs" aria-label="Previous">←</a>
                <a href="{{ $link($view, \Carbon\CarbonImmutable::today()) }}" class="btn-ghost text-xs">Today</a>
                <a href="{{ $link($view, $date->{$step}()) }}" class="btn-ghost text-xs" aria-label="Next">→</a>
            </div>
            <h2 class="font-serif text-2xl">{{ $title }}</h2>
            <nav class="flex gap-1" aria-label="Calendar view">
                @foreach(['month' => 'Month', 'week' => 'Week', 'day' => 'Day'] as $v => $l)
                    <a href="{{ $link($v, $date) }}" class="text-xs px-3 py-1.5 rounded-full border {{ $view === $v ? 'border-gold text-gold' : 'border-line text-muted' }}" @if($view === $v) aria-current="page" @endif>{{ $l }}</a>
                @endforeach
            </nav>
        </div>

        @if($view === 'day')
            @php $key = $date->toDateString(); $bookings = $data['bookings'][$key] ?? collect(); $tentative = $data['tentative'][$key] ?? collect(); $blocks = $blocked($date); @endphp
            <div class="space-y-3">
                @foreach($blocks as $b)<p class="text-sm rounded-lg bg-canvas border border-dashed border-line px-4 py-3">⛔ Blocked{{ $b->service ? ' for '.$b->service->title : '' }}{{ $b->note ? ' — '.$b->note : '' }}</p>@endforeach
                @forelse($bookings as $b)
                    <div class="rounded-lg border {{ $b->status === 'completed' ? 'border-gold/50' : 'border-green-600/40' }} px-4 py-3">
                        <p class="font-medium">{{ $b->starts_at ? substr($b->starts_at, 0, 5).($b->ends_at ? '–'.substr($b->ends_at, 0, 5) : '') : 'All day' }} · {{ $b->service?->title }}</p>
                        <p class="text-sm text-muted">{{ $b->wedding?->coupleNames() }}{{ ($b->terms_snapshot['event']['venue'] ?? null) ? ' · '.$b->terms_snapshot['event']['venue'] : '' }}{{ ($b->terms_snapshot['event']['guests'] ?? null) ? ' · '.number_format($b->terms_snapshot['event']['guests']).' guests' : '' }}</p>
                        <p class="text-xs mt-1">{{ $b->reference }} · {{ \App\Marketplace\Money::format($b->agreed_amount, $b->currency) }} · {{ $b->status }} · <a href="{{ route('provider.enquiries.show', $b->enquiry_id) }}" class="underline">details</a></p>
                    </div>
                @empty
                    @if($blocks->isEmpty())<p class="text-sm text-muted">No bookings on this day.</p>@endif
                @endforelse
                @foreach($tentative as $t)
                    <a href="{{ route('provider.enquiries.show', $t) }}" class="block rounded-lg border border-dashed border-gold/50 px-4 py-3 text-sm text-muted">? Tentative — open quotation for {{ $t->contact_name }}{{ $t->event_start ? ' at '.substr($t->event_start, 0, 5) : '' }} (not reserved)</a>
                @endforeach
            </div>
        @else
            <div class="grid grid-cols-7 gap-1 text-xs">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)<div class="text-center text-muted py-1">{{ $d }}</div>@endforeach
                @for($day = $from; $day->lte($to); $day = $day->addDay())
                    @php $key = $day->toDateString(); $bookings = $data['bookings'][$key] ?? collect(); $tentative = $data['tentative'][$key] ?? collect(); $blocks = $blocked($day); @endphp
                    <a href="{{ $link('day', $day) }}" class="block {{ $view === 'week' ? 'min-h-64' : 'min-h-24' }} rounded-lg border p-1.5 hover:border-gold {{ $view === 'month' && $day->month !== $date->month ? 'opacity-40' : '' }} {{ $blocks->isNotEmpty() ? 'bg-canvas border-dashed' : 'border-line' }} {{ $day->isToday() ? 'ring-1 ring-gold' : '' }}" aria-label="{{ $day->format('l j F') }}: {{ $bookings->count() }} booking(s)">
                        <p class="text-right text-muted">{{ $view === 'week' ? $day->format('j M') : $day->day }}</p>
                        @foreach($bookings as $b)
                            <p class="{{ $view === 'week' ? '' : 'truncate' }} rounded px-1 mt-0.5 {{ $b->status === 'completed' ? 'bg-gold/10 text-gold' : 'bg-green-600/10 text-green-800' }}" title="{{ $b->service?->title }} · {{ $b->wedding?->coupleNames() }}">{{ $b->starts_at ? substr($b->starts_at, 0, 5).' ' : '' }}{{ $b->service?->title }}@if($view === 'week')<br><span class="text-muted">{{ $b->wedding?->coupleNames() }}</span>@endif</p>
                        @endforeach
                        @foreach($tentative as $t)<p class="truncate rounded px-1 mt-0.5 border border-dashed border-gold/50 text-muted" title="Tentative: open quotation">? {{ $t->contact_name }}</p>@endforeach
                        @foreach($blocks as $b)<p class="truncate text-muted mt-0.5">⛔ {{ $b->note ?: ($b->service?->title ?? 'Unavailable') }}</p>@endforeach
                    </a>
                @endfor
            </div>
        @endif
        <p class="text-xs text-muted mt-3"><span class="text-green-800">■</span> confirmed · <span class="text-gold">■</span> completed · dashed = tentative (quote open, not reserved) · ⛔ blocked · click a day for details</p>
    </div>

    <div class="space-y-4">
        <form method="POST" action="{{ route('provider.blackouts.store') }}" class="card p-5 space-y-2">@csrf
            <p class="eyebrow">Block dates</p>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-muted">From<input type="date" name="starts_on" required value="{{ $view === 'day' ? $date->toDateString() : '' }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted">To<input type="date" name="ends_on" required value="{{ $view === 'day' ? $date->toDateString() : '' }}" class="lux-input mt-1"></label>
            </div>
            <select name="service_id" class="lux-input" aria-label="Service"><option value="">All services</option>@foreach($services as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach</select>
            <input name="note" placeholder="Note (only you see it)" class="lux-input" aria-label="Note">
            <button class="btn-gold w-full">Block</button>
        </form>
        <div class="card p-5">
            <p class="eyebrow mb-2">Blocked in this view</p>
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
