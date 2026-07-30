@extends('layouts.app')

@section('title', 'Photos')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Gallery</p>
        <h1 class="font-serif text-3xl mt-1">Photos</h1>
    </div>
    <form method="POST" action="{{ route('photos.store', $wedding) }}" enctype="multipart/form-data" class="flex gap-2">
        @csrf
        <input type="file" name="photos[]" accept="image/*" multiple required class="lux-input">
        <button class="btn-gold">Upload</button>
    </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
    @forelse($photos as $photo)
        <div class="card overflow-hidden group relative">
            <img src="{{ asset('storage/'.$photo->file_path) }}" class="w-full aspect-square object-cover">
            <div class="absolute inset-0 bg-ink/70 opacity-0 group-hover:opacity-100 transition flex items-end justify-between p-3">
                <span class="text-xs">{{ $photo->uploaded_by }}</span>
                <form method="POST" action="{{ route('photos.destroy', [$wedding, $photo]) }}" onsubmit="return confirm('Remove this photo?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-300">Delete</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card p-10 text-center text-muted col-span-full">
            <p class="font-serif text-xl mb-2">No photos yet</p>
            <p class="text-sm">Upload engagement photos, venue visits, or anything worth keeping.</p>
        </div>
    @endforelse
</div>
@endsection
