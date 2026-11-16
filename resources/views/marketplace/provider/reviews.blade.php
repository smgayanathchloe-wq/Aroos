@extends('layouts.app')

@section('title', 'Reviews')

@section('content')
@include('marketplace.provider._nav')

<div class="card p-5 mb-6 flex gap-8">
    <div><p class="eyebrow">Average</p><p class="font-serif text-3xl text-gold mt-1">{{ $provider->rating_count ? number_format((float) $provider->rating_avg, 1).'★' : '—' }}</p></div>
    <div><p class="eyebrow">Reviews</p><p class="font-serif text-3xl mt-1">{{ $provider->rating_count }}</p></div>
    <p class="text-xs text-muted self-end">Only couples with a completed Aroos booking can review. Hidden reviews don't count toward your rating.</p>
</div>
@forelse($reviews as $r)
    <div class="card p-5 mb-3">
        <div class="flex justify-between"><p class="text-sm"><span class="text-gold">{{ str_repeat('★', $r->overall) }}</span> · {{ $r->reviewerName() }} · {{ $r->booking?->service?->title }}</p>
            <span class="text-xs {{ $r->status === 'hidden' ? 'text-red-500' : 'text-muted' }}">{{ $r->status === 'hidden' ? 'Hidden by Aroos' : $r->created_at->format('j M Y') }}</span></div>
        @if($r->body)<p class="text-sm mt-2 whitespace-pre-line">{{ $r->body }}</p>@endif
        <form method="POST" action="{{ route('provider.reviews.respond', $r) }}" class="mt-3 space-y-2">@csrf
            <textarea name="response" rows="2" required placeholder="Write a public response" class="lux-input" aria-label="Response">{{ $r->provider_response }}</textarea>
            <button class="btn-ghost text-xs">{{ $r->provider_response ? 'Update response' : 'Respond' }}</button>
        </form>
    </div>
@empty
    <div class="card p-10 text-center text-muted">No reviews yet. Couples can review after a completed booking.</div>
@endforelse
<div class="mt-4">{{ $reviews->links() }}</div>
@endsection
