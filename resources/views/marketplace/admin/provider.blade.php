@extends('layouts.app')

@section('title', $provider->business_name)

@use('App\Marketplace\Money')

@section('content')
<a href="{{ route('admin.marketplace.index') }}" class="text-xs text-muted">← Marketplace admin</a>
<div class="flex flex-wrap items-start justify-between gap-4 mt-2 mb-6">
    <div>
        <h1 class="font-serif text-3xl">{{ $provider->business_name }}</h1>
        <p class="text-sm text-muted">{{ ucfirst($provider->business_type) }} · {{ $provider->categories->pluck('name')->join(', ') }} · {{ $provider->region?->name }}</p>
        <div class="flex gap-2 mt-2 text-xs">@include('marketplace.provider._badges', ['provider' => $provider])</div>
    </div>
    <a href="{{ route('marketplace.provider', $provider) }}" class="btn-ghost">View profile</a>
</div>

<div class="grid lg:grid-cols-[1fr_360px] gap-6">
    <div class="space-y-6">
        <div class="card p-5">
            <p class="eyebrow mb-3">Verification submissions</p>
            @forelse($provider->verifications as $v)
                <div class="border-t border-line first:border-0 py-3 text-sm">
                    <p>{{ $v->created_at->format('j M Y H:i') }} · <strong>{{ str_replace('_', ' ', $v->status) }}</strong></p>
                    <p>Representative: {{ $v->representative_name }}{{ $v->representative_id_number ? ' · ID '.$v->representative_id_number : '' }}</p>
                    @if($v->registration_number)<p>Registration: {{ $v->registration_number }}</p>@endif
                    @if($v->notes)<p class="text-muted">{{ $v->notes }}</p>@endif
                    <ul class="text-xs mt-1">@foreach($v->documents as $d)<li><a href="{{ route('provider.verification.document', $d) }}" class="underline">{{ $d->original_name }}</a> ({{ round($d->size / 1024) }} KB)</li>@endforeach</ul>
                </div>
            @empty
                <p class="text-sm text-muted">No documents submitted.</p>
            @endforelse
        </div>

        <div class="card p-5">
            <p class="eyebrow mb-3">Contact</p>
            <p class="text-sm">{{ $provider->contact_name }} · {{ $provider->phone }} · {{ $provider->email }}</p>
            <p class="text-sm text-muted">Owner account: {{ $provider->owner?->name }} ({{ $provider->owner?->email }})</p>
        </div>

        <div class="card p-5">
            <p class="eyebrow mb-3">Services</p>
            @foreach($provider->services as $s)
                <p class="text-sm">{{ $s->title }} · {{ $s->status }} · {{ $s->packages->count() }} package(s){{ $s->startingPrice() !== null ? ' · from '.Money::format($s->startingPrice()) : '' }}</p>
            @endforeach
        </div>

        <div class="card p-5">
            <p class="eyebrow mb-3">Media moderation</p>
            <div class="grid grid-cols-3 md:grid-cols-4 gap-3">
                @foreach($provider->media as $m)
                    <div class="text-xs">
                        @if($m->type === 'image')<img src="{{ $m->fileUrl() }}" alt="" class="h-24 w-full object-cover rounded {{ $m->moderation === 'hidden' ? 'opacity-30' : '' }}">
                        @else<a href="{{ $m->url ?? $m->fileUrl() }}" target="_blank" rel="noopener noreferrer" class="flex h-24 items-center justify-center border rounded">▶ {{ $m->platform() ?? 'Video' }}</a>@endif
                        <form method="POST" action="{{ route('admin.marketplace.media', $m) }}">@csrf
                            <input type="hidden" name="moderation" value="{{ $m->moderation === 'hidden' ? 'visible' : 'hidden' }}">
                            <button class="mt-1 {{ $m->moderation === 'hidden' ? 'text-gold' : 'text-red-500' }}">{{ $m->moderation === 'hidden' ? 'Restore' : 'Hide' }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <aside class="space-y-4">
        <form method="POST" action="{{ route('admin.marketplace.review', $provider) }}" class="card p-5 space-y-2">@csrf
            <p class="eyebrow">Verification decision</p>
            <select name="decision" class="lux-input" aria-label="Decision">
                <option value="under_review">Mark under review</option>
                <option value="verified">Verify</option>
                <option value="rejected">Reject</option>
            </select>
            <input name="reason" placeholder="Reason (required to reject; sent to provider)" class="lux-input" aria-label="Reason">
            <button class="btn-gold w-full">Save decision</button>
        </form>
        @if($provider->isBanned())
            <form method="POST" action="{{ route('admin.marketplace.unban', $provider) }}" class="card p-5">@csrf
                <p class="text-sm mb-2">Banned: {{ $provider->ban_reason }}</p>
                <button class="btn-ghost w-full">Lift ban</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.marketplace.ban', $provider) }}" class="card p-5 space-y-2" onsubmit="return confirm('Ban this provider? They disappear from the marketplace and cannot take requests.')">@csrf
                <p class="eyebrow">Ban provider</p>
                <input name="reason" required placeholder="Reason (sent to provider)" class="lux-input" aria-label="Ban reason">
                <button class="w-full rounded-lg border border-red-400 text-red-500 py-2 text-sm">Ban</button>
            </form>
        @endif
        <div class="card p-5">
            <p class="eyebrow mb-2">Audit log</p>
            @forelse($audit as $a)
                <p class="text-xs py-0.5">{{ $a->created_at->format('j M H:i') }} · {{ $a->action }}{{ ($a->data['reason'] ?? null) ? ' — '.$a->data['reason'] : '' }}</p>
            @empty
                <p class="text-xs text-muted">No entries.</p>
            @endforelse
        </div>
    </aside>
</div>
@endsection
