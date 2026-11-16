@extends('layouts.app')

@section('title', 'Marketplace categories')

@section('content')
<a href="{{ route('admin.marketplace.index') }}" class="text-xs text-muted">← Marketplace admin</a>
<h1 class="font-serif text-3xl mt-2 mb-6">Service categories</h1>

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    <div class="space-y-3">
        @foreach($categories as $c)
            <form method="POST" action="{{ route('admin.marketplace.categories.update', $c) }}" enctype="multipart/form-data" class="card p-4 grid gap-2 md:grid-cols-6 items-center {{ $c->parent_id ? 'ml-6' : '' }}">@csrf @method('PUT')
                <input name="name" value="{{ $c->name }}" required class="lux-input md:col-span-2" aria-label="Name">
                <input name="name_sw" value="{{ $c->name_sw }}" placeholder="Kiswahili" class="lux-input" aria-label="Kiswahili name">
                <select name="scheduling_default" class="lux-input" aria-label="Default scheduling">@foreach(['per_day' => 'Per day', 'per_slot' => 'Time slots'] as $v => $l)<option value="{{ $v }}" @selected($c->scheduling_default === $v)>{{ $l }}</option>@endforeach</select>
                <input type="number" name="sort" value="{{ $c->sort }}" class="lux-input" aria-label="Order">
                <label class="text-xs flex items-center gap-1"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)> Active · {{ $c->providers_count }} providers</label>
                <input name="description" value="{{ $c->description }}" placeholder="Description" class="lux-input md:col-span-3" aria-label="Description">
                <select name="parent_id" class="lux-input md:col-span-1" aria-label="Parent"><option value="">Top level</option>@foreach($categories->whereNull('parent_id')->where('id', '!=', $c->id) as $p)<option value="{{ $p->id }}" @selected($c->parent_id === $p->id)>↳ {{ $p->name }}</option>@endforeach</select>
                <input type="file" name="image" accept="image/*" class="text-xs" aria-label="Image">
                <button class="btn-gold">Save</button>
            </form>
        @endforeach
    </div>
    <form method="POST" action="{{ route('admin.marketplace.categories.store') }}" enctype="multipart/form-data" class="card p-5 space-y-2 h-fit">@csrf
        <p class="eyebrow">New category or subcategory</p>
        <input name="name" required placeholder="Name" class="lux-input" aria-label="Name">
        <input name="name_sw" placeholder="Kiswahili name" class="lux-input" aria-label="Kiswahili name">
        <select name="parent_id" class="lux-input" aria-label="Parent"><option value="">Top level</option>@foreach($categories->whereNull('parent_id') as $p)<option value="{{ $p->id }}">↳ {{ $p->name }}</option>@endforeach</select>
        <select name="scheduling_default" class="lux-input" aria-label="Default scheduling"><option value="per_day">Per day</option><option value="per_slot">Time slots</option></select>
        <input name="description" placeholder="Description" class="lux-input" aria-label="Description">
        <input type="number" name="sort" placeholder="Order" class="lux-input" aria-label="Order">
        <input type="file" name="image" accept="image/*" class="lux-input" aria-label="Image">
        <button class="btn-gold w-full">Add</button>
    </form>
</div>
@endsection
