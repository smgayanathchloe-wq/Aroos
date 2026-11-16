@extends('layouts.app')

@section('title', 'Marketplace analytics')

@section('content')
<a href="{{ route('admin.marketplace.index') }}" class="text-xs text-muted">← Marketplace admin</a>
<h1 class="font-serif text-3xl mt-2 mb-6">Marketplace analytics</h1>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($totals as $l => $v)<div class="card p-4"><p class="eyebrow">{{ $l }}</p><p class="font-serif text-2xl mt-2">{{ $v }}</p></div>@endforeach
    <div class="card p-4"><p class="eyebrow">Completed bookings (agreed value)</p><p class="font-serif text-2xl mt-2">{{ \App\Marketplace\Money::format($agreedValue) }}</p></div>
</div>
<div class="grid lg:grid-cols-2 gap-6">
    <div class="card p-5"><p class="eyebrow mb-3">Listed providers by category</p>
        @foreach($byCategory as $c)<p class="text-sm flex justify-between py-0.5"><span>{{ $c->name }}</span><span>{{ $c->listed }}</span></p>@endforeach
    </div>
    <div class="card p-5"><p class="eyebrow mb-3">Listed providers by region</p>
        @forelse($byRegion as $name => $n)<p class="text-sm flex justify-between py-0.5"><span>{{ $name }}</span><span>{{ $n }}</span></p>@empty<p class="text-sm text-muted">No listed providers yet.</p>@endforelse
    </div>
</div>
@endsection
