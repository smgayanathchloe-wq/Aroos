@extends('layouts.app')

@section('title', 'Reminder logs')

@section('content')
<div class="mb-8">
    <a href="{{ route('reminders.index', $wedding) }}" class="text-xs text-muted hover:text-gold">← Back to reminders</a>
    <h1 class="font-serif text-3xl mt-2">{{ $reminder->name }} — delivery log</h1>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">Recipient</th>
                <th class="px-4 py-3">Channel</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Time</th>
                <th class="px-4 py-3">Error</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr class="border-b border-line/50">
                    <td class="px-4 py-3">{{ $log->recipient }}</td>
                    <td class="px-4 py-3 text-xs">{{ strtoupper($log->channel) }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs {{ $log->status === 'sent' ? 'text-gold' : 'text-red-400' }}">{{ ucfirst($log->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $log->created_at->format('M j, H:i') }}</td>
                    <td class="px-4 py-3 text-xs text-red-300">{{ $log->error }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-muted">No deliveries yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
