@extends('layouts.app')

@section('title', 'Service providers')

@use('App\Marketplace\Money')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Huduma</p>
        <h1 class="font-serif text-3xl mt-1">Service providers</h1>
        <p class="text-sm text-muted mt-2">Requests, quotations and bookings for this wedding. Everyone on the wedding team can follow and act on them.</p>
    </div>
    <a href="{{ route('marketplace.index') }}" class="btn-gold">Find providers</a>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Open requests</p><p class="font-serif text-2xl mt-2">{{ $open->count() }}</p></div>
    <div class="card p-5"><p class="eyebrow">Quotes to review</p><p class="font-serif text-2xl mt-2">{{ $open->whereIn('status', ['quoted', 'negotiating'])->count() }}</p></div>
    <div class="card p-5"><p class="eyebrow">Confirmed bookings</p><p class="font-serif text-2xl mt-2">{{ $bookings->where('status', 'confirmed')->count() }}</p></div>
    <div class="card p-5"><p class="eyebrow">Booked total</p><p class="font-serif text-2xl mt-2">{{ Money::format($bookings->whereIn('status', ['confirmed', 'completed'])->sum('agreed_amount')) }}</p></div>
</div>

<h2 class="eyebrow mb-3">Bookings</h2>
<div class="card overflow-x-auto mb-8">
    <table class="w-full text-sm">
        <tbody>
        @forelse($bookings as $b)
            <tr class="border-b border-line/50 align-top">
                <td class="px-4 py-3"><p class="font-medium">{{ $b->provider->business_name }}</p><p class="text-xs text-muted">{{ $b->service?->title }}{{ $b->package ? ' · '.$b->package->name : '' }} · {{ $b->reference }}</p></td>
                <td class="px-4 py-3 text-xs">{{ $b->event_date->format('j M Y') }}{{ $b->starts_at ? ' · '.substr($b->starts_at, 0, 5) : '' }}</td>
                <td class="px-4 py-3">{{ Money::format($b->agreed_amount, $b->currency) }}</td>
                <td class="px-4 py-3">@include('marketplace._status', ['status' => $b->status])</td>
                <td class="px-4 py-3 text-right">
                    <a href="{{ route('services.enquiry', [$wedding, $b->enquiry_id]) }}" class="text-xs underline">Details</a>
                    @if($b->status === 'completed' && ! $b->review)
                        <details class="inline-block ml-3 text-left"><summary class="text-xs text-gold cursor-pointer">★ Review</summary>
                            <form method="POST" action="{{ route('services.bookings.review', [$wedding, $b]) }}" class="mt-2 w-72 space-y-2">@csrf
                                @foreach(['overall' => 'Overall *'] + \App\Models\Marketplace\ProviderReview::ASPECTS as $field => $label)
                                    <label class="flex justify-between items-center text-xs text-muted">{{ $label }}
                                        <select name="{{ $field }}" class="lux-input w-20 py-1" @if($field === 'overall') required @endif>
                                            @if($field !== 'overall')<option value="">—</option>@endif
                                            @foreach([5, 4, 3, 2, 1] as $n)<option value="{{ $n }}">{{ $n }}★</option>@endforeach
                                        </select>
                                    </label>
                                @endforeach
                                <textarea name="body" rows="3" placeholder="How was the service?" class="lux-input"></textarea>
                                <button class="btn-gold text-xs">Publish review</button>
                            </form>
                        </details>
                    @elseif($b->review)
                        <span class="text-xs text-gold ml-3">{{ $b->review->overall }}★ reviewed</span>
                    @endif
                    @if($b->status === 'confirmed')
                        <details class="inline-block ml-3 text-left"><summary class="text-xs text-muted cursor-pointer">Cancel</summary>
                            <form method="POST" action="{{ route('services.bookings.cancel', [$wedding, $b]) }}" class="mt-2 w-64 space-y-2">@csrf
                                @if($b->terms_snapshot['package']['cancellation_policy'] ?? null)<p class="text-xs text-muted">Policy: {{ $b->terms_snapshot['package']['cancellation_policy'] }}</p>@endif
                                <input name="reason" required placeholder="Reason" class="lux-input">
                                <button class="text-xs text-red-500">Cancel booking</button>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td class="px-4 py-8 text-center text-muted">No bookings yet. Accept a quotation to confirm one.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<form method="GET" action="{{ route('services.compare', $wedding) }}">
<div class="flex items-center justify-between mb-3">
    <h2 class="eyebrow">Open requests</h2>
    @if($open->count() > 1)<button class="btn-ghost text-xs">Compare selected</button>@endif
</div>
<div class="card overflow-x-auto mb-8">
    <table class="w-full text-sm">
        <tbody>
        @forelse($open as $e)
            @php $latest = $e->versions->sortByDesc('version_no')->first(); @endphp
            <tr class="border-b border-line/50">
                <td class="px-3 py-3"><input type="checkbox" name="ids[]" value="{{ $e->id }}" aria-label="Compare {{ $e->provider->business_name }}"></td>
                <td class="px-3 py-3"><a href="{{ route('services.enquiry', [$wedding, $e]) }}" class="font-medium hover:text-gold">{{ $e->provider->business_name }}</a><p class="text-xs text-muted">{{ $e->service?->title }} · {{ $e->reference }}</p></td>
                <td class="px-3 py-3 text-xs">{{ $e->event_date->format('j M Y') }}</td>
                <td class="px-3 py-3">{{ $latest ? Money::format($latest->amount, $latest->currency) : '—' }}</td>
                <td class="px-3 py-3">@include('marketplace._status', ['status' => $e->status])</td>
            </tr>
        @empty
            <tr><td class="px-4 py-8 text-center text-muted">No open requests. <a href="{{ route('marketplace.index') }}" class="underline">Browse the marketplace</a>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</form>

@if($closed->isNotEmpty())
    <details class="mb-8"><summary class="eyebrow cursor-pointer mb-3">Closed requests ({{ $closed->count() }})</summary>
        <div class="card divide-y divide-line">
            @foreach($closed as $e)
                <a href="{{ route('services.enquiry', [$wedding, $e]) }}" class="flex justify-between px-4 py-3 text-sm hover:bg-canvas"><span>{{ $e->provider->business_name }} · {{ $e->service?->title }}</span>@include('marketplace._status', ['status' => $e->status])</a>
            @endforeach
        </div>
    </details>
@endif

@if($favourites->isNotEmpty())
    <h2 class="eyebrow mb-3">Your saved providers</h2>
    <div class="flex flex-wrap gap-2">
        @foreach($favourites as $p)<a href="{{ route('marketplace.provider', $p) }}" class="btn-ghost text-xs">♥ {{ $p->business_name }}</a>@endforeach
    </div>
@endif
@endsection
