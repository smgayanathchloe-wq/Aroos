@extends('layouts.app')

@section('title', 'Vendors')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Suppliers</p>
        <h1 class="font-serif text-3xl mt-1">Vendors</h1>
    </div>
    <form method="GET" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search vendors…" class="lux-input w-44">
        <select name="status" class="lux-input w-40" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach(['researching' => 'Researching', 'contacted' => 'Contacted', 'meeting' => 'Meeting', 'quote' => 'Quote Received', 'booked' => 'Booked', 'rejected' => 'Rejected'] as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="btn-ghost">Go</button>
    </form>
</div>

@php
    $booked = $vendors->where('status', 'booked');
@endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Vendors</p><p class="font-serif text-2xl mt-2">{{ $vendors->count() }}</p></div>
    <div class="card p-5"><p class="eyebrow">Booked</p><p class="font-serif text-2xl mt-2 text-gold">{{ $booked->count() }}</p></div>
    <div class="card p-5"><p class="eyebrow">Booked cost</p><p class="font-serif text-2xl mt-2">{{ number_format($booked->sum('price_estimate')) }}</p></div>
    <div class="card p-5"><p class="eyebrow">Paid</p><p class="font-serif text-2xl mt-2">{{ number_format($vendors->sum('amount_paid')) }}</p></div>
</div>

<details class="card p-6 mb-8" {{ $errors->any() ? 'open' : '' }}>
    <summary class="cursor-pointer font-serif text-lg">+ Add Vendor</summary>
    <form method="POST" action="{{ route('vendors.store', $wedding) }}" class="grid md:grid-cols-4 gap-3 mt-4">
        @csrf
        <input name="category" placeholder="Category (Caterer, DJ…) *" required class="lux-input">
        <input name="name" placeholder="Business name *" required class="lux-input">
        <input type="email" name="email" placeholder="Email" class="lux-input">
        <input name="phone" placeholder="Phone" class="lux-input">
        <input name="website" placeholder="Website" class="lux-input">
        <input type="number" step="0.01" name="price_estimate" placeholder="Price estimate" class="lux-input">
        <select name="status" class="lux-input">
            @foreach(['researching', 'contacted', 'meeting', 'quote', 'booked', 'rejected'] as $s)
                <option value="{{ $s }}">{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="rating" class="lux-input">
            <option value="">No rating</option>
            @for($i = 5; $i >= 1; $i--)
                <option value="{{ $i }}">{{ str_repeat('★', $i) }}</option>
            @endfor
        </select>
        <button class="btn-gold md:col-span-4 justify-self-start">Add Vendor</button>
    </form>
</details>

<div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($vendors as $vendor)
        <div class="card p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="eyebrow">{{ $vendor->category }}</p>
                    <h3 class="font-serif text-lg mt-1">{{ $vendor->name }}</h3>
                </div>
                @php $sc = ['researching' => 'text-muted border-line', 'contacted' => 'text-cream border-line', 'meeting' => 'text-goldlight border-gold/40', 'quote' => 'text-goldlight border-gold/40', 'booked' => 'text-gold border-gold', 'rejected' => 'text-red-400 border-red-400/40']; @endphp
                <span class="text-xs border rounded-full px-2.5 py-1 {{ $sc[$vendor->status] }}">{{ ucfirst($vendor->status) }}</span>
            </div>
            <div class="text-xs text-muted mt-3 space-y-1">
                @if($vendor->rating)<p class="text-gold">{{ str_repeat('★', $vendor->rating) }}</p>@endif
                @if((float) $vendor->price_estimate > 0)<p>Est: {{ number_format((float) $vendor->price_estimate) }} {{ $wedding->currency }} · Paid: {{ number_format((float) $vendor->amount_paid) }}</p>@endif
                @if($vendor->email)<p>{{ $vendor->email }}</p>@endif
                @if($vendor->phone)<p>{{ $vendor->phone }}</p>@endif
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-xs text-muted hover:text-gold">Edit ▾</summary>
                <form method="POST" action="{{ route('vendors.update', [$wedding, $vendor]) }}" class="grid grid-cols-2 gap-2 mt-3">
                    @csrf @method('PUT')
                    <input name="category" value="{{ $vendor->category }}" required class="lux-input">
                    <input name="name" value="{{ $vendor->name }}" required class="lux-input">
                    <input type="email" name="email" value="{{ $vendor->email }}" placeholder="Email" class="lux-input">
                    <input name="phone" value="{{ $vendor->phone }}" placeholder="Phone" class="lux-input">
                    <input type="number" step="0.01" name="price_estimate" value="{{ $vendor->price_estimate }}" class="lux-input">
                    <input type="number" step="0.01" name="amount_paid" value="{{ $vendor->amount_paid }}" title="Amount paid" class="lux-input">
                    <select name="status" class="lux-input">
                        @foreach(['researching', 'contacted', 'meeting', 'quote', 'booked', 'rejected'] as $s)
                            <option value="{{ $s }}" @selected($vendor->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <select name="rating" class="lux-input">
                        <option value="">No rating</option>
                        @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" @selected($vendor->rating == $i)>{{ str_repeat('★', $i) }}</option>
                        @endfor
                    </select>
                    <button class="btn-gold col-span-2">Save</button>
                </form>
                <form method="POST" action="{{ route('vendors.destroy', [$wedding, $vendor]) }}" onsubmit="return confirm('Remove this vendor?')" class="mt-2">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:underline">Remove</button>
                </form>
            </details>
        </div>
    @empty
        <p class="text-muted text-sm md:col-span-3">No vendors yet.</p>
    @endforelse
</div>
@endsection
