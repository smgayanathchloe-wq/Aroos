@extends('layouts.app')

@section('title', 'Bookings')

@use('App\Marketplace\Money')

@section('content')
@include('marketplace.provider._nav')

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="border-b border-line text-left text-xs text-muted"><th class="px-4 py-3">Booking</th><th class="px-4 py-3">Event</th><th class="px-4 py-3">Agreed</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
        <tbody>
        @forelse($bookings as $b)
            <tr class="border-b border-line/50 align-top">
                <td class="px-4 py-3"><p class="font-medium">{{ $b->enquiry->contact_name }}</p><p class="text-xs text-muted">{{ $b->service?->title }}{{ $b->package ? ' · '.$b->package->name : '' }} · <a href="{{ route('provider.enquiries.show', $b->enquiry_id) }}" class="underline">{{ $b->reference }}</a></p></td>
                <td class="px-4 py-3 text-xs">{{ $b->event_date->format('D j M Y') }}{{ $b->starts_at ? ' · '.substr($b->starts_at, 0, 5) : '' }}<br>{{ $b->terms_snapshot['event']['venue'] ?? '' }}</td>
                <td class="px-4 py-3">{{ Money::format($b->agreed_amount, $b->currency) }}</td>
                <td class="px-4 py-3">@include('marketplace._status', ['status' => $b->status])@if($b->cancel_reason)<p class="text-xs text-muted mt-1">{{ $b->cancel_reason }}</p>@endif</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if($b->status === 'confirmed')
                        @if(! $b->event_date->isFuture())
                            <form method="POST" action="{{ route('provider.bookings.complete', $b) }}" class="inline">@csrf<button class="text-xs text-gold">Mark completed</button></form>
                        @endif
                        <details class="inline-block ml-3 text-left"><summary class="text-xs text-muted cursor-pointer">Cancel</summary>
                            <form method="POST" action="{{ route('provider.bookings.cancel', $b) }}" class="mt-2 w-64 space-y-2">@csrf
                                <input name="reason" required placeholder="Reason (shared with the couple)" class="lux-input">
                                <button class="text-xs text-red-500">Cancel booking</button>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-muted">No bookings yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $bookings->links() }}</div>
<p class="text-xs text-muted mt-3">Amounts are agreed prices. Aroos does not track payments for bookings yet.</p>
@endsection
