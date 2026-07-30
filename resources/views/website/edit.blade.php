@extends('layouts.app')

@section('title', 'Website')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Public site</p>
        <h1 class="font-serif text-3xl mt-1">Wedding Website</h1>
    </div>
    @if($settings->published)
        <a href="{{ route('public.site', $wedding->slug) }}" target="_blank" class="btn-gold">View live site ↗</a>
    @endif
</div>

<div class="card p-6 mb-8">
    <p class="text-sm text-muted mb-1">Your public URL</p>
    <p class="text-gold">{{ route('public.site', $wedding->slug) }}</p>
    <p class="text-xs text-muted mt-2">Each guest also has a personal RSVP link and invitation card link — find them on the Guests page.</p>
</div>

<form method="POST" action="{{ route('website.update', $wedding) }}" enctype="multipart/form-data" class="space-y-8">
    @csrf @method('PUT')

    <div class="card p-6">
        <p class="eyebrow mb-4">Theme</p>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                'atelier' => ['Atelier', 'Dark, gold & editorial', '#0E0C0A', '#B89668'],
                'blush' => ['Blush', 'Soft pink & romantic', '#FDF3F0', '#C97B63'],
                'royal' => ['Royal', 'Deep navy & regal gold', '#0B1230', '#C9A860'],
                'garden' => ['Garden', 'Ivory & botanical green', '#F6F5EE', '#5F7A5A'],
            ] as $key => [$name, $desc, $bg, $accent])
                <label class="cursor-pointer">
                    <input type="radio" name="theme" value="{{ $key }}" @checked($settings->theme === $key) class="peer sr-only">
                    <div class="card p-4 peer-checked:border-gold transition">
                        <div class="h-16 rounded-lg mb-3 border border-line" style="background: linear-gradient(135deg, {{ $bg }} 60%, {{ $accent }})"></div>
                        <p class="text-sm">{{ $name }}</p>
                        <p class="text-xs text-muted">{{ $desc }}</p>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <div class="card p-6 grid md:grid-cols-2 gap-4">
        <div>
            <p class="eyebrow mb-4">Hero image</p>
            @if($settings->hero_image)
                <img src="{{ asset('storage/'.$settings->hero_image) }}" class="rounded-lg mb-3 max-h-40 object-cover w-full">
            @endif
            <input type="file" name="hero_image" accept="image/*" class="lux-input">
        </div>
        <div>
            <p class="eyebrow mb-4">Welcome message</p>
            <textarea name="welcome_message" rows="3" placeholder="Together with their families…" class="lux-input">{{ $settings->welcome_message }}</textarea>
            <p class="eyebrow my-4">Our story</p>
            <textarea name="story" rows="4" placeholder="How you met, the proposal…" class="lux-input">{{ $wedding->story }}</textarea>
        </div>
    </div>

    <div class="card p-6">
        <p class="eyebrow mb-4">Sections shown on the site</p>
        <div class="flex flex-wrap gap-4">
            @foreach(['hero' => 'Hero', 'story' => 'Our Story', 'details' => 'Event Details', 'timeline' => 'The Day (timeline)', 'menus' => 'Menus', 'rsvp' => 'RSVP note'] as $key => $label)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key, $settings->sections ?? [])) class="accent-[#B89668]"> {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="published" value="1" @checked($settings->published) class="accent-[#B89668]"> Published (visible to anyone with the link)
        </label>
        <button class="btn-gold">Save website</button>
    </div>
</form>
@endsection
