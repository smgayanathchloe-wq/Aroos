@extends('layouts.app')

@section('title', 'Seating')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Room</p>
        <h1 class="font-serif text-3xl mt-1">Seating</h1>
    </div>
    <form method="POST" action="{{ route('seating.store', $wedding) }}" class="flex flex-wrap gap-2">
        @csrf
        <input name="name" placeholder="Table name" required class="lux-input w-36">
        <input type="number" name="capacity" value="8" min="1" title="Capacity" class="lux-input w-20">
        <select name="shape" class="lux-input w-32">
            <option value="round">Round</option>
            <option value="rectangular">Rectangular</option>
            <option value="square">Square</option>
        </select>
        <input type="number" name="quantity" value="1" min="1" title="Quantity (auto-numbered)" class="lux-input w-20">
        <button class="btn-gold">Add table</button>
    </form>
</div>

@php
    $seatsTotal = $tables->sum('capacity');
    $seatsUsed = $tables->sum(fn ($t) => $t->seatsUsed());
@endphp
<div class="grid grid-cols-3 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Tables</p><p class="font-serif text-2xl mt-2">{{ $tables->count() }}</p></div>
    <div class="card p-5"><p class="eyebrow">Seats filled</p><p class="font-serif text-2xl mt-2 text-gold">{{ $seatsUsed }}/{{ $seatsTotal }}</p></div>
    <div class="card p-5"><p class="eyebrow">Unseated parties</p><p class="font-serif text-2xl mt-2">{{ $unseated->count() }}</p></div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 grid sm:grid-cols-2 gap-4">
        @forelse($tables as $table)
            <div class="card p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-6 h-6 border border-gold/60 {{ $table->shape === 'round' ? 'rounded-full' : ($table->shape === 'square' ? 'rounded' : 'rounded w-9') }}"></span>
                        <h3 class="font-serif">{{ $table->name }}</h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-muted">
                        <span>{{ $table->seatsUsed() }}/{{ $table->capacity }}</span>
                        <form method="POST" action="{{ route('seating.destroy', [$wedding, $table]) }}" onsubmit="return confirm('Delete this table?')">
                            @csrf @method('DELETE')
                            <button class="hover:text-red-400">×</button>
                        </form>
                    </div>
                </div>
                <ul class="space-y-1 mb-3">
                    @foreach($table->guests as $g)
                        <li class="flex items-center justify-between text-sm">
                            <span>{{ $g->fullName() }} @if($g->party_count > 1)<span class="text-xs text-muted">×{{ $g->party_count }}</span>@endif</span>
                            <form method="POST" action="{{ route('seating.assign', $wedding) }}">
                                @csrf
                                <input type="hidden" name="guest_id" value="{{ $g->id }}">
                                <input type="hidden" name="seating_table_id" value="">
                                <button class="text-xs text-muted hover:text-red-400" title="Unseat">×</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                @if($table->seatsUsed() < $table->capacity && $unseated->count())
                    <form method="POST" action="{{ route('seating.assign', $wedding) }}" class="flex gap-2">
                        @csrf
                        <input type="hidden" name="seating_table_id" value="{{ $table->id }}">
                        <select name="guest_id" class="lux-input flex-1 text-xs">
                            @foreach($unseated as $g)
                                <option value="{{ $g->id }}">{{ $g->fullName() }} ({{ $g->party_count }})</option>
                            @endforeach
                        </select>
                        <button class="btn-ghost text-xs">Seat</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="card p-10 text-center text-muted sm:col-span-2">
                <p class="font-serif text-xl mb-2">No tables yet</p>
                <p class="text-sm">Add your first table to start seating guests.</p>
            </div>
        @endforelse
    </div>

    <div class="card p-5 h-fit">
        <p class="eyebrow mb-3">Unseated guests</p>
        <ul class="space-y-2">
            @forelse($unseated as $g)
                <li class="text-sm flex justify-between">
                    <span>{{ $g->fullName() }}</span>
                    <span class="text-xs text-muted">×{{ $g->party_count }}</span>
                </li>
            @empty
                <li class="text-sm text-muted">Everyone is seated. ✓</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
