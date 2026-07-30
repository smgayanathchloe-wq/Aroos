@extends('layouts.app')

@section('title', 'Report')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8 no-print">
    <div>
        <p class="eyebrow">The full picture</p>
        <h1 class="font-serif text-3xl mt-1 text-inktext">Wedding Report</h1>
    </div>
    <button onclick="window.print()" class="btn-gold">Print / Save as PDF</button>
</div>

<div class="card p-8 mb-6 text-center">
    <p class="font-script text-3xl text-gold">{{ $wedding->coupleNames() }}</p>
    <p class="text-sm text-muted mt-2">{{ optional($wedding->wedding_date)->format('F j, Y') ?? 'Date TBA' }} · {{ $wedding->venue_name }} {{ $wedding->venue_city ? '· '.$wedding->venue_city : '' }} · Currency: {{ $wedding->currency }}</p>
    <p class="text-xs text-muted mt-1">Report generated {{ now()->format('F j, Y H:i') }}</p>
</div>

{{-- Guests --}}
<div class="card p-6 mb-6">
    <h2 class="font-serif text-xl text-inktext mb-4">👥 Guests & Attendance</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 text-center">
        <div><p class="font-serif text-2xl text-inktext">{{ $report['guests']['parties'] }}</p><p class="text-xs text-muted">Parties invited</p></div>
        <div><p class="font-serif text-2xl text-inktext">{{ $report['guests']['people'] }}</p><p class="text-xs text-muted">Total people</p></div>
        <div><p class="font-serif text-2xl text-inktext">{{ $report['guests']['attending'] }}</p><p class="text-xs text-muted">Said attending</p></div>
        <div><p class="font-serif text-2xl text-inktext">{{ $report['guests']['declined'] }}</p><p class="text-xs text-muted">Declined</p></div>
        <div><p class="font-serif text-2xl text-inktext">{{ $report['guests']['pending'] }}</p><p class="text-xs text-muted">Never replied</p></div>
        <div><p class="font-serif text-2xl text-gold">{{ $report['guests']['checked_in'] }}</p><p class="text-xs text-muted">Parties arrived</p></div>
        <div><p class="font-serif text-2xl text-gold">{{ $report['guests']['checked_in_people'] }}</p><p class="text-xs text-muted">People arrived</p></div>
    </div>
</div>

