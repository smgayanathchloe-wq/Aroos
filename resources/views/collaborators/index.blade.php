@extends('layouts.app')

@section('title', 'Collaborators')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Team</p>
        <h1 class="font-serif text-3xl mt-1">Collaborators</h1>
    </div>
    <form method="POST" action="{{ route('collaborators.store', $wedding) }}" class="flex gap-2">
        @csrf
        <input type="email" name="email" placeholder="Email address" required class="lux-input w-56">
        <select name="role" class="lux-input w-40">
            <option value="partner">Partner</option>
            <option value="planner">Planner</option>
            <option value="family">Family</option>
            <option value="wedding_party">Wedding party</option>
            <option value="vendor">Vendor</option>
        </select>
        <button class="btn-gold">Invite</button>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">Member</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-b border-line/50">
                <td class="px-4 py-3">
                    <p>{{ $wedding->user->name }}</p>
                    <p class="text-xs text-muted">{{ $wedding->user->email }}</p>
                </td>
                <td class="px-4 py-3"><span class="text-xs border border-gold rounded-full px-2.5 py-1 text-gold">Owner</span></td>
                <td class="px-4 py-3 text-xs text-gold">Active</td>
                <td></td>
            </tr>
            @foreach($collaborators as $collab)
                <tr class="border-b border-line/50">
                    <td class="px-4 py-3">
                        <p>{{ $collab->user?->name ?? '—' }}</p>
                        <p class="text-xs text-muted">{{ $collab->email }}</p>
                    </td>
                    <td class="px-4 py-3"><span class="text-xs border border-line rounded-full px-2.5 py-1 text-muted">{{ str_replace('_', ' ', ucfirst($collab->role)) }}</span></td>
                    <td class="px-4 py-3 text-xs {{ $collab->status === 'active' ? 'text-gold' : 'text-muted' }}">{{ ucfirst($collab->status) }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('collaborators.destroy', [$wedding, $collab]) }}" onsubmit="return confirm('Remove this collaborator?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-muted hover:text-red-400">Remove</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="text-xs text-muted mt-4">Invited collaborators get access automatically when they register with the invited email address.</p>
@endsection
