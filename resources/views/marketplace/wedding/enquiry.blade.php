@extends('layouts.app')

@section('title', 'Request '.$enquiry->reference)

@use('App\Marketplace\Money')
@php
    $latest = $enquiry->versions->sortByDesc('version_no')->first();
    $canAccept = $enquiry->isOpen() && $latest && $latest->author_side === 'provider' && $latest->status === 'pending' && ! $latest->isExpired();
@endphp

@section('content')
<a href="{{ route('services.index', $wedding) }}" class="text-xs text-muted">← Service providers</a>
<div class="flex flex-wrap items-start justify-between gap-4 mt-2 mb-6">
    <div>
        <h1 class="font-serif text-3xl"><a href="{{ route('marketplace.provider', $enquiry->provider) }}" class="hover:text-gold">{{ $enquiry->provider->business_name }}</a></h1>
        <p class="text-sm text-muted">{{ $enquiry->service?->title }}{{ $enquiry->package ? ' · '.$enquiry->package->name : '' }} · {{ $enquiry->reference }}</p>
    </div>
    @include('marketplace._status', ['status' => $enquiry->status])
</div>

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    <div class="space-y-6 min-w-0">
        @if($enquiry->booking)
            <div class="card p-5 border-green-600/40">
                <p class="eyebrow mb-1">Booking {{ $enquiry->booking->reference }}</p>
                <p class="text-sm">@include('marketplace._status', ['status' => $enquiry->booking->status]) {{ $enquiry->booking->event_date->format('j M Y') }} · <strong>{{ Money::format($enquiry->booking->agreed_amount, $enquiry->booking->currency) }}</strong></p>
                <p class="text-xs text-muted mt-2">Added to this wedding's vendors and budget. Payment is arranged directly with the provider.</p>
            </div>
        @endif

        <div class="card p-5">
            <p class="eyebrow mb-4">Conversation</p>
            @include('marketplace._thread', ['enquiry' => $enquiry, 'side' => 'customer'])
        </div>

        @if($enquiry->isOpen())
            @if($canAccept)
                <div class="card p-5 grid md:grid-cols-2 gap-5">
                    <form method="POST" action="{{ route('services.accept', [$wedding, $enquiry]) }}" class="space-y-3">@csrf
                        <input type="hidden" name="version_id" value="{{ $latest->id }}">
                        <p class="font-medium">Accept {{ Money::format($latest->amount, $latest->currency) }}</p>
                        <label class="flex gap-2 text-xs text-muted"><input type="checkbox" name="confirm" value="1" required> I confirm this booking for {{ ($latest->proposed_date ?? $enquiry->event_date)->format('j M Y') }} at the agreed price and terms.</label>
                        <button class="btn-gold">Accept & book</button>
                    </form>
                    <form method="POST" action="{{ route('services.counter', [$wedding, $enquiry]) }}" class="space-y-3">@csrf
                        <p class="font-medium">Make a counter-offer</p>
                        <input type="number" name="amount" min="1" required placeholder="Your price ({{ $latest->currency }})" class="lux-input" aria-label="Counter amount">
                        <textarea name="message" rows="2" placeholder="Message (optional)" class="lux-input" aria-label="Message"></textarea>
                        <button class="btn-ghost">Send counter-offer</button>
                    </form>
                </div>
            @endif
            <form method="POST" action="{{ route('services.message', [$wedding, $enquiry]) }}" class="card p-5 space-y-3">@csrf
                <textarea name="body" rows="3" required placeholder="Write to {{ $enquiry->provider->business_name }}…" class="lux-input" aria-label="Message"></textarea>
                <button class="btn-ghost">Send message</button>
            </form>
        @endif
    </div>

    <aside class="space-y-4">
        <div class="card p-5 text-sm space-y-1">
            <p class="eyebrow mb-2">Your request</p>
            <p>📅 {{ $enquiry->event_date->format('j M Y') }}{{ $enquiry->event_start ? ' · '.substr($enquiry->event_start, 0, 5).($enquiry->event_end ? '–'.substr($enquiry->event_end, 0, 5) : '') : '' }}</p>
            @if($enquiry->venue)<p>📍 {{ $enquiry->venue }}</p>@endif
            @if($enquiry->guests)<p>👥 {{ number_format($enquiry->guests) }} guests</p>@endif
            @if($enquiry->budget)<p>💰 Budget {{ Money::format($enquiry->budget) }}</p>@endif
            @if($enquiry->requirements)<p class="text-muted whitespace-pre-line pt-2">{{ $enquiry->requirements }}</p>@endif
            <p class="text-xs text-muted pt-2">Sent by {{ $enquiry->customer->name }} · {{ $enquiry->created_at->format('j M Y') }}</p>
        </div>
        @if($enquiry->isOpen())
            <details class="card p-5"><summary class="text-sm text-muted cursor-pointer">Withdraw this request</summary>
                <form method="POST" action="{{ route('services.withdraw', [$wedding, $enquiry]) }}" class="mt-3 space-y-2">@csrf
                    <input name="reason" required placeholder="Reason" class="lux-input">
                    <button class="text-sm text-red-500">Withdraw</button>
                </form>
            </details>
        @endif
    </aside>
</div>
@endsection
