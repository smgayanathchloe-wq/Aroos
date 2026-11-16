@extends('layouts.app')

@section('title', 'Portfolio')

@section('content')
@include('marketplace.provider._nav')

<div class="grid lg:grid-cols-[1fr_340px] gap-6">
    <div>
        @if($media->isEmpty())
            <div class="card p-10 text-center text-muted">Your portfolio is empty. Couples book what they can see — add your best photos and videos.</div>
        @else
            @foreach($media->groupBy(fn ($m) => $m->album ?: 'Gallery') as $album => $items)
                <h2 class="font-serif text-xl mb-3 mt-2">{{ $album }}</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
                    @foreach($items as $m)
                        <div class="card overflow-hidden">
                            @if($m->type === 'image')<img src="{{ $m->fileUrl() }}" alt="{{ $m->caption }}" class="h-36 w-full object-cover" loading="lazy">
                            @elseif($m->type === 'video')<video src="{{ $m->fileUrl() }}" class="h-36 w-full object-cover bg-black" preload="metadata" controls></video>
                            @else<a href="{{ $m->url }}" target="_blank" rel="nofollow noopener noreferrer" class="flex h-36 items-center justify-center text-sm text-gold">▶ {{ $m->platform() ?? 'Video link' }}</a>@endif
                            @if($m->moderation === 'hidden')<p class="text-xs text-red-500 px-3 pt-2">Hidden by Aroos moderation</p>@endif
                            <form method="POST" action="{{ route('provider.media.update', $m) }}" class="p-3 space-y-2">@csrf @method('PUT')
                                <input name="caption" value="{{ $m->caption }}" placeholder="Caption" class="lux-input text-xs" aria-label="Caption">
                                <div class="flex gap-2">
                                    <input name="album" value="{{ $m->album }}" placeholder="Album" class="lux-input text-xs" aria-label="Album">
                                    <input type="number" name="sort" value="{{ $m->sort }}" class="lux-input text-xs w-16" aria-label="Order">
                                </div>
                                <div class="flex justify-between">
                                    <button class="text-xs text-gold">Save</button>
                                    <button formaction="{{ route('provider.media.destroy', $m) }}" name="_method" value="DELETE" class="text-xs text-muted hover:text-red-500" onclick="return confirm('Remove this item?')">Remove</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>

    <div class="space-y-4">
        @foreach(['image' => 'Upload photos', 'video' => 'Upload a video', 'video_link' => 'Link Instagram, TikTok or YouTube'] as $kind => $title)
            <form method="POST" action="{{ route('provider.media.store') }}" enctype="multipart/form-data" class="card p-5 space-y-2">@csrf
                <p class="eyebrow">{{ $title }}</p>
                <input type="hidden" name="kind" value="{{ $kind }}">
                @if($kind === 'video_link')
                    <input name="url" required placeholder="https://www.instagram.com/p/…" class="lux-input" aria-label="Link">
                @else
                    <input type="file" name="file" required accept="{{ $kind === 'image' ? 'image/*' : 'video/mp4,video/webm,video/quicktime' }}" class="lux-input" aria-label="File">
                    <p class="text-xs text-muted">Max {{ $kind === 'image' ? config('marketplace.max_image_mb') : config('marketplace.max_video_mb') }} MB{{ $kind === 'image' ? '. Photos are optimised and location data is removed.' : ', MP4/WebM/MOV.' }}</p>
                @endif
                <input name="caption" placeholder="Caption" class="lux-input" aria-label="Caption">
                <input name="album" placeholder="Album (e.g. Church weddings)" class="lux-input" list="albums" aria-label="Album">
                <select name="service_id" class="lux-input" aria-label="Service">
                    <option value="">Whole business</option>
                    @foreach($services as $s)<option value="{{ $s->id }}">{{ $s->title }}</option>@endforeach
                </select>
                <button class="btn-gold w-full">Add</button>
            </form>
        @endforeach
        <datalist id="albums">@foreach($media->pluck('album')->filter()->unique() as $a)<option value="{{ $a }}">@endforeach</datalist>
    </div>
</div>
@endsection
