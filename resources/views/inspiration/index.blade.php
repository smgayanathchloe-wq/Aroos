@extends('layouts.app')

@section('title', 'Inspiration')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Moodboard</p>
        <h1 class="font-serif text-3xl mt-1">Inspiration</h1>
    </div>
</div>

<div class="card p-6 mb-8">
    <p class="eyebrow mb-4">Add inspiration</p>
    <form method="POST" action="{{ route('inspiration.store', $wedding) }}" enctype="multipart/form-data" class="grid md:grid-cols-4 gap-3">
        @csrf
        <input name="title" placeholder="Title (e.g. Tablescape mood)" class="lux-input">
        <input type="file" name="file" accept="image/*" class="lux-input">
        <input name="url" placeholder="…or paste an image / video URL" class="lux-input">
        <button class="btn-gold">Add</button>
        <textarea name="notes" rows="2" placeholder="Notes…" class="lux-input md:col-span-4"></textarea>
    </form>
</div>

<div class="columns-2 md:columns-3 lg:columns-4 gap-4 space-y-4">
    @forelse($inspirations as $item)
        <div class="card overflow-hidden break-inside-avoid">
            @if($item->file_path)
                <img src="{{ asset('storage/'.$item->file_path) }}" alt="{{ $item->title }}" class="w-full">
            @elseif($item->type === 'image' && $item->url)
                <img src="{{ $item->url }}" alt="{{ $item->title }}" class="w-full">
            @elseif($item->type === 'video' && $item->url)
                <a href="{{ $item->url }}" target="_blank" class="block aspect-video bg-ink flex items-center justify-center text-gold">▶ Play video</a>
            @endif
            <div class="p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm">{{ $item->title ?? 'Untitled' }}</h3>
                    <form method="POST" action="{{ route('inspiration.destroy', [$wedding, $item]) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-muted hover:text-red-400">×</button>
                    </form>
                </div>
                @if($item->notes)<p class="text-xs text-muted mt-1">{{ $item->notes }}</p>@endif
            </div>
        </div>
    @empty
        <div class="card p-10 text-center text-muted col-span-full">
            <p class="font-serif text-xl mb-2">Nothing here yet</p>
            <p class="text-sm">Add images or videos to start your moodboard.</p>
        </div>
    @endforelse
</div>
@endsection
