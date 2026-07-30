@extends('layouts.app')

@section('title', 'Admin')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Platform</p>
    <h1 class="font-serif text-3xl mt-1">Admin Panel</h1>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <div class="card p-5"><p class="eyebrow">Total users</p><p class="font-serif text-3xl mt-2">{{ $stats['users'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Planners</p><p class="font-serif text-3xl mt-2">{{ $stats['planners'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Couples</p><p class="font-serif text-3xl mt-2">{{ $stats['couples'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Weddings</p><p class="font-serif text-3xl mt-2 text-gold">{{ $stats['weddings'] }}</p></div>
</div>

<div class="card p-6 mb-10">
    <p class="eyebrow mb-4">Create user</p>
    <form method="POST" action="{{ route('admin.users.store') }}" class="grid md:grid-cols-5 gap-3">
        @csrf
        <input name="name" placeholder="Name *" required class="lux-input">
        <input type="email" name="email" placeholder="Email *" required class="lux-input">
        <input name="password" placeholder="Password *" required class="lux-input">
        <select name="role" class="lux-input">
            <option value="planner">Planner</option>
            <option value="couple">Couple</option>
            <option value="super_admin">Super Admin</option>
        </select>
        <button class="btn-gold">Create</button>
    </form>
    <p class="text-xs text-muted mt-3">Planners can create and manage weddings. Couples get view-only access to weddings shared with them (invite them as collaborators). Super admins can do everything.</p>
</div>

<p class="eyebrow mb-4">Users</p>
<div class="card overflow-x-auto mb-10">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Weddings</th>
                <th class="px-4 py-3">Joined</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $u)
                <tr class="border-b border-line/50 hover:bg-ink/40">
                    <td class="px-4 py-3">
                        <p>{{ $u->name }} @if($u->id === auth()->id())<span class="text-xs text-gold">(you)</span>@endif</p>
                        <p class="text-xs text-muted">{{ $u->email }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex items-center gap-2">
                            @csrf @method('PUT')
                            <select name="role" class="lux-input w-36 text-xs" onchange="this.form.submit()">
                                <option value="super_admin" @selected($u->role === 'super_admin')>Super Admin</option>
                                <option value="planner" @selected($u->role === 'planner')>Planner</option>
                                <option value="couple" @selected($u->role === 'couple')>Couple</option>
                            </select>
                        </form>
                    </td>
                    <td class="px-4 py-3">{{ $u->weddings_count }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $u->created_at->format('M j, Y') }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($u->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Delete {{ $u->email }} and all their data?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-muted hover:text-red-400">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p class="eyebrow mb-4">All weddings</p>
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">Couple</th>
                <th class="px-4 py-3">Owner</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Venue</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach($weddings as $w)
                <tr class="border-b border-line/50 hover:bg-ink/40">
                    <td class="px-4 py-3">{{ $w->coupleNames() }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $w->user->name }} ({{ $w->user->role }})</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ optional($w->wedding_date)->format('M j, Y') ?? 'TBA' }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $w->venue_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('wedding.dashboard', $w) }}" class="text-xs text-gold hover:underline">Open</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
