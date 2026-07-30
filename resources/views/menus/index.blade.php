@extends('layouts.app')

@section('title', 'Menus')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">On the menu</p>
        <h1 class="font-serif text-3xl mt-1">Menus</h1>
    </div>
    <form method="POST" action="{{ route('menus.store', $wedding) }}" class="flex gap-2">
        @csrf
        <input name="name" placeholder="Menu name (Dinner, Cocktails…)" required class="lux-input w-64">
        <button class="btn-gold">Add menu</button>
    </form>
</div>

<div class="grid md:grid-cols-2 gap-6">
    @forelse($menus as $menu)
        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-serif text-xl">{{ $menu->name }}</h2>
                <form method="POST" action="{{ route('menus.destroy', [$wedding, $menu]) }}" onsubmit="return confirm('Delete this menu?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-muted hover:text-red-400">Delete</button>
                </form>
            </div>
            @php $courses = $menu->items->groupBy('course'); @endphp
            @foreach($courses as $course => $items)
                @if($course)<p class="eyebrow mt-3 mb-1">{{ $course }}</p>@endif
                <ul class="space-y-1.5">
                    @foreach($items as $item)
                        <li class="flex justify-between text-sm group">
                            <div>
                                <span>{{ $item->name }}</span>
                                @if($item->description)<p class="text-xs text-muted">{{ $item->description }}</p>@endif
                            </div>
                            <form method="POST" action="{{ route('menus.items.destroy', [$wedding, $menu, $item]) }}" class="opacity-0 group-hover:opacity-100">
                                @csrf @method('DELETE')
                                <button class="text-xs text-muted hover:text-red-400">×</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endforeach
            <form method="POST" action="{{ route('menus.items.store', [$wedding, $menu]) }}" class="grid grid-cols-3 gap-2 mt-4">
                @csrf
                <input name="name" placeholder="Dish name" required class="lux-input">
                <select name="course" class="lux-input">
                    <option value="">Course…</option>
                    @foreach(['Starter', 'Main', 'Dessert', 'Drinks'] as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
                <input name="description" placeholder="Description" class="lux-input">
                <button class="btn-ghost text-xs col-span-3 justify-self-start">Add item</button>
            </form>
        </div>
    @empty
        <div class="card p-10 text-center text-muted md:col-span-2">
            <p class="font-serif text-xl mb-2">No menus yet</p>
            <p class="text-sm">Create menus for dinner, cocktails, kids and more. They can be shown on your public wedding site.</p>
        </div>
    @endforelse
</div>
@endsection
