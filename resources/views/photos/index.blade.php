@extends('layouts.app')

@section('title', 'Photos & Albums')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Gallery</p>
        <h1 class="font-serif text-3xl mt-1 text-inktext">Photos & Albums</h1>
        <p class="text-sm text-muted mt-2 max-w-2xl">Organise photos into albums by event or location (Church, Reception, Beach shoot…). Public albums appear on your after-wedding website, each with its own style.</p>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
    <div class="card p-6">
        <p class="eyebrow mb-4">New album</p>
        <form method="POST" action="{{ route('photos.albums.store', $wedding) }}" class="grid grid-cols-2 gap-3">
            @csrf
            <input name="name" placeholder="Album name (e.g. Church) *" required class="lux-input">
            <input name="description" placeholder="Short description" class="lux-input">
            <select name="style" class="lux-input">
                <option value="grid">Style: Grid</option>
                <option value="masonry">Style: Masonry</option>
                <option value="carousel">Style: Carousel</option>
            </select>
            <input type="color" name="accent_color" value="#B08D57" title="Album accent color" class="lux-input h-10">
            <button class="btn-gold col-span-2">Create album</button>
        </form>
    </div>
    <div class="card p-6">
        <p class="eyebrow mb-4">Upload photos</p>
        <form method="POST" action="{{ route('photos.store', $wedding) }}" enctype="multipart/form-data" class="grid gap-3">
            @csrf
            <input type="file" name="photos[]" accept="image/*" multiple required class="lux-input">
            <div class="grid grid-cols-2 gap-3">
                <select name="album_id" class="lux-input">
                    <option value="">No album (unfiled)</option>
                    @foreach($albums as $album)
                        <option value="{{ $album->id }}">{{ $album->name }}</option>
                    @endforeach
                </select>
                <input name="caption" placeholder="Caption (optional)" class="lux-input">
            </div>
            <button class="btn-gold justify-self-start">Upload</button>
        </form>
    </div>
</div>

@foreach($albums as $album)
    <div class="card p-6 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full" style="background: {{ $album->accent_color ?: '#B08D57' }};"></span>
                <h2 class="font-serif text-xl text-inktext">{{ $album->name }}</h2>
                <span class="text-xs text-muted">{{ $album->photos->count() }} photos · {{ $album->style }} {{ $album->public ? '· shown on website' : '· hidden' }}</span>
            </div>
            <details class="relative">
                <summary class="cursor-pointer text-xs text-muted hover:text-gold list-none">Edit album ▾</summary>
                <div class="absolute right-0 z-40 mt-2 w-80 card p-4 shadow-xl">
                    <form method="POST" action="{{ route('photos.albums.update', [$wedding, $album]) }}" class="grid grid-cols-2 gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $album->name }}" required class="lux-input col-span-2">
                        <input name="description" value="{{ $album->description }}" placeholder="Description" class="lux-input col-span-2">
                        <select name="style" class="lux-input">
                            @foreach(['grid', 'masonry', 'carousel'] as $s)
                                <option value="{{ $s }}" @selected($album->style === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                        <input type="color" name="accent_color" value="{{ $album->accent_color ?: '#B08D57' }}" class="lux-input h-10">
                        <label class="flex items-center gap-2 text-xs col-span-2"><input type="checkbox" name="public" value="1" @checked($album->public) class="accent-[#B08D57]"> Show on public website (after mode)</label>
                        <button class="btn-gold col-span-2">Save</button>
                    </form>
                    <form method="POST" action="{{ route('photos.albums.destroy', [$wedding, $album]) }}" onsubmit="return confirm('Delete album? Photos become unfiled.')" class="mt-2">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-400 hover:underline">Delete album</button>
                    </form>
                </div>
            </details>
        </div>
        <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-3">
            @forelse($album->photos as $photo)
                <div class="relative group rounded-lg overflow-hidden">
                    <img src="{{ asset('storage/'.$photo->file_path) }}" class="w-full aspect-square object-cover">
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center gap-1 p-2">
                        <form method="POST" action="{{ route('photos.move', [$wedding, $photo]) }}">
                            @csrf @method('PUT')
                            <select name="album_id" class="text-xs rounded px-1 py-0.5" onchange="this.form.submit()">
                                <option value="">Unfiled</option>
                                @foreach($albums as $a)
                                    <option value="{{ $a->id }}" @selected($photo->album_id === $a->id)>{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('photos.destroy', [$wedding, $photo]) }}" onsubmit="return confirm('Delete photo?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-300">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-xs text-muted col-span-full">No photos yet — upload above and choose this album.</p>
            @endforelse
        </div>
    </div>
@endforeach

@if($unfiled->count())
    <div class="card p-6">
        <h2 class="font-serif text-xl text-inktext mb-4">Unfiled photos <span class="text-xs text-muted font-sans">{{ $unfiled->count() }}</span></h2>
        <div class="grid grid-cols-3 md:grid-cols-5 lg:grid-cols-6 gap-3">
            @foreach($unfiled as $photo)
                <div class="relative group rounded-lg overflow-hidden">
                    <img src="{{ asset('storage/'.$photo->file_path) }}" class="w-full aspect-square object-cover">
                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex flex-col items-center justify-center gap-1 p-2">
                        <form method="POST" action="{{ route('photos.move', [$wedding, $photo]) }}">
                            @csrf @method('PUT')
                            <select name="album_id" class="text-xs rounded px-1 py-0.5" onchange="this.form.submit()">
                                <option value="">Unfiled</option>
                                @foreach($albums as $a)
                                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                                @endforeach
                            </select>
                        </form>
                        <form method="POST" action="{{ route('photos.destroy', [$wedding, $photo]) }}" onsubmit="return confirm('Delete photo?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-300">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
