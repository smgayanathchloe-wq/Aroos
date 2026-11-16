@extends('layouts.app')

@section('title', 'AI Conversations')

@section('content')
<h1 class="font-serif text-3xl mb-6">AI Conversations</h1>
@include('outreach._tabs')

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="border-b border-line text-left text-xs text-muted">
            <th class="px-4 py-3">When</th><th class="px-4 py-3">Pledger</th><th class="px-4 py-3">Channel</th>
            <th class="px-4 py-3">Status</th><th class="px-4 py-3">Outcome</th><th class="px-4 py-3">Summary</th>
        </tr></thead>
        <tbody>
        @forelse($conversations as $c)
            <tr class="border-b border-line/50 hover:bg-ink/40">
                <td class="px-4 py-3 text-xs text-muted"><a class="underline" href="{{ route('outreach.conversation', [$wedding, $c]) }}">{{ $c->created_at->format('M j, H:i') }}</a></td>
                <td class="px-4 py-3">{{ $c->pledge?->pledger_name ?? '—' }}</td>
                <td class="px-4 py-3 text-xs">{{ $c->channel }} · {{ $c->purpose }}</td>
                <td class="px-4 py-3 text-xs">{{ $c->status }}</td>
                <td class="px-4 py-3 text-xs">{{ $c->outcome ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-muted">{{ \Illuminate\Support\Str::limit($c->summary, 120) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No conversations yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $conversations->links() }}</div>
@endsection
