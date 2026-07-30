@extends('layouts.app')

@section('title', 'Budget')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Tariff</p>
        <h1 class="font-serif text-3xl mt-1">Budget</h1>
    </div>
    <form method="POST" action="{{ route('budget.total', $wedding) }}" class="flex gap-2 items-center">
        @csrf @method('PUT')
        <span class="text-xs text-muted">Total budget ({{ $wedding->currency }})</span>
        <input type="number" step="0.01" name="total_budget" value="{{ $wedding->total_budget }}" class="lux-input w-40">
        <button class="btn-ghost">Save</button>
    </form>
</div>

@php
    $budgeted = $categories->sum('budgeted');
    $spent = $categories->sum('spent');
    $remaining = (float) $wedding->total_budget - $spent;
@endphp
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Total budget</p><p class="font-serif text-2xl mt-2">{{ number_format((float) $wedding->total_budget) }}</p></div>
    <div class="card p-5"><p class="eyebrow">Allocated</p><p class="font-serif text-2xl mt-2">{{ number_format($budgeted) }}</p></div>
    <div class="card p-5"><p class="eyebrow">Spent</p><p class="font-serif text-2xl mt-2">{{ number_format($spent) }}</p></div>
    <div class="card p-5"><p class="eyebrow">Remaining</p><p class="font-serif text-2xl mt-2 {{ $remaining < 0 ? 'text-red-400' : 'text-gold' }}">{{ number_format($remaining) }}</p></div>
</div>

<div class="card p-6 mb-8">
    <p class="eyebrow mb-4">Add category</p>
    <form method="POST" action="{{ route('budget.store', $wedding) }}" class="grid md:grid-cols-5 gap-3">
        @csrf
        <input name="name" placeholder="Category name" required class="lux-input md:col-span-2">
        <input type="number" step="0.01" name="budgeted" placeholder="Budgeted" class="lux-input">
        <input type="number" step="0.01" name="spent" placeholder="Spent" class="lux-input">
        <button class="btn-gold">Add Category</button>
    </form>
</div>

<div class="space-y-3">
    @forelse($categories as $cat)
        @php $pct = (float) $cat->budgeted > 0 ? min(100, round((float) $cat->spent / (float) $cat->budgeted * 100)) : 0; @endphp
        <div class="card p-5">
            <form method="POST" action="{{ route('budget.update', [$wedding, $cat]) }}" class="grid md:grid-cols-6 gap-3 items-center">
                @csrf @method('PUT')
                <input name="name" value="{{ $cat->name }}" required class="lux-input md:col-span-2">
                <input type="number" step="0.01" name="budgeted" value="{{ $cat->budgeted }}" class="lux-input" title="Budgeted">
                <input type="number" step="0.01" name="spent" value="{{ $cat->spent }}" class="lux-input" title="Spent">
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-ink overflow-hidden"><div class="h-full {{ $pct >= 100 ? 'bg-red-400' : 'gold-gradient' }}" style="width: {{ $pct }}%"></div></div>
                    <span class="text-xs text-muted w-9">{{ $pct }}%</span>
                </div>
                <div class="flex gap-2 justify-end">
                    <button class="btn-ghost text-xs">Save</button>
                    <button formaction="{{ route('budget.destroy', [$wedding, $cat]) }}" formmethod="POST" name="_method" value="DELETE" onclick="return confirm('Remove this category?')" class="btn-ghost text-xs hover:border-red-400 hover:text-red-400">×</button>
                </div>
            </form>
        </div>
    @empty
        <p class="text-muted text-sm">No categories yet. Add your first above.</p>
    @endforelse
</div>
@endsection
