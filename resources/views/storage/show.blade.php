@extends('layouts.app')

@section('title', 'Storage Management')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Wedding Settings</p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">Storage Management</h1>
    <p class="text-sm text-muted mt-2">Manage your photo, video, and media storage for this wedding.</p>
</div>

<div class="grid md:grid-cols-3 gap-6 mb-8">
    <div class="card p-6">
        <p class="eyebrow mb-2">Current Usage</p>
        <p class="font-serif text-3xl text-inktext">{{ number_format($percentUsed, 1) }}%</p>
        <p class="text-xs text-muted mt-2">{{ format_bytes($storagePlan->used_bytes) }} of {{ format_bytes($storagePlan->total_bytes) }}</p>
        
        <div class="w-full bg-neutral-200 rounded-full h-2 mt-4 overflow-hidden">
            <div class="bg-gold h-2 rounded-full transition-all" style="width: {{ min($percentUsed, 100) }}%"></div>
        </div>
    </div>

    <div class="card p-6">
        <p class="eyebrow mb-2">Current Plan</p>
        <p class="font-serif text-3xl text-inktext capitalize">{{ str_replace('gb', ' GB', $storagePlan->plan_type) }}</p>
        <p class="text-xs text-muted mt-2">
            @switch($storagePlan->plan_type)
                @case('free')
                    Free tier - 2 GB
                    @break
                @case('5gb')
                    Premium - 5 GB
                    @break
                @case('10gb')
                    Professional - 10 GB
                    @break
                @case('20gb')
                    Studio - 20 GB
                    @break
                @case('50gb')
                    Enterprise - 50 GB
                    @break
            @endswitch
        </p>
    </div>

    <div class="card p-6">
        <p class="eyebrow mb-2">Photos & Videos</p>
        <p class="font-serif text-3xl text-inktext">{{ $wedding->photos()->count() }}</p>
        <p class="text-xs text-muted mt-2">Total media files</p>
    </div>
</div>

<div class="card p-6 mb-8">
    <h2 class="font-serif text-xl text-inktext mb-4">Upgrade Storage Plan</h2>
    <p class="text-sm text-muted mb-6">Choose a storage plan that fits your wedding's needs.</p>

    <form method="POST" action="{{ route('storage.update', $wedding) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="grid md:grid-cols-5 gap-4">
            <label class="relative cursor-pointer">
                <input type="radio" name="plan_type" value="free" {{ $storagePlan->plan_type === 'free' ? 'checked' : '' }} class="sr-only peer">
                <div class="peer-checked:border-gold peer-checked:bg-gold/10 p-4 border-2 border-neutral-200 rounded-lg text-center transition">
                    <p class="font-semibold text-inktext">Free</p>
                    <p class="text-xs text-muted">2 GB</p>
                </div>
            </label>

            <label class="relative cursor-pointer">
                <input type="radio" name="plan_type" value="5gb" {{ $storagePlan->plan_type === '5gb' ? 'checked' : '' }} class="sr-only peer">
                <div class="peer-checked:border-gold peer-checked:bg-gold/10 p-4 border-2 border-neutral-200 rounded-lg text-center transition">
                    <p class="font-semibold text-inktext">Premium</p>
                    <p class="text-xs text-muted">5 GB</p>
                </div>
            </label>

            <label class="relative cursor-pointer">
                <input type="radio" name="plan_type" value="10gb" {{ $storagePlan->plan_type === '10gb' ? 'checked' : '' }} class="sr-only peer">
                <div class="peer-checked:border-gold peer-checked:bg-gold/10 p-4 border-2 border-neutral-200 rounded-lg text-center transition">
                    <p class="font-semibold text-inktext">Professional</p>
                    <p class="text-xs text-muted">10 GB</p>
                </div>
            </label>

            <label class="relative cursor-pointer">
                <input type="radio" name="plan_type" value="20gb" {{ $storagePlan->plan_type === '20gb' ? 'checked' : '' }} class="sr-only peer">
                <div class="peer-checked:border-gold peer-checked:bg-gold/10 p-4 border-2 border-neutral-200 rounded-lg text-center transition">
                    <p class="font-semibold text-inktext">Studio</p>
                    <p class="text-xs text-muted">20 GB</p>
                </div>
            </label>

            <label class="relative cursor-pointer">
                <input type="radio" name="plan_type" value="50gb" {{ $storagePlan->plan_type === '50gb' ? 'checked' : '' }} class="sr-only peer">
                <div class="peer-checked:border-gold peer-checked:bg-gold/10 p-4 border-2 border-neutral-200 rounded-lg text-center transition">
                    <p class="font-semibold text-inktext">Enterprise</p>
                    <p class="text-xs text-muted">50 GB</p>
                </div>
            </label>
        </div>

        <button type="submit" class="btn-primary mt-6">Update Plan</button>
    </form>
</div>

<div class="card p-6">
    <h2 class="font-serif text-xl text-inktext mb-4">Storage Breakdown</h2>
    <div class="space-y-3 text-sm">
        <div class="flex justify-between items-center">
            <span class="text-muted">📸 Photos</span>
            <span class="text-inktext font-medium">{{ $wedding->photos()->count() }} items</span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-muted">💿 Albums</span>
            <span class="text-inktext font-medium">{{ $wedding->albums()->count() }} albums</span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-muted">📝 Memories</span>
            <span class="text-inktext font-medium">{{ $wedding->memories()->count() }} memories</span>
        </div>
    </div>
</div>
@endsection

@php
function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
@endphp
