@extends('layouts.app')

@section('title', 'Guests')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Roster</p>
        <h1 class="font-serif text-3xl mt-1">Guests</h1>
    </div>
    <form method="GET" class="flex gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Search guests…" class="lux-input w-48">
        <select name="status" class="lux-input w-36" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach(['pending' => 'Pending', 'attending' => 'Attending', 'declined' => 'Declined', 'maybe' => 'Maybe'] as $v => $l)
                <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="btn-ghost">Search</button>
    </form>
</div>

@php
    $attending = $guests->where('rsvp_status', 'attending')->sum('party_count');
    $totalPeople = $guests->sum('party_count');
@endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Invited</p><p class="font-serif text-2xl mt-2">{{ $guests->count() }} <span class="text-sm text-muted">parties</span></p></div>
    <div class="card p-5"><p class="eyebrow">Total people</p><p class="font-serif text-2xl mt-2">{{ $totalPeople }}</p></div>
    <div class="card p-5"><p class="eyebrow">Attending</p><p class="font-serif text-2xl mt-2 text-gold">{{ $attending }}</p></div>
    <div class="card p-5"><p class="eyebrow">Pending</p><p class="font-serif text-2xl mt-2">{{ $guests->where('rsvp_status', 'pending')->count() }}</p></div>
</div>

<details class="card p-6 mb-8" {{ $errors->any() ? 'open' : '' }}>
    <summary class="cursor-pointer font-serif text-lg">+ Add Guest</summary>
    <form method="POST" action="{{ route('guests.store', $wedding) }}" class="grid md:grid-cols-4 gap-3 mt-4">
        @csrf
        <input name="first_name" placeholder="First name *" required class="lux-input">
        <input name="last_name" placeholder="Last name" class="lux-input">
        <input type="email" name="email" placeholder="Email" class="lux-input">
        <input name="phone" placeholder="Phone (+255…)" class="lux-input">
        <input type="number" name="party_count" value="1" min="1" title="Party size" class="lux-input">
        <select name="side" class="lux-input">
            <option value="both">Side: Both</option>
            <option value="partner_a">Side: {{ $wedding->partner_a }}</option>
            <option value="partner_b">Side: {{ $wedding->partner_b }}</option>
        </select>
        <input name="group_label" placeholder="Group (family, friends…)" class="lux-input">
        <select name="rsvp_status" class="lux-input">
            <option value="pending">RSVP: Pending</option>
            <option value="attending">RSVP: Attending</option>
            <option value="maybe">RSVP: Maybe</option>
            <option value="declined">RSVP: Declined</option>
        </select>
        <button class="btn-gold md:col-span-4 justify-self-start">Add Guest</button>
    </form>
</details>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3">Party</th>
                <th class="px-4 py-3">RSVP</th>
                <th class="px-4 py-3">Table</th>
                <th class="px-4 py-3">Invite link</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($guests as $guest)
                <tr class="border-b border-line/50 hover:bg-ink/40">
                    <td class="px-4 py-3">
                        <p>{{ $guest->fullName() }}</p>
                        @if($guest->group_label)<p class="text-xs text-muted">{{ $guest->group_label }}</p>@endif
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">
                        {{ $guest->email }}<br>{{ $guest->phone }}
                    </td>
                    <td class="px-4 py-3">{{ $guest->party_count }}</td>
                    <td class="px-4 py-3">
                        @php $colors = ['pending' => 'text-muted border-line', 'attending' => 'text-gold border-gold/50', 'declined' => 'text-red-400 border-red-400/50', 'maybe' => 'text-cream border-line']; @endphp
                        <span class="text-xs border rounded-full px-2.5 py-1 {{ $colors[$guest->rsvp_status] }}">{{ ucfirst($guest->rsvp_status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $guest->seatingTable?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2 text-xs">
                            <a href="{{ route('public.rsvp', [$wedding->slug, $guest->invite_token]) }}" target="_blank" class="text-gold hover:underline">RSVP</a>
                            <a href="{{ route('public.invitation', [$wedding->slug, $guest->invite_token]) }}" target="_blank" class="text-gold hover:underline">Card</a>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <details class="relative">
                            <summary class="cursor-pointer text-muted text-xs list-none hover:text-gold">Edit ▾</summary>
                            <div class="absolute right-0 z-40 mt-2 w-80 card p-4 shadow-2xl">
                                <form method="POST" action="{{ route('guests.update', [$wedding, $guest]) }}" class="grid grid-cols-2 gap-2">
                                    @csrf @method('PUT')
                                    <input name="first_name" value="{{ $guest->first_name }}" required class="lux-input">
                                    <input name="last_name" value="{{ $guest->last_name }}" class="lux-input">
                                    <input type="email" name="email" value="{{ $guest->email }}" placeholder="Email" class="lux-input">
                                    <input name="phone" value="{{ $guest->phone }}" placeholder="Phone" class="lux-input">
                                    <input type="number" name="party_count" value="{{ $guest->party_count }}" min="1" class="lux-input">
                                    <select name="side" class="lux-input">
                                        <option value="both" @selected($guest->side === 'both')>Both</option>
                                        <option value="partner_a" @selected($guest->side === 'partner_a')>{{ $wedding->partner_a }}</option>
                                        <option value="partner_b" @selected($guest->side === 'partner_b')>{{ $wedding->partner_b }}</option>
                                    </select>
                                    <input name="group_label" value="{{ $guest->group_label }}" placeholder="Group" class="lux-input">
                                    <select name="rsvp_status" class="lux-input">
                                        @foreach(['pending', 'attending', 'maybe', 'declined'] as $s)
                                            <option value="{{ $s }}" @selected($guest->rsvp_status === $s)>{{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select>
                                    <input name="meal_preference" value="{{ $guest->meal_preference }}" placeholder="Meal preference" class="lux-input col-span-2">
                                    <button class="btn-gold col-span-2">Save</button>
                                </form>
                                <form method="POST" action="{{ route('guests.destroy', [$wedding, $guest]) }}" onsubmit="return confirm('Remove this guest?')" class="mt-2">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-400 hover:underline">Remove guest</button>
                                </form>
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-muted text-sm">No guests yet. Add your first above.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
