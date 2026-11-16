@extends('layouts.app')

@section('title', 'Saved providers')

@section('content')
<a href="{{ route('marketplace.index') }}" class="text-xs text-muted">← Marketplace</a>
<h1 class="font-serif text-3xl mt-2 mb-6">Saved providers</h1>
@if($providers->isEmpty())
    <div class="card p-10 text-center text-muted">Nothing saved yet. Tap ♡ on a provider to keep them here.</div>
@else
    <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($providers as $p)
            @if($p->isListed())
                @include('marketplace._card', ['p' => $p, 'favourite' => true])
            @else
                <div class="card p-5 text-sm text-muted">{{ $p->business_name }} is not available in the marketplace right now.
                    <form method="POST" action="{{ route('marketplace.favourite', $p) }}" class="mt-2">@csrf<button class="underline">Remove</button></form>
                </div>
            @endif
        @endforeach
    </div>
@endif
@endsection
