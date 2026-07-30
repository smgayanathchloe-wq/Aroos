@extends('layouts.app')

@section('title', 'My Weddings')

@section('content')
<div class="flex items-center justify-between mb-8">
    <div>
        <p class="eyebrow">Atelier</p>
        <h1 class="font-serif text-3xl mt-1">My Weddings</h1>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    @foreach($weddings as $w)
        <a href="{{ route('wedding.dashboard', $w) }}" class="card p-6 hover:border-gold transition group">
            <p class="eyebrow">{{ optional($w->wedding_date)->format('F j, Y') ?? 'Date TBA' }}</p>
            <h2 class="font-serif text-2xl mt-2 group-hover:text-goldlight">{{ $w->coupleNames() }}</h2>
            <p class="text-sm text-muted mt-2">{{ $w->venue_name ?? 'Venue TBA' }}{{ $w->venue_city ? ' · '.$w->venue_city : '' }}</p>
            @if($w->daysToGo() !== null && $w->daysToGo() >= 0)
                <p class="text-gold text-sm mt-4">{{ $w->daysToGo() }} days to go</p>
            @endif
        </a>
    @endforeach

    @if(auth()->user()->canCreateWeddings())
    <div class="card p-6 border-dashed">
        <p class="eyebrow mb-4">Create new wedding</p>
        <form method="POST" action="{{ route('weddings.store') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <input name="partner_a" required placeholder="Partner A name" class="lux-input" value="{{ old('partner_a') }}">
                <input name="partner_b" required placeholder="Partner B name" class="lux-input" value="{{ old('partner_b') }}">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <input type="date" name="wedding_date" class="lux-input" value="{{ old('wedding_date') }}">
                <select name="currency" class="lux-input">
                    @foreach(['TZS','KES','USD','EUR','GBP','NGN','ZAR','UGX','RWF'] as $c)
                        <option value="{{ $c }}" @selected(old('currency', 'TZS') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <input name="venue_name" placeholder="Venue (optional)" class="lux-input" value="{{ old('venue_name') }}">
            <div class="grid grid-cols-2 gap-3">
                <input name="venue_city" placeholder="City" class="lux-input" value="{{ old('venue_city') }}">
                <input type="number" step="0.01" name="total_budget" placeholder="Total budget" class="lux-input" value="{{ old('total_budget') }}">
            </div>
            <button class="btn-gold w-full">Create wedding</button>
        </form>
    </div>
    @endif
</div>
@endsection
