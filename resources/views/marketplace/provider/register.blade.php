@extends('layouts.app')

@section('title', 'Register your business')

@section('content')
<div class="max-w-2xl">
    <p class="eyebrow">Aroos Marketplace</p>
    <h1 class="font-serif text-3xl mt-1">Register your wedding business</h1>
    <p class="text-sm text-muted mt-2 mb-6">Uses your Aroos account ({{ auth()->user()->email }}) — no new login needed. You can keep planning weddings with the same account.</p>
    <form method="POST" action="{{ route('provider.register.store') }}" class="card p-6 grid gap-4 md:grid-cols-2">
        @csrf
        <label class="text-xs text-muted md:col-span-2">Business or provider name *<input name="business_name" value="{{ old('business_name') }}" required maxlength="120" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Type *
            <select name="business_type" class="lux-input mt-1"><option value="individual">Individual</option><option value="company" @selected(old('business_type') === 'company')>Registered company</option></select>
        </label>
        <label class="text-xs text-muted">Main service *
            <select name="primary_category_id" required class="lux-input mt-1">
                <option value="">Choose…</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('primary_category_id') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs text-muted">Phone *<input name="phone" value="{{ old('phone', auth()->user()->phone) }}" required placeholder="+255 7xx xxx xxx" class="lux-input mt-1"></label>
        <label class="text-xs text-muted">Region *
            <select name="region_id" required class="lux-input mt-1">
                <option value="">Choose…</option>
                @foreach($regions as $r)<option value="{{ $r->id }}" @selected(old('region_id') == $r->id)>{{ $r->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-xs text-muted md:col-span-2">City / town<input name="city" value="{{ old('city') }}" class="lux-input mt-1"></label>
        <button class="btn-gold md:col-span-2">Create my business profile</button>
    </form>
</div>
@endsection
