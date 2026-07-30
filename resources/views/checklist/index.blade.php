@extends('layouts.app')

@section('title', 'Checklist')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Plan</p>
        <h1 class="font-serif text-3xl mt-1">Wedding Checklist</h1>
    </div>
    <form method="POST" action="{{ route('checklist.phases.store', $wedding) }}" class="flex gap-2">
        @csrf
        <input name="name" placeholder="New phase name…" required class="lux-input w-52">
        <button class="btn-ghost">Add phase</button>
    </form>
</div>

@php
    $total = $phases->flatMap->tasks->count();
    $done = $phases->flatMap->tasks->where('completed', true)->count();
@endphp
<div class="card p-4 mb-8 flex items-center gap-4">
    <div class="flex-1 h-2 rounded-full bg-ink overflow-hidden">
        <div class="h-full gold-gradient" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></div>
    </div>
    <span class="text-sm text-muted">{{ $done }}/{{ $total }} done</span>
</div>

<div class="space-y-6">
    @foreach($phases as $phase)
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-serif text-xl">{{ $phase->name }}</h2>
                <div class="flex items-center gap-3 text-xs text-muted">
                    <span>{{ $phase->tasks->where('completed', true)->count() }}/{{ $phase->tasks->count() }}</span>
                    <form method="POST" action="{{ route('checklist.phases.destroy', [$wedding, $phase]) }}" onsubmit="return confirm('Delete this phase and its tasks?')">
                        @csrf @method('DELETE')
                        <button class="hover:text-red-400">Delete</button>
                    </form>
                </div>
            </div>
            <ul class="space-y-1.5">
                @foreach($phase->tasks as $task)
                    <li class="flex items-center gap-3 group">
                        <form method="POST" action="{{ route('checklist.tasks.toggle', [$wedding, $task]) }}">
                            @csrf @method('PATCH')
                            <button class="w-5 h-5 rounded border {{ $task->completed ? 'gold-gradient border-gold text-ink' : 'border-line' }} text-xs leading-none">{{ $task->completed ? '✓' : '' }}</button>
                        </form>
                        <span class="text-sm flex-1 {{ $task->completed ? 'line-through text-muted' : '' }}">{{ $task->title }}</span>
                        @if($task->due_date)
                            <span class="text-xs text-muted">{{ $task->due_date->format('M j') }}</span>
                        @endif
                        <form method="POST" action="{{ route('checklist.tasks.destroy', [$wedding, $task]) }}" class="opacity-0 group-hover:opacity-100 transition">
                            @csrf @method('DELETE')
                            <button class="text-xs text-muted hover:text-red-400">×</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('checklist.tasks.store', [$wedding, $phase]) }}" class="flex gap-2 mt-4">
                @csrf
                <input name="title" placeholder="New task…" required class="lux-input flex-1">
                <input type="date" name="due_date" class="lux-input w-40">
                <button class="btn-ghost">Add</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
