@extends('layouts.app')

@section('title', 'Marketplace admin')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4 mb-6">
    <div><p class="eyebrow">Admin</p><h1 class="font-serif text-3xl mt-1">Marketplace</h1></div>
    <div class="flex gap-2">
        <a href="{{ route('admin.marketplace.reports') }}" class="btn-ghost">Reports & reviews</a>
        <a href="{{ route('admin.marketplace.analytics') }}" class="btn-ghost">Analytics</a>
        <a href="{{ route('admin.marketplace.categories') }}" class="btn-ghost">Categories</a>
    </div>
</div>
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @foreach([['Providers', $stats['providers']], ['Listed', $stats['listed']], ['Awaiting review', $stats['queue']], ['Requests (30d)', $stats['enquiries_30d']], ['Bookings (30d)', $stats['bookings_30d']]] as [$l, $v])
        <div class="card p-4"><p class="eyebrow">{{ $l }}</p><p class="font-serif text-2xl mt-2">{{ $v }}</p></div>
    @endforeach
</div>
<form class="flex flex-wrap gap-2 mb-4" method="GET">
    @foreach(['queue' => 'Verification queue', 'listed' => 'Listed', 'banned' => 'Banned', 'all' => 'All'] as $v => $l)
        <a href="{{ route('admin.marketplace.index', ['filter' => $v]) }}" class="text-xs px-3 py-1.5 rounded-full border {{ $filter === $v ? 'border-gold text-gold' : 'border-line text-muted' }}">{{ $l }}</a>
    @endforeach
    <input type="hidden" name="filter" value="all"><input name="q" value="{{ request('q') }}" placeholder="Search name" class="lux-input w-56 py-1" aria-label="Search">
</form>
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="border-b border-line text-left text-xs text-muted"><th class="px-4 py-3">Business</th><th class="px-4 py-3">Owner</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Updated</th></tr></thead>
        <tbody>
        @forelse($providers as $p)
            <tr class="border-b border-line/50">
                <td class="px-4 py-3"><a href="{{ route('admin.marketplace.provider', $p) }}" class="font-medium hover:text-gold">{{ $p->business_name }}</a><p class="text-xs text-muted">{{ $p->region?->name }}</p></td>
                <td class="px-4 py-3 text-xs">{{ $p->owner?->name }}<br>{{ $p->owner?->email }}</td>
                <td class="px-4 py-3 text-xs">{{ $p->primaryCategory?->name }}</td>
                <td class="px-4 py-3 text-xs space-x-1">@include('marketplace.provider._badges', ['provider' => $p])</td>
                <td class="px-4 py-3 text-xs text-muted">{{ $p->updated_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-10 text-center text-muted">Nothing here.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $providers->links() }}</div>
@endsection
