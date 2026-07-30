@extends('layouts.app')

@section('title', 'Preview recipients')

@section('content')
<div class="mb-8">
    <a href="{{ route('reminders.index', $wedding) }}" class="text-xs text-muted hover:text-gold">← Back to messages</a>
    <h1 class="font-serif text-3xl mt-2 text-inktext">{{ $reminder->name }}</h1>
    <p class="text-sm text-muted mt-1">Audience: <strong>{{ ucfirst($reminder->audience) }}</strong> · Channel: <strong>{{ strtoupper($reminder->channel) }}</strong> · {{ count($recipients) }} recipient(s) will receive this.</p>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs text-muted" style="border-color:#E8E2D9;">
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Phone</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recipients as $r)
                    <tr class="border-b" style="border-color:#F0E9DC;">
                        <td class="px-4 py-2.5 text-inktext">{{ $r['name'] }}</td>
                        <td class="px-4 py-2.5 text-xs text-muted">{{ $r['email'] ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-xs text-muted">{{ $r['phone'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-muted">No recipients match. Adjust the audience filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <div class="card p-6 mb-4">
            <p class="eyebrow mb-3">Message preview {{ count($recipients) ? '(for '.$recipients[0]['name'].')' : '' }}</p>
            @php $ph = count($recipients) ? $recipients[0]['placeholders'] : []; @endphp
            @if($reminder->subject)
                <p class="text-sm text-inktext font-medium mb-2">{{ strtr($reminder->subject, $ph) }}</p>
            @endif
            <p class="text-sm text-muted whitespace-pre-line">{{ strtr($reminder->body, $ph) }}</p>
        </div>
        <form method="POST" action="{{ route('reminders.send', [$wedding, $reminder]) }}" onsubmit="return confirm('Send to {{ count($recipients) }} recipient(s) now?')">
            @csrf
            <button class="btn-gold w-full py-3" {{ count($recipients) ? '' : 'disabled' }}>Send to {{ count($recipients) }} recipient(s)</button>
        </form>
    </div>
</div>
@endsection
