@extends('layouts.app')

@section('title', 'Timeline')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Hours</p>
        <h1 class="font-serif text-3xl mt-1">Day-of Timeline</h1>
        <p class="text-sm text-muted mt-2 max-w-xl">The hour-by-hour schedule for the wedding day — shared with vendors, the crew, and optionally on your public website.</p>
    </div>
</div>

<div class="card p-6 mb-8">
    <p class="eyebrow mb-4">Add event</p>
    <form method="POST" action="{{ route('timeline.store', $wedding) }}" class="grid md:grid-cols-6 gap-3">
        @csrf
        <input name="title" placeholder="Title *" required class="lux-input md:col-span-2">
        <input type="time" name="start_time" required class="lux-input">
        <input type="number" name="duration_minutes" value="30" min="5" step="5" title="Duration (min)" class="lux-input">
        <input name="location" placeholder="Location" class="lux-input">
        <label class="flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="public" value="1" class="accent-[#B89668]"> Public</label>
        <button class="btn-gold md:col-span-6 justify-self-start">Add Event</button>
    </form>
</div>

<div class="relative border-l border-gold/30 ml-3 space-y-6 pb-4">
    @forelse($events as $event)
        <div class="relative pl-8">
            <span class="absolute -left-[7px] top-1.5 w-3.5 h-3.5 rounded-full gold-gradient"></span>
            <div class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="text-gold text-sm">{{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }} · {{ $event->duration_minutes }} min</p>
                        <h3 class="font-serif text-lg mt-0.5">{{ $event->title }}</h3>
                        @if($event->location)<p class="text-xs text-muted mt-1">{{ $event->location }}</p>@endif
                        @if($event->notes)<p class="text-xs text-muted mt-1">{{ $event->notes }}</p>@endif
                    </div>
                    <div class="flex items-center gap-3">
                        @if($event->public)<span class="text-xs border border-gold/40 text-goldlight rounded-full px-2.5 py-1">On website</span>@endif
                        <form method="POST" action="{{ route('timeline.destroy', [$wedding, $event]) }}" onsubmit="return confirm('Remove this event?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-muted hover:text-red-400">Remove</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="pl-8 text-muted text-sm">No timeline events yet. Plan your wedding day!</div>
    @endforelse
</div>
@endsection
