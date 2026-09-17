@extends('layouts.app')

@section('title', 'Wedding analytics')

@section('content')
@php
    // Storage is reported in bytes; show it in whole megabytes so the
    // number means something at a glance.
    $usedMb = $stats['storage']['used'] / 1048576;
    $totalMb = $stats['storage']['total'] / 1048576;
    $storagePercent = $totalMb > 0 ? min(100, round(($usedMb / $totalMb) * 100)) : 0;

    $budgetPercent = $stats['budget']['total'] > 0
        ? min(100, round(($stats['budget']['spent'] / $stats['budget']['total']) * 100))
        : 0;

    $collectedPercent = $stats['pledges']['amount'] > 0
        ? round(($stats['pledges']['collected'] / $stats['pledges']['amount']) * 100)
        : 0;
@endphp

<div class="mb-8">
    <p class="eyebrow">
        <a class="underline" href="{{ route('admin.analytics.index') }}">Analytics</a>
    </p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">
        {{ $wedding->partner_a }} &amp; {{ $wedding->partner_b }}
    </h1>
    <p class="text-xs text-muted mt-1">
        {{ $wedding->wedding_date?->format('j F Y') ?? 'Date to be set' }}
        @if($wedding->venue_name) · {{ $wedding->venue_name }} @endif
    </p>
</div>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="card p-5">
        <p class="eyebrow">Guests</p>
        <p class="font-serif text-3xl mt-2">{{ $stats['guests']['total'] }}</p>
        <p class="text-xs text-muted mt-1">{{ $stats['guests']['attending'] }} attending</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Checked in</p>
        <p class="font-serif text-3xl mt-2">{{ $stats['guests']['checked_in'] }}</p>
        <p class="text-xs text-muted mt-1">on the day</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Pledges</p>
        <p class="font-serif text-3xl mt-2">{{ $stats['pledges']['total'] }}</p>
        <p class="text-xs text-muted mt-1">
            TZS {{ number_format((float) $stats['pledges']['amount']) }} promised
        </p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Collected</p>
        <p class="font-serif text-3xl mt-2">{{ $collectedPercent }}%</p>
        <p class="text-xs text-muted mt-1">
            TZS {{ number_format((float) $stats['pledges']['collected']) }} received
        </p>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <p class="eyebrow">Budget</p>
        <p class="font-serif text-2xl mt-2">
            TZS {{ number_format((float) $stats['budget']['spent']) }}
            <span class="text-sm text-muted">
                of {{ number_format((float) $stats['budget']['total']) }}
            </span>
        </p>
        <div class="mt-3 h-2 rounded-full" style="background:#F0EBE3;">
            <div class="h-2 rounded-full" style="width: {{ $budgetPercent }}%; background:#C4788B;"></div>
        </div>
        <p class="text-xs text-muted mt-2">{{ $budgetPercent }}% of the budget spent</p>
    </div>

    <div class="card p-5">
        <p class="eyebrow">Storage</p>
        <p class="font-serif text-2xl mt-2">
            {{ number_format($usedMb, 1) }} MB
            <span class="text-sm text-muted">of {{ number_format($totalMb, 0) }} MB</span>
        </p>
        <div class="mt-3 h-2 rounded-full" style="background:#F0EBE3;">
            <div class="h-2 rounded-full" style="width: {{ $storagePercent }}%; background:#C9A227;"></div>
        </div>
        <p class="text-xs text-muted mt-2">
            @if($totalMb > 0)
                {{ $storagePercent }}% of the plan used
            @else
                No storage plan on this wedding
            @endif
        </p>
    </div>
</div>
@endsection
