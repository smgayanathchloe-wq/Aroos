@extends('layouts.app')

@section('title', 'Team')

@section('content')
@include('marketplace.provider._nav')

@php $labels = ['enquiries' => 'See requests', 'respond' => 'Reply & quote', 'calendar' => 'Calendar', 'bookings' => 'Bookings', 'services' => 'Edit services & profile', 'reports' => 'Analytics']; @endphp
<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    <div class="space-y-3">
        @foreach($members as $m)
            <div class="card p-5">
                <div class="flex justify-between gap-3">
                    <div><p class="font-medium">{{ $m->user?->name ?? $m->email }}</p><p class="text-xs text-muted">{{ $m->user?->email ?? $m->email }} · {{ $m->role === 'owner' ? 'Owner — full access' : ($m->status === 'invited' ? 'Invited (joins when they sign up)' : 'Staff') }}</p></div>
                    @if($m->role !== 'owner')
                        <form method="POST" action="{{ route('provider.team.remove', $m) }}" onsubmit="return confirm('Remove from team?')">@csrf @method('DELETE')<button class="text-xs text-muted hover:text-red-500">Remove</button></form>
                    @endif
                </div>
                @if($m->role !== 'owner')
                    <form method="POST" action="{{ route('provider.team.update', $m) }}" class="mt-3 flex flex-wrap gap-3 items-center text-xs">@csrf @method('PUT')
                        @foreach($labels as $perm => $label)<label class="flex items-center gap-1"><input type="checkbox" name="permissions[]" value="{{ $perm }}" @checked(in_array($perm, $m->permissions ?? [], true))> {{ $label }}</label>@endforeach
                        <button class="btn-ghost text-xs">Save</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
    <form method="POST" action="{{ route('provider.team.invite') }}" class="card p-5 space-y-3 h-fit">@csrf
        <p class="eyebrow">Add a team member</p>
        <input type="email" name="email" required placeholder="Their email" class="lux-input" aria-label="Email">
        <div class="space-y-1 text-xs">@foreach($labels as $perm => $label)<label class="flex items-center gap-1.5"><input type="checkbox" name="permissions[]" value="{{ $perm }}" @checked(in_array($perm, ['enquiries', 'respond'], true))> {{ $label }}</label>@endforeach</div>
        <button class="btn-gold w-full">Add</button>
        <p class="text-xs text-muted">Staff see customer details only for requests they are allowed to handle.</p>
    </form>
</div>
@endsection
