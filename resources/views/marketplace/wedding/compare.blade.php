@extends('layouts.app')

@section('title', 'Compare quotations')

@use('App\Marketplace\Money')

@section('content')
<a href="{{ route('services.index', $wedding) }}" class="text-xs text-muted">← Service providers</a>
<h1 class="font-serif text-3xl mt-2 mb-6">Compare quotations</h1>
@if($enquiries->count() < 2)
    <div class="card p-10 text-center text-muted">Select at least two requests to compare.</div>
@else
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="border-b border-line"><th class="px-4 py-3 text-left text-xs text-muted w-40"></th>
            @foreach($enquiries as $e)<th class="px-4 py-3 text-left font-serif text-lg"><a href="{{ route('services.enquiry', [$wedding, $e]) }}" class="hover:text-gold">{{ $e->provider->business_name }}</a></th>@endforeach
        </tr></thead>
        <tbody>
        @php
            $latest = fn ($e) => $e->versions->where('author_side', 'provider')->sortByDesc('version_no')->first();
            $rows = [
                'Service' => fn ($e) => $e->service?->title,
                'Package' => fn ($e) => ($latest($e)?->package ?? $e->package)?->name ?? '—',
                'Latest offer' => fn ($e) => $latest($e) ? Money::format($latest($e)->amount, $latest($e)->currency) : 'No quote yet',
                'Offer valid until' => fn ($e) => $latest($e)?->valid_until?->format('j M Y') ?? '—',
                'Date' => fn ($e) => ($latest($e)?->proposed_date ?? $e->event_date)->format('j M Y'),
                'Guests' => fn ($e) => $e->guests ? number_format($e->guests) : '—',
                'Included' => fn ($e) => collect(($latest($e)?->package ?? $e->package)?->features ?? $e->service?->inclusions ?? [])->join(', ') ?: '—',
                'Extras' => fn ($e) => collect(($latest($e)?->package ?? $e->package)?->extras ?? [])->map(fn ($x) => $x['name'].($x['price'] !== null ? ' ('.Money::format($x['price']).')' : ''))->join(', ') ?: '—',
                'Location' => fn ($e) => $e->provider->region?->name ?? '—',
                'Terms' => fn ($e) => $latest($e)?->terms ?: (($latest($e)?->package ?? $e->package)?->terms ?: '—'),
                'Verified' => fn ($e) => $e->provider->verification_status === 'verified' ? '✔ Verified by Aroos' : 'Not verified',
                'Status' => fn ($e) => str_replace('_', ' ', $e->status),
            ];
        @endphp
        @foreach($rows as $label => $value)
            <tr class="border-b border-line/50 align-top"><th class="px-4 py-3 text-left text-xs text-muted font-normal">{{ $label }}</th>
                @foreach($enquiries as $e)<td class="px-4 py-3 whitespace-pre-line">{{ $value($e) }}</td>@endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="text-xs text-muted mt-3">Reviews will appear here once providers have completed bookings on Aroos.</p>
@endif
@endsection
