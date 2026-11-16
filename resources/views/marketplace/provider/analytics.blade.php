@extends('layouts.app')

@section('title', 'Analytics')

@use('App\Marketplace\Money')

@section('content')
@include('marketplace.provider._nav')

@php $maxView = max(1, $days->max()); $maxMonth = max(1, $months->max(fn ($m) => $m['requests'])); @endphp
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @foreach([['Profile views (30d)', $days->sum()], ['Requests (all time)', $total], ['Bookings', $booked], ['Request → booking', $conversion === null ? '—' : $conversion.'%'], ['Rating', $provider->rating_count ? number_format((float) $provider->rating_avg, 1).'★ ('.$provider->rating_count.')' : '—']] as [$l, $v])
        <div class="card p-4"><p class="eyebrow">{{ $l }}</p><p class="font-serif text-2xl mt-2">{{ $v }}</p></div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="card p-5">
        <p class="eyebrow mb-4">Profile views, last 30 days</p>
        <div class="flex items-end gap-0.5 h-32" role="img" aria-label="Daily profile views">
            @foreach($days as $date => $v)<div class="flex-1 bg-gold/70 rounded-t" style="height: {{ max(2, round(100 * $v / $maxView)) }}%" title="{{ $date }}: {{ $v }}"></div>@endforeach
        </div>
    </div>
    <div class="card p-5">
        <p class="eyebrow mb-4">Requests and bookings by month</p>
        <table class="w-full text-sm">
            @foreach($months as $m => $v)
                <tr><td class="py-1 text-xs text-muted w-20">{{ \Carbon\Carbon::parse($m.'-01')->format('M Y') }}</td>
                    <td class="py-1"><div class="h-3 rounded bg-gold/60" style="width: {{ max(2, round(100 * $v['requests'] / $maxMonth)) }}%"></div></td>
                    <td class="py-1 text-xs text-right w-28">{{ $v['requests'] }} req · {{ $v['bookings'] }} booked</td></tr>
            @endforeach
        </table>
    </div>
    <div class="card p-5">
        <p class="eyebrow mb-3">Most requested services</p>
        @forelse($byService as $name => $n)<p class="text-sm flex justify-between py-0.5"><span>{{ $name }}</span><span>{{ $n }}</span></p>@empty<p class="text-sm text-muted">No requests yet.</p>@endforelse
        <p class="eyebrow mt-5 mb-3">Most requested packages</p>
        @forelse($byPackage as $name => $n)<p class="text-sm flex justify-between py-0.5"><span>{{ $name }}</span><span>{{ $n }}</span></p>@empty<p class="text-sm text-muted">No package requests yet.</p>@endforelse
    </div>
    <div class="card p-5">
        <p class="eyebrow mb-3">Booking value</p>
        <p class="text-sm flex justify-between py-1"><span>Completed bookings (agreed prices)</span><strong>{{ Money::format($completedValue) }}</strong></p>
        <p class="text-sm flex justify-between py-1"><span>Upcoming confirmed bookings (agreed prices)</span><strong>{{ Money::format($upcomingValue) }}</strong></p>
        <p class="text-xs text-muted mt-3">These are prices agreed with couples, not payments received. Open quotations are not counted.</p>
    </div>
</div>
@endsection