{{-- Money --}}
<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">💰 Budget</h2>
        <table class="w-full text-sm">
            <tr><td class="py-1.5 text-muted">Total budget</td><td class="text-right text-inktext">{{ number_format($report['budget']['total']) }}</td></tr>
            <tr><td class="py-1.5 text-muted">Allocated to categories</td><td class="text-right text-inktext">{{ number_format($report['budget']['allocated']) }}</td></tr>
            <tr><td class="py-1.5 text-muted">Actually spent</td><td class="text-right text-inktext">{{ number_format($report['budget']['spent']) }}</td></tr>
            <tr class="border-t" style="border-color:#E8E2D9;"><td class="py-1.5 font-medium text-inktext">Remaining</td><td class="text-right font-medium {{ $report['budget']['total'] - $report['budget']['spent'] < 0 ? 'text-red-500' : 'text-gold' }}">{{ number_format($report['budget']['total'] - $report['budget']['spent']) }}</td></tr>
        </table>
        <div class="mt-4 space-y-2">
            @foreach($wedding->budgetCategories as $cat)
                @php $pct = (float) $cat->budgeted > 0 ? min(100, round((float) $cat->spent / (float) $cat->budgeted * 100)) : 0; @endphp
                <div class="flex items-center gap-3 text-xs">
                    <span class="w-32 truncate text-muted">{{ $cat->name }}</span>
                    <div class="flex-1 h-1.5 rounded-full overflow-hidden" style="background:#F0E9DC;"><div class="h-full gold-gradient" style="width: {{ $pct }}%"></div></div>
                    <span class="text-muted w-28 text-right">{{ number_format((float) $cat->spent) }} / {{ number_format((float) $cat->budgeted) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">🎁 Pledges & Contributions</h2>
        <table class="w-full text-sm">
            <tr><td class="py-1.5 text-muted">Pledges made</td><td class="text-right text-inktext">{{ $report['pledges']['count'] }}</td></tr>
            <tr><td class="py-1.5 text-muted">Total pledged</td><td class="text-right text-inktext">{{ number_format($report['pledges']['pledged']) }}</td></tr>
            <tr><td class="py-1.5 text-muted">Total collected</td><td class="text-right text-gold">{{ number_format($report['pledges']['collected']) }}</td></tr>
            <tr><td class="py-1.5 text-muted">Fulfilled pledges</td><td class="text-right text-inktext">{{ $report['pledges']['fulfilled'] }}</td></tr>
            <tr class="border-t" style="border-color:#E8E2D9;"><td class="py-1.5 font-medium text-inktext">Still outstanding</td><td class="text-right font-medium text-inktext">{{ number_format($report['pledges']['outstanding_amount']) }}</td></tr>
        </table>
        <a href="{{ route('pledges.print', $wedding) }}" class="btn-ghost text-xs mt-4 no-print inline-block">Print detailed pledge list</a>
    </div>
</div>

{{-- Vendors & tasks --}}
<div class="grid md:grid-cols-2 gap-6 mb-6">
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">🤝 Vendors</h2>
        <table class="w-full text-sm">
            <tr><td class="py-1.5 text-muted">Vendors tracked</td><td class="text-right text-inktext">{{ $report['vendors']['count'] }}</td></tr>
            <tr><td class="py-1.5 text-muted">Booked</td><td class="text-right text-inktext">{{ $report['vendors']['booked'] }}</td></tr>
            <tr><td class="py-1.5 text-muted">Booked cost</td><td class="text-right text-inktext">{{ number_format($report['vendors']['cost']) }}</td></tr>
            <tr><td class="py-1.5 text-muted">Paid so far</td><td class="text-right text-gold">{{ number_format($report['vendors']['paid']) }}</td></tr>
        </table>
        <div class="mt-3 space-y-1">
            @foreach($wedding->vendors->where('status', 'booked') as $v)
                <p class="text-xs text-muted">✓ {{ $v->name }} <span class="opacity-60">({{ $v->category }})</span> — {{ number_format((float) $v->price_estimate) }}</p>
            @endforeach
        </div>
    </div>
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">✅ Checklist & Messages</h2>
        <table class="w-full text-sm">
            <tr><td class="py-1.5 text-muted">Tasks completed</td><td class="text-right text-inktext">{{ $report['tasks']['done'] }} / {{ $report['tasks']['total'] }}</td></tr>
            <tr><td class="py-1.5 text-muted">Messages delivered</td><td class="text-right text-inktext">{{ $report['messages']['sent'] }}</td></tr>
            <tr><td class="py-1.5 text-muted">Failed deliveries</td><td class="text-right {{ $report['messages']['failed'] ? 'text-red-500' : 'text-inktext' }}">{{ $report['messages']['failed'] }}</td></tr>
        </table>
        <h3 class="font-serif text-sm text-inktext mt-4 mb-2">Wedding crew</h3>
        @forelse($wedding->crewTeams as $team)
            <p class="text-xs text-muted">{{ $team->name }}: {{ $team->members->pluck('name')->join(', ') ?: 'no members' }}</p>
        @empty
            <p class="text-xs text-muted">No crew teams.</p>
        @endforelse
    </div>
</div>

{{-- Seating summary --}}
<div class="card p-6 mb-6">
    <h2 class="font-serif text-xl text-inktext mb-4">🪑 Seating</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach($wedding->seatingTables as $table)
            <div class="rounded-lg p-3 text-xs" style="background:#FAF7F2; border:1px solid #F0E9DC;">
                <p class="font-medium text-inktext">{{ $table->name }} <span class="text-muted">({{ $table->seatsUsed() }}/{{ $table->capacity }})</span></p>
                <p class="text-muted mt-1">{{ $table->guests->map(fn ($g) => $g->fullName())->join(', ') ?: 'Empty' }}</p>
            </div>
        @endforeach
    </div>
    <div class="flex gap-2 mt-4 no-print">
        <a href="{{ route('seating.print', $wedding) }}" class="btn-ghost text-xs">Print seating chart</a>
        <a href="{{ route('guests.print', $wedding) }}" class="btn-ghost text-xs">Print guest list</a>
        <a href="{{ route('checkin.print', [$wedding, 'filter' => 'attended']) }}" class="btn-ghost text-xs">Print attendance</a>
    </div>
</div>

<p class="text-center text-xs text-muted">Aroos · Powered by LetsVenture</p>
@endsection
