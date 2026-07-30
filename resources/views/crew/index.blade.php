@extends('layouts.app')

@section('title', 'Wedding Crew')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">The Day</p>
        <h1 class="font-serif text-3xl mt-1">Wedding Crew</h1>
    </div>
    <form method="POST" action="{{ route('crew.store', $wedding) }}" class="flex gap-2">
        @csrf
        <input name="name" placeholder="Team name (e.g. Greeters Team)" required class="lux-input w-64">
        <button class="btn-gold">Add team</button>
    </form>
</div>

<div class="grid md:grid-cols-2 gap-6">
    @forelse($teams as $team)
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-serif text-xl">{{ $team->name }}</h2>
                <form method="POST" action="{{ route('crew.destroy', [$wedding, $team]) }}" onsubmit="return confirm('Delete this team?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-muted hover:text-red-400">Delete</button>
                </form>
            </div>

            <p class="eyebrow mb-2">Responsibilities</p>
            <ul class="space-y-1 mb-3">
                @forelse($team->responsibilities ?? [] as $i => $resp)
                    <li class="flex justify-between text-sm">
                        <span>• {{ $resp }}</span>
                        <form method="POST" action="{{ route('crew.update', [$wedding, $team]) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="remove_responsibility" value="{{ $i }}">
                            <button class="text-xs text-muted hover:text-red-400">×</button>
                        </form>
                    </li>
                @empty
                    <li class="text-xs text-muted">No responsibilities listed</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('crew.update', [$wedding, $team]) }}" class="flex gap-2 mb-5">
                @csrf @method('PUT')
                <input name="responsibility" placeholder="New responsibility…" class="lux-input flex-1">
                <button class="btn-ghost text-xs">Add</button>
            </form>

            <p class="eyebrow mb-2">Members</p>
            <ul class="space-y-1.5 mb-3">
                @foreach($team->members as $member)
                    <li class="flex items-center justify-between text-sm">
                        <span>{{ $member->name }} @if($member->role)<span class="text-xs text-muted">· {{ $member->role }}</span>@endif</span>
                        <span class="flex items-center gap-3">
                            <span class="text-xs text-muted">{{ $member->phone }}</span>
                            <form method="POST" action="{{ route('crew.members.destroy', [$wedding, $team, $member]) }}">
                                @csrf @method('DELETE')
                                <button class="text-xs text-muted hover:text-red-400">×</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('crew.members.store', [$wedding, $team]) }}" class="grid grid-cols-3 gap-2">
                @csrf
                <input name="name" placeholder="Member name" required class="lux-input">
                <input name="role" placeholder="Role" class="lux-input">
                <input name="phone" placeholder="Phone" class="lux-input">
                <button class="btn-ghost text-xs col-span-3 justify-self-start">Add member</button>
            </form>
        </div>
    @empty
        <div class="card p-10 text-center text-muted md:col-span-2">
            <p class="font-serif text-xl mb-2">No crew teams yet</p>
            <p class="text-sm">Create teams like “Greeters”, “Kitchen”, “Transport” and assign helpers with responsibilities.</p>
        </div>
    @endforelse
</div>
@endsection
