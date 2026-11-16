@extends('layouts.app')

@section('title', $service->title)

@use('App\Marketplace\Money')

@section('content')
@include('marketplace.provider._nav')
<a href="{{ route('provider.services') }}" class="text-xs text-muted">← All services</a>

<form method="POST" action="{{ route('provider.services.update', $service) }}" class="card p-6 grid gap-4 md:grid-cols-3 mt-3">
    @csrf @method('PUT')
    <label class="text-xs text-muted md:col-span-2">Title *<input name="title" value="{{ old('title', $service->title) }}" required class="lux-input mt-1"></label>
    <label class="text-xs text-muted">Status
        <select name="status" class="lux-input mt-1">@foreach(['draft' => 'Draft (hidden)', 'published' => 'Published'] as $v => $l)<option value="{{ $v }}" @selected(old('status', $service->status) === $v)>{{ $l }}</option>@endforeach</select>
    </label>
    <label class="text-xs text-muted">Category
        <select name="category_id" class="lux-input mt-1">@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $service->category_id) == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>@endforeach</select>
    </label>
    <label class="text-xs text-muted">Pricing
        <select name="pricing_type" class="lux-input mt-1">@foreach(\App\Models\Marketplace\ProviderService::PRICING as $v => $l)<option value="{{ $v }}" @selected(old('pricing_type', $service->pricing_type) === $v)>{{ $l }}</option>@endforeach</select>
    </label>
    <label class="text-xs text-muted">Base price ({{ config('marketplace.currency') }})<input type="number" min="0" name="base_price" value="{{ old('base_price', $service->base_price) }}" class="lux-input mt-1"></label>
    <label class="text-xs text-muted flex items-center gap-2 md:col-span-3"><input type="hidden" name="is_negotiable" value="0"><input type="checkbox" name="is_negotiable" value="1" @checked(old('is_negotiable', $service->is_negotiable))> Price is negotiable</label>
    <label class="text-xs text-muted md:col-span-3">Description<textarea name="description" rows="5" class="lux-input mt-1">{{ old('description', $service->description) }}</textarea></label>
    <label class="text-xs text-muted">Included (one per line)<textarea name="inclusions" rows="4" class="lux-input mt-1">{{ old('inclusions', implode("\n", $service->inclusions ?? [])) }}</textarea></label>
    <label class="text-xs text-muted">Not included (one per line)<textarea name="exclusions" rows="4" class="lux-input mt-1">{{ old('exclusions', implode("\n", $service->exclusions ?? [])) }}</textarea></label>
    <label class="text-xs text-muted">Requirements from the couple<textarea name="requirements" rows="4" class="lux-input mt-1">{{ old('requirements', $service->requirements) }}</textarea></label>
    <fieldset class="text-xs text-muted md:col-span-3">
        <legend>Regions you serve</legend>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-1 mt-1 border border-line rounded-lg p-2 max-h-40 overflow-y-auto">
            @foreach($regions as $r)<label class="flex gap-1.5 text-inktext"><input type="checkbox" name="coverage_regions[]" value="{{ $r->id }}" @checked(in_array($r->id, $service->coverage_regions ?? []))> {{ $r->name }}</label>@endforeach
        </div>
    </fieldset>
    <p class="eyebrow md:col-span-3 mt-2">Scheduling & capacity</p>
    <label class="text-xs text-muted">Booking type
        <select name="scheduling" class="lux-input mt-1">@foreach(['per_day' => 'Whole day (e.g. venue, catering)', 'per_slot' => 'Time slots (e.g. MC, makeup)'] as $v => $l)<option value="{{ $v }}" @selected(old('scheduling', $service->scheduling) === $v)>{{ $l }}</option>@endforeach</select>
    </label>
    <label class="text-xs text-muted">Events at the same time / per day<input type="number" name="capacity_per_day" min="1" max="100" value="{{ old('capacity_per_day', $service->capacity_per_day) }}" class="lux-input mt-1"></label>
    <label class="text-xs text-muted">Capacity shared across
        <select name="capacity_scope" class="lux-input mt-1">@foreach(['service' => 'This service only', 'provider' => 'All my services (one team)'] as $v => $l)<option value="{{ $v }}" @selected(old('capacity_scope', $service->capacity_scope) === $v)>{{ $l }}</option>@endforeach</select>
    </label>
    <label class="text-xs text-muted">Typical duration (minutes)<input type="number" name="duration_minutes" min="15" value="{{ old('duration_minutes', $service->duration_minutes) }}" class="lux-input mt-1"></label>
    <label class="text-xs text-muted">Buffer between events (minutes)<input type="number" name="buffer_minutes" min="0" value="{{ old('buffer_minutes', $service->buffer_minutes) }}" class="lux-input mt-1"></label>
    <label class="text-xs text-muted">Capacity note<input name="capacity_note" value="{{ old('capacity_note', $service->capacity_note) }}" placeholder="e.g. up to 800 guests" class="lux-input mt-1"></label>
    <div class="md:col-span-3 flex justify-between items-center">
        <button class="btn-gold">Save service</button>
    </div>
</form>
<form method="POST" action="{{ route('provider.services.archive', $service) }}" class="mt-2 text-right" onsubmit="return confirm('Archive this service? Past requests keep their history.')">@csrf @method('DELETE')<button class="text-xs text-muted hover:text-red-500">Archive service</button></form>

<h2 class="font-serif text-2xl mt-8 mb-3">Packages</h2>
<div class="grid lg:grid-cols-2 gap-4">
    @foreach($service->packages as $pkg)
        <form method="POST" action="{{ route('provider.packages.update', $pkg) }}" class="card p-5 space-y-2">@csrf @method('PUT')
            @include('marketplace.provider._package-fields', ['pkg' => $pkg])
            <div class="flex items-center gap-3">
                <select name="status" class="lux-input w-auto" aria-label="Package status">@foreach(['active' => 'Active', 'inactive' => 'Inactive'] as $v => $l)<option value="{{ $v }}" @selected($pkg->status === $v)>{{ $l }}</option>@endforeach</select>
                <button class="btn-gold">Save</button>
                <button formaction="{{ route('provider.packages.duplicate', $pkg) }}" name="_method" value="POST" class="text-xs text-muted hover:text-gold">Duplicate</button>
                <button formaction="{{ route('provider.packages.archive', $pkg) }}" name="_method" value="DELETE" class="text-xs text-muted hover:text-red-500" onclick="return confirm('Archive this package?')">Archive</button>
            </div>
        </form>
    @endforeach
    <form method="POST" action="{{ route('provider.packages.store', $service) }}" class="card p-5 space-y-2 border-dashed">@csrf
        <p class="eyebrow">New package</p>
        @include('marketplace.provider._package-fields', ['pkg' => null])
        <button class="btn-gold">Add package</button>
    </form>
</div>
@endsection
