@extends('layouts.app')

@section('title', 'Marketplace')

@section('content')
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="eyebrow">Aroos Marketplace</p>
        <h1 class="font-serif text-3xl mt-1">Find wedding service providers</h1>
        <p class="text-sm text-muted mt-2">Verified professionals only. Send a request from any provider's profile to get a quotation.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('marketplace.assistant') }}" class="btn-gold">✨ Plan my services</a>
        <a href="{{ route('marketplace.favourites') }}" class="btn-ghost">♥ Saved providers</a>
    </div>
</div>

<form method="GET" action="{{ route('marketplace.index') }}" class="card p-4 mb-8 grid gap-3 md:grid-cols-6" role="search">
    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Business or service name" class="lux-input md:col-span-2" aria-label="Search">
    <select name="category" class="lux-input" aria-label="Category">
        <option value="">All categories</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? null) == $c->id)>{{ $c->name }}</option>@endforeach
    </select>
    <select name="region" class="lux-input" aria-label="Region">
        <option value="">All regions</option>
        @foreach($regions as $r)<option value="{{ $r->id }}" @selected(($filters['region'] ?? null) == $r->id)>{{ $r->name }}</option>@endforeach
    </select>
    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="lux-input" aria-label="Event date" title="Hide providers unavailable on this date">
    <button class="btn-gold">Search</button>
    <details class="md:col-span-6" @if(collect($filters)->only(['min_price','max_price','pricing','has_packages','has_portfolio','multi_service','min_rating','sort'])->filter()->isNotEmpty()) open @endif>
        <summary class="text-xs text-muted cursor-pointer">More filters</summary>
        <div class="grid gap-3 md:grid-cols-6 mt-3 items-center">
            <input type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}" placeholder="Min price" class="lux-input" min="0" aria-label="Minimum price">
            <input type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}" placeholder="Max price" class="lux-input" min="0" aria-label="Maximum price">
            <select name="pricing" class="lux-input" aria-label="Pricing">
                <option value="">Any pricing</option>
                @foreach(\App\Models\Marketplace\ProviderService::PRICING as $v => $l)<option value="{{ $v }}" @selected(($filters['pricing'] ?? null) === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="min_rating" class="lux-input" aria-label="Minimum rating">
                <option value="">Any rating</option>
                @foreach([4, 3] as $r)<option value="{{ $r }}" @selected(($filters['min_rating'] ?? null) == $r)>{{ $r }}★ and up</option>@endforeach
            </select>
            <select name="sort" class="lux-input" aria-label="Sort">
                @foreach($sorts as $v => $l)<option value="{{ $v }}" @selected(($filters['sort'] ?? 'recommended') === $v)>{{ $l }}</option>@endforeach
            </select>
            <div class="md:col-span-2 flex flex-wrap gap-3 text-xs">
                <label><input type="checkbox" name="has_packages" value="1" @checked($filters['has_packages'] ?? false)> Has packages</label>
                <label><input type="checkbox" name="has_portfolio" value="1" @checked($filters['has_portfolio'] ?? false)> Has portfolio</label>
                <label><input type="checkbox" name="multi_service" value="1" @checked($filters['multi_service'] ?? false)> Several services</label>
            </div>
        </div>
    </details>
</form>

@if($searching)
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-muted">{{ $results->total() }} provider{{ $results->total() === 1 ? '' : 's' }} found · <a href="{{ route('marketplace.index') }}" class="underline">Clear</a></p>
    </div>
    @if($sponsored->isNotEmpty())
        <p class="text-[11px] uppercase tracking-wide text-muted mb-2">Sponsored — paid placement, not a ranking or endorsement</p>
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-8">
            @foreach($sponsored as $p)@include('marketplace._card', ['p' => $p, 'favourite' => in_array($p->id, $favouriteIds, true)])@endforeach
        </div>
    @endif
    @if($results->isEmpty())
        <div class="card p-10 text-center text-muted">No providers match yet. Try another region or fewer filters.</div>
    @else
        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($results as $p)
                @include('marketplace._card', ['p' => $p, 'favourite' => in_array($p->id, $favouriteIds, true)])
            @endforeach
        </div>
        <div class="mt-6">{{ $results->links() }}</div>
    @endif
@else
    <p class="eyebrow mb-4">Browse by category</p>
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($categories as $c)
            <a href="{{ route('marketplace.index', ['category' => $c->id]) }}" class="card overflow-hidden hover:shadow-md transition">
                @if($c->image_path)
                    <img src="{{ asset('storage/'.$c->image_path) }}" alt="" class="h-28 w-full object-cover" loading="lazy">
                @else
                    <div class="h-28 w-full flex items-center justify-center font-serif text-3xl text-white" style="background: linear-gradient(135deg, hsl({{ ($loop->index * 37) % 360 }}, 28%, 42%), #B08D57);" aria-hidden="true">{{ mb_substr($c->name, 0, 1) }}</div>
                @endif
                <div class="p-4">
                    <h2 class="font-serif text-lg leading-snug">{{ $c->name }}</h2>
                    <p class="text-xs text-muted mt-1">{{ $c->listed_count }} provider{{ $c->listed_count === 1 ? '' : 's' }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
