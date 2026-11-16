@extends('layouts.app')

@section('title', 'Reports & reviews')

@section('content')
<a href="{{ route('admin.marketplace.index') }}" class="text-xs text-muted">← Marketplace admin</a>
<h1 class="font-serif text-3xl mt-2 mb-6">Reports & reviews</h1>

<div class="flex gap-1 mb-3">
    @foreach(['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed'] as $v => $l)
        <a href="{{ route('admin.marketplace.reports', ['status' => $v]) }}" class="text-xs px-3 py-1 rounded-full border {{ $status === $v ? 'border-gold text-gold' : 'border-line text-muted' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="card divide-y divide-line mb-10">
    @forelse($reports as $r)
        @php $subject = $r->subject(); @endphp
        <div class="p-5 text-sm">
            <p><strong>{{ ucfirst($r->reportable_type) }}</strong>
                @if($subject instanceof \App\Models\Marketplace\ProviderProfile) <a href="{{ route('admin.marketplace.provider', $subject) }}" class="underline">{{ $subject->business_name }}</a>
                @elseif($subject instanceof \App\Models\Marketplace\ProviderReview) on <a href="{{ route('admin.marketplace.provider', $subject->provider) }}" class="underline">{{ $subject->provider?->business_name }}</a>: “{{ \Illuminate\Support\Str::limit($subject->body, 120) }}” ({{ $subject->overall }}★, {{ $subject->status }})
                @elseif($subject) #{{ $subject->id }} @else <span class="text-muted">(deleted)</span> @endif
            </p>
            <p class="text-muted mt-1">“{{ $r->reason }}” — {{ $r->reporter?->name }}, {{ $r->created_at->format('j M Y') }}</p>
            @if($r->status === 'open')
                <form method="POST" action="{{ route('admin.marketplace.reports.resolve', $r) }}" class="flex flex-wrap gap-2 mt-3 items-center">@csrf
                    <input name="resolution" placeholder="Note" class="lux-input w-64 py-1" aria-label="Resolution note">
                    @if($r->reportable_type === 'review')<label class="text-xs flex items-center gap-1"><input type="checkbox" name="action" value="hide_review"> Hide the review</label>@endif
                    @if($r->reportable_type === 'media')<label class="text-xs flex items-center gap-1"><input type="checkbox" name="action" value="hide_media"> Hide the media</label>@endif
                    <button name="status" value="resolved" class="btn-gold text-xs">Resolve</button>
                    <button name="status" value="dismissed" class="btn-ghost text-xs">Dismiss</button>
                </form>
            @else
                <p class="text-xs text-muted mt-2">{{ ucfirst($r->status) }}{{ $r->resolution ? ': '.$r->resolution : '' }}</p>
            @endif
        </div>
    @empty
        <p class="p-8 text-center text-muted">No {{ $status }} reports.</p>
    @endforelse
</div>
{{ $reports->links() }}

<h2 class="font-serif text-2xl mb-3">Latest reviews</h2>
<div class="card divide-y divide-line">
    @forelse($reviews as $rv)
        <div class="p-4 text-sm flex justify-between gap-4">
            <div><p>{{ $rv->overall }}★ · {{ $rv->provider?->business_name }} · {{ $rv->customer?->name }} · <span class="text-muted">{{ $rv->created_at->format('j M Y') }}</span></p>
                @if($rv->body)<p class="text-muted">{{ \Illuminate\Support\Str::limit($rv->body, 200) }}</p>@endif</div>
            <form method="POST" action="{{ route('admin.marketplace.reviews.moderate', $rv) }}">@csrf
                <input type="hidden" name="status" value="{{ $rv->status === 'published' ? 'hidden' : 'published' }}">
                <button class="text-xs {{ $rv->status === 'published' ? 'text-red-500' : 'text-gold' }}">{{ $rv->status === 'published' ? 'Hide' : 'Restore' }}</button>
            </form>
        </div>
    @empty
        <p class="p-8 text-center text-muted">No reviews yet.</p>
    @endforelse
</div>
@endsection
