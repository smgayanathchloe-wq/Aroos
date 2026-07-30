@extends('layouts.app')

@section('title', 'Memories')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Guestbook</p>
    <h1 class="font-serif text-3xl mt-1">Memories</h1>
    <p class="text-sm text-muted mt-2 max-w-xl">Messages and memories left by your guests. Memories arrive from your public wedding site's guestbook.</p>
</div>

<div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($memories as $memory)
        <div class="card p-5">
            <div class="flex items-center justify-between mb-2">
                <p class="text-gold text-sm">{{ $memory->guest_name ?? 'Anonymous' }}</p>
                <form method="POST" action="{{ route('memories.destroy', [$wedding, $memory]) }}" onsubmit="return confirm('Remove this memory?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-muted hover:text-red-400">×</button>
                </form>
            </div>
            @if($memory->message)<p class="text-sm leading-relaxed">“{{ $memory->message }}”</p>@endif
            @if($memory->file_path)
                @if($memory->type === 'video')
                    <video controls src="{{ asset('storage/'.$memory->file_path) }}" class="w-full rounded mt-3"></video>
                @elseif($memory->type === 'audio')
                    <audio controls src="{{ asset('storage/'.$memory->file_path) }}" class="w-full mt-3"></audio>
                @endif
            @endif
            <p class="text-xs text-muted mt-3">{{ $memory->created_at->format('M j, Y') }}</p>
        </div>
    @empty
        <div class="card p-10 text-center text-muted col-span-full">
            <p class="font-serif text-xl mb-2">No memories yet</p>
            <p class="text-sm">Guests can leave memories from your public wedding website's guestbook.</p>
        </div>
    @endforelse
</div>
@endsection
