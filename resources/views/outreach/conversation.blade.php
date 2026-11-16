@extends('layouts.app')

@section('title', 'Conversation')

@section('content')
<a href="{{ route('outreach.conversations', $wedding) }}" class="text-xs text-muted">← Conversations</a>
<h1 class="font-serif text-3xl mt-2 mb-1">{{ $conversation->pledge?->pledger_name ?? 'Unknown' }}</h1>
<p class="text-sm text-muted mb-6">{{ ucfirst($conversation->channel) }} · {{ $conversation->purpose }} · {{ $conversation->status }} · {{ $conversation->created_at->format('M j, Y H:i') }}</p>

<div class="grid lg:grid-cols-[1fr_380px] gap-6">
    <div class="card p-5 space-y-3">
        <p class="eyebrow">Transcript</p>
        @forelse($conversation->turns as $t)
            @continue($t->role === 'system')
            <div class="flex {{ $t->role === 'guest' ? 'justify-end' : '' }}">
                <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm {{ $t->role === 'guest' ? 'bg-ink border border-line' : 'border border-gold/40' }}">
                    <p class="text-[10px] uppercase tracking-wide text-muted mb-1">{{ $t->role === 'guest' ? 'Guest' : 'Assistant' }} · {{ $t->created_at?->format('H:i:s') }}</p>
                    <p class="whitespace-pre-line">{{ $t->content }}</p>
                    @if($t->role === 'assistant' && $t->payload && collect($t->payload)->except(['options', 'template'])->isNotEmpty())
                        <details class="mt-2"><summary class="text-xs text-muted cursor-pointer">What the AI recorded</summary>
                            <pre class="text-xs mt-1 overflow-x-auto">{{ json_encode(collect($t->payload)->except('options'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-muted">No turns recorded.</p>
        @endforelse
    </div>
    <div class="space-y-6">
        <div class="card p-5">
            <p class="eyebrow mb-2">Outcome</p>
            <p class="text-sm">{{ $conversation->outcome ?? '—' }}</p>
            @if($conversation->summary)<p class="text-sm text-muted mt-2">{{ $conversation->summary }}</p>@endif
        </div>
        <div class="card p-5">
            <p class="eyebrow mb-2">Commitments</p>
            @forelse($conversation->promises as $promise)
                <p class="text-sm">Complete by <strong>{{ $promise->promised_date->format('j M Y') }}</strong>{{ $promise->amount ? ' · '.number_format((float) $promise->amount) : '' }} <span class="text-xs text-muted">({{ $promise->confidence }}, {{ $promise->status }})</span></p>
            @empty
                <p class="text-sm text-muted">No promise captured.</p>
            @endforelse
        </div>
        <div class="card p-5">
            <p class="eyebrow mb-2">Extracted JSON</p>
            <pre class="text-xs overflow-x-auto">{{ json_encode($conversation->extracted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '—' }}</pre>
        </div>
    </div>
</div>
@endsection
