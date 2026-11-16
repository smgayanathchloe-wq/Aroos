@extends('layouts.app')

@section('title', 'Business profile')

@section('content')
@include('marketplace.provider._nav')

<form method="POST" action="{{ route('provider.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')
    <div class="card p-6 grid gap-4 md:grid-cols-2">
        <p class="eyebrow md:col-span-2">Business information</p>
        <label class="text-xs text-muted">Business name *<input name="business_name" value="{{ old('business_name', $provider->business_name) }}" required class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Type
            <select name="business_type" class="lux-input mt-1">@foreach(['individual' => 'Individual', 'company' => 'Registered company'] as $v => $l)<option value="{{ $v }}" @selected(old('business_type', $provider->business_type) === $v)>{{ $l }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-muted md:col-span-2">Description
            <textarea name="description" rows="6" class="lux-input mt-1" placeholder="Who you are, your style, what makes your service special…">{{ old('description', $provider->description) }}</textarea>
        </label>
        <label class="text-xs text-muted">Main category *
            <select name="primary_category_id" class="lux-input mt-1">@foreach($categories->whereNull('parent_id') as $c)<option value="{{ $c->id }}" @selected(old('primary_category_id', $provider->primary_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
        </label>
        <fieldset class="text-xs text-muted">
            <legend>Other categories</legend>
            <div class="grid grid-cols-2 gap-1 mt-1 max-h-40 overflow-y-auto border border-line rounded-lg p-2">
                @foreach($categories as $c)
                    <label class="flex gap-1.5 text-inktext"><input type="checkbox" name="category_ids[]" value="{{ $c->id }}" @checked($provider->categories->contains('id', $c->id))> {{ $c->name }}</label>
                @endforeach
            </div>
        </fieldset>
        <label class="text-xs text-muted">Logo<input type="file" name="logo" accept="image/*" class="lux-input mt-1">@if($provider->logoUrl())<img src="{{ $provider->logoUrl() }}" alt="Current logo" class="w-12 h-12 rounded-full object-cover mt-2">@endif</label>
        <label class="text-xs text-muted">Cover photo<input type="file" name="cover" accept="image/*" class="lux-input mt-1">@if($provider->coverUrl())<img src="{{ $provider->coverUrl() }}" alt="Current cover" class="h-12 rounded object-cover mt-2">@endif</label>
    </div>

    <div class="card p-6 grid gap-4 md:grid-cols-3">
        <p class="eyebrow md:col-span-3">Contact</p>
        <label class="text-xs text-muted">Contact person<input name="contact_name" value="{{ old('contact_name', $provider->contact_name) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Phone *<input name="phone" value="{{ old('phone', $provider->phone) }}" required class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Alternative phone<input name="alt_phone" value="{{ old('alt_phone', $provider->alt_phone) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">WhatsApp<input name="whatsapp" value="{{ old('whatsapp', $provider->whatsapp) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Email<input type="email" name="email" value="{{ old('email', $provider->email) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Website<input name="website" value="{{ old('website', $provider->website) }}" placeholder="https://" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Preferred contact
            <select name="preferred_contact" class="lux-input mt-1">@foreach(['phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'aroos' => 'Aroos messages'] as $v => $l)<option value="{{ $v }}" @selected(old('preferred_contact', $provider->preferred_contact) === $v)>{{ $l }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-muted">Show phone & WhatsApp
            <select name="phone_visibility" class="lux-input mt-1">@foreach(['public' => 'To everyone browsing', 'after_enquiry' => 'Only after they send a request'] as $v => $l)<option value="{{ $v }}" @selected(old('phone_visibility', $provider->phone_visibility) === $v)>{{ $l }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-muted">Show email
            <select name="email_visibility" class="lux-input mt-1">@foreach(['public' => 'To everyone browsing', 'after_enquiry' => 'Only after they send a request'] as $v => $l)<option value="{{ $v }}" @selected(old('email_visibility', $provider->email_visibility) === $v)>{{ $l }}</option>@endforeach</select>
        </label>
    </div>

    <div class="card p-6 grid gap-4 md:grid-cols-3">
        <p class="eyebrow md:col-span-3">Location & hours</p>
        <label class="text-xs text-muted">Region *
            <select name="region_id" class="lux-input mt-1">@foreach($regions as $r)<option value="{{ $r->id }}" @selected(old('region_id', $provider->region_id) == $r->id)>{{ $r->name }}</option>@endforeach</select>
        </label>
        <label class="text-xs text-muted">District<input name="district" value="{{ old('district', $provider->district) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">City / town<input name="city" value="{{ old('city', $provider->city) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted md:col-span-3">Address<input name="address" value="{{ old('address', $provider->address) }}" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Latitude<input name="lat" value="{{ old('lat', $provider->lat) }}" placeholder="-6.7924" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Longitude<input name="lng" value="{{ old('lng', $provider->lng) }}" placeholder="39.2083" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Operating hours<input name="operating_hours" value="{{ old('operating_hours', $provider->operating_hours['text'] ?? '') }}" placeholder="Mon–Sat 8:00–18:00" class="lux-input mt-1"></label>
    </div>

    <div class="card p-6 grid gap-4 md:grid-cols-3">
        <p class="eyebrow md:col-span-3">Social media <span class="normal-case tracking-normal text-muted">— links only, we never ask for passwords</span></p>
        @foreach(array_keys(config('marketplace.social_hosts')) as $platform)
            <label class="text-xs text-muted">{{ $platform === 'x' ? 'X (Twitter)' : ucfirst($platform) }}<input name="social_links[{{ $platform }}]" value="{{ old("social_links.$platform", $provider->social_links[$platform] ?? '') }}" placeholder="https://{{ config("marketplace.social_hosts.$platform")[0] }}/…" class="lux-input mt-1"></label>
        @endforeach
    </div>

    <button class="btn-gold">Save profile</button>
</form>
@endsection
