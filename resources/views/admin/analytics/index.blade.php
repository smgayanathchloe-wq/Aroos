@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Platform</p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">Analytics</h1>
</div>

{{-- Headline numbers --}}
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-8">
    <div class="card p-5">
        <p class="eyebrow">Weddings</p>
        <p class="font-serif text-3xl mt-2">{{ $totalWeddings }}</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Still upcoming</p>
        <p class="font-serif text-3xl mt-2">{{ $activeWeddings }}</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Already held</p>
        <p class="font-serif text-3xl mt-2">{{ $completedWeddings }}</p>
    </div>
    <div class="card p-5">
        <p class="eyebrow">Active subscriptions</p>
        <p class="font-serif text-3xl mt-2">{{ $activeSubscriptions }}</p>
    </div>
</div>

{{-- Revenue by package --}}
<div class="grid gap-6 lg:grid-cols-3 mb-8">
    <div class="card p-5">
        <p class="eyebrow">Recurring revenue</p>
        <p class="font-serif text-3xl mt-2">
            TZS {{ number_format((float) $totalRevenue) }}
        </p>
        <p class="text-xs text-muted mt-2">
            Sum of the package price for every active subscription.
        </p>
    </div>

    <div class="card overflow-x-auto lg:col-span-2">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs text-muted" style="border-color:#E8E2D9;">
                    <th class="px-4 py-3">Package</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Active</th>
                    <th class="px-4 py-3">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse($packageStats as $pkg)
                    <tr class="border-b hover:bg-canvas" style="border-color:#F0EBE3;">
                        <td class="px-4 py-3 text-inktext">{{ $pkg['name'] }}</td>
                        <td class="px-4 py-3 text-xs text-muted">
                            TZS {{ number_format((float) $pkg['price']) }}
                        </td>
                        <td class="px-4 py-3">{{ $pkg['subscriptions'] }}</td>
                        <td class="px-4 py-3">TZS {{ number_format((float) $pkg['revenue']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-muted">
                            No packages yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Newest weddings --}}
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs text-muted" style="border-color:#E8E2D9;">
                <th class="px-4 py-3">Couple</th>
                <th class="px-4 py-3">Owner</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Website</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($recentWeddings as $wedding)
                <tr class="border-b hover:bg-canvas" style="border-color:#F0EBE3;">
                    <td class="px-4 py-3 text-inktext">
                        {{ $wedding->partner_a }} &amp; {{ $wedding->partner_b }}
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">
                        {{ $wedding->user?->name ?? 'Unassigned' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">
                        {{ $wedding->wedding_date?->format('j M Y') ?? 'To be set' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">
                        {{ $wedding->websiteSetting?->published ? 'Published' : 'Draft' }}
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <a class="text-inktext underline"
                           href="{{ route('admin.analytics.wedding', $wedding) }}">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-muted">
                        No weddings yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
