@extends('layouts.app')

@section('title', 'Request '.$enquiry->reference)

@use('App\Marketplace\Money')

@section('content')
@include('marketplace.provider._nav')
<a href="{{ route('provider.enquiries') }}" class="text-xs text-muted">← Requests</a>
<div class="flex flex-wrap items-start justify-between gap-4 mt-2 mb-6">
    <div>
        <h2 class="font-serif text-2xl">{{ $enquiry->event_name ?: 'Wedding request' }}</h2>
        <p class="text-sm text-muted">{{ $enquiry->service?->title }}{{ $enquiry->package ? ' · '.$enquiry->package->name : '' }} · {{ $enquiry->reference }}</p>
    </div>
    @include('marketplace._status', ['status' => $enquiry->status])
</div>

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    <div class="space-y-6 min-w-0">
        @if($conflict)
            <div class="px-4 py-3 rounded-lg text-sm" style="background: rgba(220,160,60,.1); border: 1px solid rgba(220,160,60,.4); color: #8a5a10;">⚠ {{ $conflict }} You can propose a different date in your quotation.</div>
        @endif
        <div class="card p-5">
            <p class="eyebrow mb-4">Conversation</p>
            @include('marketplace._thread', ['enquiry' => $enquiry, 'side' => 'provider'])
        </div>

        @if($enquiry->isOpen() && $canRespond)
            <form method="POST" action="{{ route('provider.enquiries.quote', $enquiry) }}" class="card p-5 grid gap-3 md:grid-cols-2">@csrf
                <p class="eyebrow md:col-span-2">Send a quotation{{ $enquiry->versions->isNotEmpty() ? ' / counter-offer' : '' }}</p>
                <label class="text-xs text-muted">Amount ({{ config('marketplace.currency') }}) *<input type="number" name="amount" min="1" required value="{{ old('amount', $enquiry->package?->price) }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted">Package
                    <select name="package_id" class="lux-input mt-1"><option value="">Custom quotation</option>
                        @foreach($enquiry->service?->activePackages ?? [] as $p)<option value="{{ $p->id }}" @selected(old('package_id', $enquiry->package_id) == $p->id)>{{ $p->name }}{{ $p->price !== null ? ' · '.Money::format($p->price) : '' }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs text-muted">Propose another date<input type="date" name="proposed_date" min="{{ today()->toDateString() }}" value="{{ old('proposed_date') }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted">Valid for (days)<input type="number" name="valid_days" min="1" max="60" value="{{ old('valid_days', config('marketplace.quote_validity_days')) }}" class="lux-input mt-1"></label>
                <label class="text-xs text-muted md:col-span-2">Message<textarea name="message" rows="3" class="lux-input mt-1">{{ old('message') }}</textarea></label>
                <label class="text-xs text-muted md:col-span-2">Terms & conditions<textarea name="terms" rows="2" class="lux-input mt-1">{{ old('terms', $enquiry->package?->terms) }}</textarea></label>
                <button class="btn-gold md:col-span-2">Send quotation</button>
            </form>
            <form method="POST" action="{{ route('provider.enquiries.message', $enquiry) }}" class="card p-5 space-y-3">@csrf
                <textarea name="body" rows="3" required placeholder="Write to {{ $enquiry->contact_name }}…" class="lux-input" aria-label="Message"></textarea>
                <div class="flex flex-wrap gap-2 items-center">
                    <select name="kind" class="lux-input w-auto" aria-label="Message type">
                        <option value="message">Message</option><option value="info_request">Ask for more information</option><option value="meeting_request">Request a meeting</option>
                    </select>
                    <button class="btn-ghost">Send</button>
                </div>
            </form>
        @endif
    </div>

    <aside class="space-y-4">
        <div class="card p-5 text-sm space-y-1">
            <p class="eyebrow mb-2">Couple / planner</p>
            <p class="font-medium">{{ $enquiry->contact_name }}</p>
            @if($enquiry->contact_phone)<p><a href="tel:{{ preg_replace('/[^+0-9]/', '', $enquiry->contact_phone) }}" class="underline">{{ $enquiry->contact_phone }}</a></p>@endif
            @if($enquiry->contact_email)<p>{{ $enquiry->contact_email }}</p>@endif
            <p class="text-xs text-muted">Prefers: {{ ['aroos' => 'Aroos messages', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email'][$enquiry->preferred_contact] ?? $enquiry->preferred_contact }}</p>
        </div>
        <div class="card p-5 text-sm space-y-1">
            <p class="eyebrow mb-2">Event</p>
            <p>📅 {{ $enquiry->event_date->format('D j M Y') }}{{ $enquiry->event_start ? ' · '.substr($enquiry->event_start, 0, 5).($enquiry->event_end ? '–'.substr($enquiry->event_end, 0, 5) : '') : '' }}</p>
            @if($enquiry->venue)<p>📍 {{ $enquiry->venue }}{{ $enquiry->region ? ', '.$enquiry->region->name : '' }}</p>@endif
            @if($enquiry->guests)<p>👥 {{ number_format($enquiry->guests) }} guests</p>@endif
            @if($enquiry->budget)<p>💰 Budget {{ Money::format($enquiry->budget) }}</p>@endif
            @if($enquiry->requirements)<p class="text-muted whitespace-pre-line pt-2">{{ $enquiry->requirements }}</p>@endif
        </div>
        @if($enquiry->booking)
            <div class="card p-5 text-sm"><p class="eyebrow mb-1">Booked</p><p>{{ $enquiry->booking->reference }} · {{ Money::format($enquiry->booking->agreed_amount) }}</p><a href="{{ route('provider.bookings') }}" class="text-xs underline">Bookings</a></div>
        @endif
        @if($enquiry->isOpen() && $canRespond)
            <details class="card p-5"><summary class="text-sm text-muted cursor-pointer">Decline this request</summary>
                <form method="POST" action="{{ route('provider.enquiries.decline', $enquiry) }}" class="mt-3 space-y-2">@csrf
                    <input name="reason" required placeholder="Reason (shared with the couple)" class="lux-input">
                    <button class="text-sm text-red-500">Decline</button>
                </form>
            </details>
        @endif
    </aside>
</div>
@endsection
