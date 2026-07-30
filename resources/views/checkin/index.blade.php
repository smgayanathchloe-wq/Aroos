@extends('layouts.app')

@section('title', 'Check-in & QR')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Arrival day</p>
        <h1 class="font-serif text-3xl mt-1 text-inktext">Check-in & QR Cards</h1>
        <p class="text-sm text-muted mt-2 max-w-2xl">Print or share each guest's QR card. At the door, an usher scans the card — the guest is checked in instantly and marked as inside the hall.</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('checkin.cards', $wedding) }}" class="btn-gold">Print all cards</a>
        <a href="{{ route('checkin.print', [$wedding, 'filter' => 'all']) }}" class="btn-ghost">Print guest list</a>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Invited parties</p><p class="font-serif text-3xl mt-2 text-inktext">{{ $stats['total_parties'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Total people</p><p class="font-serif text-3xl mt-2 text-inktext">{{ $stats['total_people'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Expected (attending)</p><p class="font-serif text-3xl mt-2 text-inktext">{{ $stats['attending'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Arrived (parties)</p><p class="font-serif text-3xl mt-2 text-gold">{{ $stats['checked_in_parties'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Arrived (people)</p><p class="font-serif text-3xl mt-2 text-gold">{{ $stats['checked_in_people'] }}</p></div>
</div>

@php $pct = $stats['total_parties'] ? round($stats['checked_in_parties'] / $stats['total_parties'] * 100) : 0; @endphp
<div class="card p-4 mb-8 flex items-center gap-4">
    <div class="flex-1 h-2.5 rounded-full overflow-hidden" style="background:#F0E9DC;">
        <div class="h-full gold-gradient" style="width: {{ $pct }}%"></div>
    </div>
    <span class="text-sm text-muted">{{ $pct }}% arrived</span>
</div>

<div class="flex gap-2 mb-4 no-print">
    <a href="{{ route('checkin.print', [$wedding, 'filter' => 'attended']) }}" class="btn-ghost text-xs">Print attended</a>
    <a href="{{ route('checkin.print', [$wedding, 'filter' => 'not_attended']) }}" class="btn-ghost text-xs">Print not arrived</a>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs text-muted" style="border-color:#E8E2D9;">
                <th class="px-4 py-3">Guest</th>
                <th class="px-4 py-3">Party</th>
                <th class="px-4 py-3">Table / Seats</th>
                <th class="px-4 py-3">RSVP</th>
                <th class="px-4 py-3">Arrival</th>
                <th class="px-4 py-3">QR Card</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($guests as $guest)
                <tr class="border-b hover:bg-canvas" style="border-color:#F0E9DC;">
                    <td class="px-4 py-3 text-inktext">{{ $guest->fullName() }}</td>
                    <td class="px-4 py-3">{{ $guest->party_count }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $guest->seatingTable?->name ?? '—' }}{{ $guest->seat_numbers ? ' · Seat '.$guest->seat_numbers : '' }}</td>
                    <td class="px-4 py-3"><span class="text-xs">{{ ucfirst($guest->rsvp_status) }}</span></td>
                    <td class="px-4 py-3">
                        @if($guest->isCheckedIn())
                            <span class="text-xs px-2.5 py-1 rounded-full" style="background:rgba(90,150,90,.12); color:#4a7a4a;">✓ Inside · {{ $guest->checked_in_at->format('H:i') }} · {{ $guest->checked_in_count }} pax</span>
                        @else
                            <span class="text-xs px-2.5 py-1 rounded-full" style="background:#F0E9DC; color:#8A7E6F;">Not arrived</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('checkin.card', [$wedding, $guest]) }}" target="_blank" class="text-xs text-gold hover:underline">View card</a>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($guest->isCheckedIn())
                            <form method="POST" action="{{ route('checkin.undo', [$wedding, $guest]) }}">
                                @csrf
                                <button class="text-xs text-muted hover:text-red-400">Undo</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('checkin.manual', [$wedding, $guest]) }}">
                                @csrf
                                <button class="text-xs text-gold hover:underline">Check in manually</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
