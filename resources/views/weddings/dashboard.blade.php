@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Planning Dashboard</p>
        <h1 class="font-serif text-4xl mt-1">{{ $wedding->coupleNames() }}</h1>
        <p class="text-muted text-sm mt-2">
            {{ optional($wedding->wedding_date)->format('F j, Y') ?? 'Set a wedding date' }}
            {{ $wedding->venue_name ? ' · '.$wedding->venue_name : '' }}
            {{ $wedding->venue_city ? ' · '.$wedding->venue_city : '' }}
        </p>
    </div>
    @if($stats['days_to_go'] !== null && $stats['days_to_go'] >= 0)
        <div class="text-right">
            <p class="font-serif text-5xl text-gold">{{ $stats['days_to_go'] }}</p>
            <p class="eyebrow">days to “I do”</p>
        </div>
    @endif
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <div class="card p-5">
        <p class="eyebrow">Guests</p>
        <p class="font-serif text-3xl mt-2">{{ $stats['guest_count'] }}</p>
        <p class="text-xs text-muted mt-1">{{ $stats['rsvp_attending'] }} attending · {{ $stats['rsvp_received'] }} RSVPs in</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Budget spent</p>
        <p class="font-serif text-3xl mt-2">{{ number_format($stats['spent']) }}</p>
        <p class="text-xs text-muted mt-1">of {{ number_format($stats['budgeted']) }} planned · {{ $wedding->currency }}</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Checklist</p>
        <p class="font-serif text-3xl mt-2">{{ $stats['tasks_done'] }}<span class="text-lg text-muted">/{{ $stats['tasks_total'] }}</span></p>
        <p class="text-xs text-muted mt-1">tasks completed</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Pledges</p>
        <p class="font-serif text-3xl mt-2">{{ number_format($stats['contributed']) }}</p>
        <p class="text-xs text-muted mt-1">collected of {{ number_format($stats['pledged']) }} pledged</p>
    </div>
</div>

<p class="eyebrow mb-4">Plan every detail</p>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-12">
    @foreach([
        ['checklist.index', 'Checklist', 'Tasks & phases'],
        ['budget.index', 'Budget', 'Categories & spend'],
        ['guests.index', 'Guests', 'List & RSVPs'],
        ['seating.index', 'Seating', 'Tables & chairs'],
        ['vendors.index', 'Vendors', 'Pipeline & contracts'],
        ['crew.index', 'Wedding Crew', 'Teams & helpers'],
        ['timeline.index', 'Timeline', 'Day-of schedule'],
        ['menus.index', 'Menus', 'Food & drink'],
        ['inspiration.index', 'Inspiration', 'Mood board'],
        ['photos.index', 'Photos', 'Gallery'],
        ['memories.index', 'Memories', 'Guestbook'],
        ['pledges.index', 'Pledges & Gifts', 'Contributions'],
        ['reminders.index', 'Reminders', 'Email & SMS'],
        ['collaborators.index', 'Collaborators', 'Roles & access'],
        ['website.edit', 'Website', 'Public site & invites'],
    ] as [$route, $label, $hint])
        <a href="{{ route($route, $wedding) }}" class="card p-5 hover:border-gold transition group">
            <h3 class="font-serif text-lg group-hover:text-goldlight">{{ $label }}</h3>
            <p class="text-xs text-muted mt-1">{{ $hint }}</p>
        </a>
    @endforeach
</div>

<details class="card p-6">
    <summary class="cursor-pointer font-serif text-xl">Wedding details <span class="text-xs text-muted font-sans ml-2">edit your basics</span></summary>
    <form method="POST" action="{{ route('wedding.update', $wedding) }}" class="mt-6 grid md:grid-cols-2 gap-4">
        @csrf @method('PUT')
        <div><label class="text-xs text-muted block mb-1.5">Partner A</label><input name="partner_a" value="{{ $wedding->partner_a }}" required class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">Partner B</label><input name="partner_b" value="{{ $wedding->partner_b }}" required class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">Wedding date</label><input type="date" name="wedding_date" value="{{ optional($wedding->wedding_date)->format('Y-m-d') }}" class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">Time</label><input type="time" name="wedding_time" value="{{ $wedding->wedding_time }}" class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">Venue</label><input name="venue_name" value="{{ $wedding->venue_name }}" class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">City</label><input name="venue_city" value="{{ $wedding->venue_city }}" class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">Country</label><input name="venue_country" value="{{ $wedding->venue_country }}" class="lux-input"></div>
        <div><label class="text-xs text-muted block mb-1.5">Currency</label>
            <select name="currency" class="lux-input">
                @foreach(['TZS','KES','USD','EUR','GBP','NGN','ZAR','UGX','RWF'] as $c)
                    <option value="{{ $c }}" @selected($wedding->currency === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2"><label class="text-xs text-muted block mb-1.5">Our story (shown on website)</label><textarea name="story" rows="4" class="lux-input">{{ $wedding->story }}</textarea></div>
        <div><label class="text-xs text-muted block mb-1.5">Total budget</label><input type="number" step="0.01" name="total_budget" value="{{ $wedding->total_budget }}" class="lux-input"></div>
        <div class="flex items-end"><button class="btn-gold">Save changes</button></div>
    </form>
</details>
@endsection
