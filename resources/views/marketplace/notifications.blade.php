@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h1 class="font-serif text-3xl">Notifications</h1>
    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-ghost text-xs">Mark all as read</button></form>
</div>
<div class="card divide-y divide-line">
    @forelse($notifications as $n)
        <a href="{{ route('notifications.open', $n->id) }}" class="block px-5 py-4 hover:bg-canvas {{ $n->read_at ? '' : 'border-l-2 border-gold' }}">
            <p class="text-sm {{ $n->read_at ? '' : 'font-semibold' }}">{{ $n->data['title'] ?? 'Notification' }}</p>
            <p class="text-sm text-muted">{{ $n->data['body'] ?? '' }}</p>
            <p class="text-xs text-muted mt-1">{{ $n->created_at->diffForHumans() }}</p>
        </a>
    @empty
        <p class="px-5 py-10 text-center text-muted">You're all caught up.</p>
    @endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
