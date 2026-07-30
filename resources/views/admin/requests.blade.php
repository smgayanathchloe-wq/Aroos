@extends('layouts.app')

@section('title', 'Requests')

@section('content')
<div class="mb-8">
    <p class="eyebrow">From the landing page</p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">Contact Requests</h1>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs text-muted" style="border-color:#E8E2D9;">
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3">Package</th>
                <th class="px-4 py-3">Wedding date</th>
                <th class="px-4 py-3">Message</th>
                <th class="px-4 py-3">Received</th>
                <th class="px-4 py-3">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($requests as $req)
                <tr class="border-b hover:bg-canvas" style="border-color:#F0E9DC;">
                    <td class="px-4 py-3">
                        <p class="text-inktext">{{ $req->name }}</p>
                        <p class="text-xs text-muted">{{ $req->email }}{{ $req->phone ? ' · '.$req->phone : '' }}</p>
                    </td>
                    <td class="px-4 py-3">
                        @if($req->package)<span class="text-xs px-2.5 py-1 rounded-full" style="background:rgba(176,141,87,.12); color:#8a6d3f;">{{ ucfirst($req->package) }}</span>@else<span class="text-xs text-muted">—</span>@endif
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">{{ optional($req->wedding_date)->format('M j, Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-muted max-w-xs">{{ Str::limit($req->message, 100) }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $req->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.requests.update', $req) }}">
                            @csrf @method('PUT')
                            <select name="status" class="lux-input w-32 text-xs" onchange="this.form.submit()">
                                <option value="new" @selected($req->status === 'new')>New</option>
                                <option value="contacted" @selected($req->status === 'contacted')>Contacted</option>
                                <option value="closed" @selected($req->status === 'closed')>Closed</option>
                            </select>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-muted">No requests yet. They will arrive from the landing page contact form.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
