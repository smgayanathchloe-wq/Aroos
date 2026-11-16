@extends('layouts.app')

@section('title', 'Plan my services')

@use('App\Marketplace\Money')

@section('content')
<a href="{{ route('marketplace.index') }}" class="text-xs text-muted">← Marketplace</a>
<h1 class="font-serif text-3xl mt-2">Plan my services</h1>
<p class="text-sm text-muted mt-1 mb-6">Tell us what you need. We match you with verified providers on Aroos and show real price ranges from the marketplace. Nothing is sent until you review a request yourself.</p>

<form method="GET" action="{{ route('marketplace.assistant') }}" class="card p-5 mb-6 space-y-4">
    @if($aiEnabled)
        <label class="block text-xs text-muted">Describe your wedding needs (English or Kiswahili)
            <textarea name="description" rows="3" class="lux-input mt-1" placeholder="e.g. Harusi Dar es Salaam, wageni 300, bajeti ya picha TZS 1,000,000, tunahitaji picha na video">{{ $criteria['description'] ?? '' }}</textarea>
        </label>
        <p class="text-xs text-muted">✨ AI reads your description and fills the details below — check them before searching. Or skip it and choose yourself.</p>
    @endif
    <fieldset>
        <legend class="text-xs text-muted mb-1">Services you need</legend>
        <div class="flex flex-wrap gap-2">
            @foreach($categories as $c)
                <label class="text-xs border rounded-full px-3 py-1.5 cursor-pointer {{ in_array($c->id, $criteria['category_ids'], true) ? 'border-gold text-gold' : 'border-line' }}">
                    <input type="checkbox" name="category_ids[]" value="{{ $c->id }}" class="sr-only" @checked(in_array($c->id, $criteria['category_ids'], true)) onchange="this.parentElement.classList.toggle('border-gold');this.parentElement.classList.toggle('text-gold')"> {{ $c->name }}
                </label>
            @endforeach
        </div>
    </fieldset>
    <div class="grid md:grid-cols-4 gap-3">
        <select name="region_id" class="lux-input" aria-label="Region"><option value="">Region</option>@foreach($regions as $r)<option value="{{ $r->id }}" @selected(($criteria['region_id'] ?? null) == $r->id)>{{ $r->name }}</option>@endforeach</select>
        <input type="date" name="event_date" value="{{ $criteria['event_date'] ?? '' }}" min="{{ today()->toDateString() }}" class="lux-input" aria-label="Event date">
        <input type="number" name="guests" value="{{ $criteria['guests'] ?? '' }}" min="1" placeholder="Guests" class="lux-input" aria-label="Guests">
        <input type="number" name="budget" value="{{ $criteria['budget'] ?? '' }}" min="0" placeholder="Budget per service ({{ config('marketplace.currency') }})" class="lux-input" aria-label="Budget">
    </div>
    <button class="btn-gold">Find matches</button>
</form>

@if($aiNote)<p class="text-sm mb-4 px-4 py-3 rounded-lg" style="background: rgba(196,165,96,.08); border: 1px solid rgba(196,165,96,.3);">{{ $aiNote }}</p>@endif

@foreach($recommendations as $categoryId => $matches)
    @php $category = $categories->firstWhere('id', $categoryId); $range = $budgets->first(fn ($b) => $b['category']->id === $categoryId); @endphp
    <section class="mb-8">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="font-serif text-2xl">{{ $category?->name }}</h2>
            @if($range && $range['median'] !== null)
                <p class="text-xs text-muted">Estimate from {{ $range['samples'] }} marketplace prices: {{ Money::format($range['low']) }} – {{ Money::format($range['high']) }} (typical {{ Money::format($range['median']) }})</p>
            @elseif($range)
                <p class="text-xs text-muted">Not enough marketplace prices yet for an estimate.</p>
            @endif
        </div>
        @if($matches->isEmpty())
            <div class="card p-6 text-sm text-muted">No verified providers match yet. <a href="{{ route('marketplace.index', ['category' => $categoryId]) }}" class="underline">See all in this category</a>.</div>
        @else
            <div class="grid md:grid-cols-2 gap-4">
                @foreach($matches as $m)
                    <div class="card p-5">
                        <div class="flex justify-between gap-3">
                            <div>
                                <a href="{{ route('marketplace.provider', [$m['provider']] + $prefill) }}#request" class="font-serif text-xl hover:text-gold">{{ $m['provider']->business_name }}</a>
                                <p class="text-xs text-muted">{{ $m['provider']->region?->name }} · {{ $m['provider']->starting_price !== null ? 'from '.Money::format($m['provider']->starting_price) : 'price on request' }}</p>
                            </div>
                            @if($m['provider']->isSponsoredNow())<span class="text-[10px] uppercase tracking-wide text-muted">Sponsored</span>@endif
                        </div>
                        <p class="text-xs text-muted mt-3 mb-1">Why we suggest them</p>
                        <ul class="text-sm space-y-0.5">@foreach($m['reasons'] ?: ['Verified provider in this category'] as $reason)<li>• {{ $reason }}</li>@endforeach</ul>
                        <a href="{{ route('marketplace.provider', [$m['provider']] + $prefill) }}#request" class="btn-ghost text-xs mt-4">Review & send request</a>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endforeach

@if(! empty($prefill['draft']))
    <div class="card p-5">
        <p class="eyebrow mb-2">Your request draft</p>
        <p class="text-sm whitespace-pre-line">{{ $prefill['draft'] }}</p>
        <p class="text-xs text-muted mt-2">This text is filled into the request form on each provider's page. Edit it there before sending.</p>
    </div>
@endif
@endsection
