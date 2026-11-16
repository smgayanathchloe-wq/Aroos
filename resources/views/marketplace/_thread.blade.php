{{-- Negotiation timeline shared by both sides. Expects $enquiry and $side ('customer'|'provider'). --}}
@use('App\Marketplace\Money')
@php
    $timeline = $enquiry->versions->map(fn ($v) => ['at' => $v->created_at, 'v' => $v])
        ->concat($enquiry->messages->map(fn ($m) => ['at' => $m->created_at, 'm' => $m]))
        ->sortBy(fn ($i) => [$i['at']->getTimestamp(), isset($i['m']) ? 0 : 1])->values();
@endphp
<div class="space-y-3">
@foreach($timeline as $item)
    @isset($item['v'])
        @php $v = $item['v']; $mine = $v->author_side === $side; @endphp
        <div class="rounded-lg border {{ $v->status === 'accepted' ? 'border-green-600/50' : 'border-gold/40' }} p-4 {{ $mine ? 'ml-8' : 'mr-8' }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-muted">{{ $v->author_side === 'provider' ? 'Quotation' : 'Counter-offer' }} v{{ $v->version_no }} · {{ $v->author?->name }} · {{ $v->created_at->format('j M Y H:i') }}</p>
                <span class="text-[11px] uppercase tracking-wide {{ $v->status === 'pending' ? 'text-gold' : 'text-muted' }}">{{ $v->status === 'pending' && $v->isExpired() ? 'expired' : $v->status }}</span>
            </div>
            <p class="font-serif text-2xl mt-1">{{ Money::format($v->amount, $v->currency) }}
                @if($v->previous_amount !== null)<span class="text-xs text-muted font-sans">was {{ Money::format($v->previous_amount, $v->currency) }}</span>@endif
            </p>
            @if($v->package)<p class="text-sm">Package: {{ $v->package->name }}</p>@endif
            @if($v->proposed_date)<p class="text-sm">Proposed date: <strong>{{ $v->proposed_date->format('j M Y') }}</strong></p>@endif
            @if($v->message)<p class="text-sm mt-2 whitespace-pre-line">{{ $v->message }}</p>@endif
            @if($v->terms)<details class="mt-2"><summary class="text-xs text-muted cursor-pointer">Terms</summary><p class="text-sm whitespace-pre-line mt-1">{{ $v->terms }}</p></details>@endif
            @if($v->valid_until && $v->status === 'pending')<p class="text-xs text-muted mt-2">Valid until {{ $v->valid_until->format('j M Y H:i') }}</p>@endif
        </div>
    @else
        @php $m = $item['m']; @endphp
        @if($m->kind === 'event')
            <p class="text-center text-xs text-muted">{{ $m->body }} · {{ $m->created_at->format('j M H:i') }}</p>
        @else
            <div class="rounded-lg px-4 py-3 text-sm {{ $m->side === $side ? 'ml-8 bg-canvas' : 'mr-8 border border-line' }}">
                <p class="text-xs text-muted mb-1">{{ $m->user?->name }}{{ $m->kind === 'info_request' ? ' · asked for more information' : ($m->kind === 'meeting_request' ? ' · requested a meeting' : '') }} · {{ $m->created_at->format('j M H:i') }}</p>
                <p class="whitespace-pre-line">{{ $m->body }}</p>
            </div>
        @endif
    @endisset
@endforeach
</div>
