@extends('layouts.app')

@section('title', 'Requests')

@section('content')
@include('marketplace.provider._nav')

<div class="flex gap-1 mb-4">
    @foreach(['open' => 'Open', 'closed' => 'Closed', 'all' => 'All'] as $v => $l)
        <a href="{{ route('provider.enquiries', ['tab' => $v]) }}" class="text-xs px-3 py-1 rounded-full border {{ $tab === $v ? 'border-gold text-gold' : 'border-line text-muted' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="border-b border-line text-left text-xs text-muted"><th class="px-4 py-3">From</th><th class="px-4 py-3">Service</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Budget</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last activity</th></tr></thead>
        <tbody>
        @forelse($enquiries as $e)
            <tr class="border-b border-line/50 hover:bg-canvas">
                <td class="px-4 py-3"><a href="{{ route('provider.enquiries.show', $e) }}" class="font-medium hover:text-gold">{{ $e->contact_name }}</a><p class="text-xs text-muted">{{ $e->reference }}</p></td>
                <td class="px-4 py-3 text-xs">{{ $e->service?->title }}</td>
                <td class="px-4 py-3 text-xs">{{ $e->event_date->format('j M Y') }}{{ $e->guests ? ' · '.number_format($e->guests).' guests' : '' }}</td>
                <td class="px-4 py-3 text-xs">{{ $e->budget ? \App\Marketplace\Money::format($e->budget) : '—' }}</td>
                <td class="px-4 py-3">@include('marketplace._status', ['status' => $e->status])</td>
                <td class="px-4 py-3 text-xs text-muted">{{ $e->last_activity_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No requests here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $enquiries->links() }}</div>
@endsection
