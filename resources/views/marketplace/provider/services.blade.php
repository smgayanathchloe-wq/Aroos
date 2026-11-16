@extends('layouts.app')

@section('title', 'Services')

@use('App\Marketplace\Money')

@section('content')
@include('marketplace.provider._nav')

<div class="grid lg:grid-cols-[1fr_360px] gap-6">
    <div class="space-y-3">
        @forelse($services as $s)
            <a href="{{ route('provider.services.edit', $s) }}" class="card p-5 flex flex-wrap justify-between gap-3 hover:shadow-md transition">
                <div>
                    <p class="font-serif text-xl">{{ $s->title }}</p>
                    <p class="text-xs text-muted">{{ $s->category?->name }} · {{ \App\Models\Marketplace\ProviderService::PRICING[$s->pricing_type] }} · {{ $s->packages->where('status', 'active')->count() }} active package(s)</p>
                </div>
                <div class="text-right">
                    <span class="text-xs border rounded-full px-2.5 py-1 {{ $s->status === 'published' ? 'border-gold text-gold' : 'border-line text-muted' }}">{{ ucfirst($s->status) }}</span>
                    @if($s->startingPrice() !== null)<p class="text-sm mt-2">From {{ Money::format($s->startingPrice()) }}</p>@endif
                </div>
            </a>
        @empty
            <div class="card p-10 text-center text-muted">No services yet. Add your first one — you can publish without prices or photos and add them later.</div>
        @endforelse
    </div>
    <form method="POST" action="{{ route('provider.services.store') }}" class="card p-5 space-y-3 h-fit">@csrf
        <p class="eyebrow">Add a service</p>
        <input name="title" required maxlength="120" placeholder="e.g. Wedding photography" class="lux-input" aria-label="Title">
        <select name="category_id" required class="lux-input" aria-label="Category">@foreach($categories as $c)<option value="{{ $c->id }}" @selected($provider->primary_category_id === $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach</select>
        <select name="pricing_type" class="lux-input" aria-label="Pricing">@foreach(\App\Models\Marketplace\ProviderService::PRICING as $v => $l)<option value="{{ $v }}" @selected($v === 'quote_only')>{{ $l }}</option>@endforeach</select>
        <input type="hidden" name="scheduling" value="{{ $categories->firstWhere('id', $provider->primary_category_id)?->scheduling_default ?? 'per_day' }}">
        <input type="hidden" name="capacity_per_day" value="1">
        <input type="hidden" name="capacity_scope" value="service">
        <button class="btn-gold w-full">Create service</button>
    </form>
</div>
@endsection
