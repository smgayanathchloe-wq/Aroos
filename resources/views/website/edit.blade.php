@extends('layouts.app')

@section('title', 'Website')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4 mb-8">
    <div>
        <p class="eyebrow">Public site</p>
        <h1 class="font-serif text-3xl mt-1 text-inktext">Wedding Website</h1>
        <p class="text-sm text-muted mt-2 max-w-2xl">Everything is configurable. Before the wedding: banner, countdown and profiles. After: photo albums with beautiful words. It looks stunning even before you add images.</p>
    </div>
    @if($settings->published)
        <a href="{{ route('public.site', $wedding->slug) }}" target="_blank" class="btn-gold">View live site ↗</a>
    @endif
</div>

<div class="card p-5 mb-8 flex flex-wrap items-center justify-between gap-3">
    <div>
        <p class="text-xs text-muted">Your public URL</p>
        <p class="text-gold text-sm">{{ route('public.site', $wedding->slug) }}</p>
    </div>
    <p class="text-xs text-muted max-w-xs">Guests are never asked to confirm on the website. Invitations and RSVPs go out from Messages via email/SMS.</p>
</div>

<form method="POST" action="{{ route('website.update', $wedding) }}" enctype="multipart/form-data" class="space-y-8">
    @csrf @method('PUT')

    {{-- Mode --}}
    <div class="card p-6">
        <p class="eyebrow mb-4">Stage of the journey</p>
        <div class="grid md:grid-cols-2 gap-4">
            <label class="cursor-pointer">
                <input type="radio" name="mode" value="before" @checked($settings->mode !== 'after') class="peer sr-only">
                <div class="card p-5 peer-checked:!border-gold transition h-full">
                    <p class="text-2xl">🕊️</p>
                    <h3 class="font-serif text-lg mt-2 text-inktext">Before the wedding</h3>
                    <p class="text-xs text-muted mt-1">Names on top, banner, live countdown, bride & groom profiles, story and event details.</p>
                </div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="mode" value="after" @checked($settings->mode === 'after') class="peer sr-only">
                <div class="card p-5 peer-checked:!border-gold transition h-full">
                    <p class="text-2xl">🌅</p>
                    <h3 class="font-serif text-lg mt-2 text-inktext">After the wedding</h3>
                    <p class="text-xs text-muted mt-1">A thank-you message, slideshow with beautiful words, and photo albums by event or location.</p>
                </div>
            </label>
        </div>
    </div>

    {{-- Design --}}
    <div class="card p-6">
        <p class="eyebrow mb-4">Design</p>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                'royal-gold' => ['Royal Gold', 'Ivory, deep charcoal & gold', '#FFFDF8', '#B08D57', '#191512'],
                'blush-rose' => ['Blush Rose', 'Soft pink & warm rosewood', '#FFF7F5', '#C4756B', '#4A2E2B'],
                'emerald-lux' => ['Emerald Lux', 'Cream & deep emerald', '#F8FAF7', '#2E6B4F', '#122A1F'],
                'midnight-pearl' => ['Midnight Pearl', 'Dark navy & pearl shimmer', '#101527', '#C9B37E', '#EDEAE0'],
            ] as $key => [$name, $desc, $bg, $accent, $text])
                <label class="cursor-pointer">
                    <input type="radio" name="design" value="{{ $key }}" @checked($settings->design === $key) class="peer sr-only">
                    <div class="card p-4 peer-checked:!border-gold transition">
                        <div class="h-16 rounded-lg mb-3 flex items-center justify-center" style="background: {{ $bg }}; border: 1px solid #eee;">
                            <span style="color: {{ $accent }}; font-family: 'Playfair Display', serif; font-size: 1.1rem;">A & J</span>
                        </div>
                        <p class="text-sm text-inktext">{{ $name }}</p>
                        <p class="text-xs text-muted">{{ $desc }}</p>
                    </div>
                </label>
            @endforeach
        </div>
        <div class="grid md:grid-cols-3 gap-4 mt-5">
            <div>
                <label class="text-xs text-muted block mb-1.5">Heading font style</label>
                <select name="font_style" class="lux-input">
                    <option value="serif" @selected($settings->font_style === 'serif')>Elegant serif (Playfair)</option>
                    <option value="script" @selected($settings->font_style === 'script')>Romantic script (Great Vibes)</option>
                    <option value="modern" @selected($settings->font_style === 'modern')>Clean modern (Inter)</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-muted block mb-1.5">Accent color override (optional)</label>
                <input type="color" name="accent_override" value="{{ $settings->accent_override ?: '#B08D57' }}" class="lux-input h-10">
            </div>
        </div>
    </div>

    {{-- Banner & messages --}}
    <div class="card p-6 grid md:grid-cols-2 gap-6">
        <div>
            <p class="eyebrow mb-3">Banner picture</p>
            @if($settings->banner_image)
                <img src="{{ asset('storage/'.$settings->banner_image) }}" class="rounded-lg mb-3 max-h-44 object-cover w-full">
            @else
                <div class="rounded-lg mb-3 h-28 flex items-center justify-center text-xs text-muted" style="background:#FAF7F2; border: 1px dashed #E0D6C2;">No banner yet — the design still looks beautiful without one</div>
            @endif
            <input type="file" name="banner_image" accept="image/*" class="lux-input">
        </div>
        <div>
            <p class="eyebrow mb-3">Welcome message (before)</p>
            <textarea name="welcome_message" rows="3" placeholder="Together with their families, they invite you…" class="lux-input">{{ $settings->welcome_message }}</textarea>
            <p class="eyebrow my-3">Thank-you message (after)</p>
            <textarea name="after_message" rows="3" placeholder="Thank you for celebrating with us — our hearts are full…" class="lux-input">{{ $settings->after_message }}</textarea>
        </div>
    </div>

    {{-- Bride & Groom profiles --}}
    <div class="card p-6">
        <p class="eyebrow mb-4">The couple — separate profiles</p>
        <div class="grid md:grid-cols-2 gap-8">
            <div>
                <p class="font-serif text-lg text-inktext mb-3">👰 The Bride</p>
                <input name="bride_name" value="{{ $settings->bride_name ?: $wedding->partner_a }}" placeholder="Bride's name" class="lux-input mb-3">
                @if($settings->bride_photo)
                    <img src="{{ asset('storage/'.$settings->bride_photo) }}" class="w-24 h-24 rounded-full object-cover mb-3" style="border: 3px solid #B08D57;">
                @endif
                <input type="file" name="bride_photo" accept="image/*" class="lux-input mb-3">
                <textarea name="bride_bio" rows="3" placeholder="A few words about her — who she is, what she loves…" class="lux-input">{{ $settings->bride_bio }}</textarea>
            </div>
            <div>
                <p class="font-serif text-lg text-inktext mb-3">🤵 The Groom</p>
                <input name="groom_name" value="{{ $settings->groom_name ?: $wedding->partner_b }}" placeholder="Groom's name" class="lux-input mb-3">
                @if($settings->groom_photo)
                    <img src="{{ asset('storage/'.$settings->groom_photo) }}" class="w-24 h-24 rounded-full object-cover mb-3" style="border: 3px solid #B08D57;">
                @endif
                <input type="file" name="groom_photo" accept="image/*" class="lux-input mb-3">
                <textarea name="groom_bio" rows="3" placeholder="A few words about him…" class="lux-input">{{ $settings->groom_bio }}</textarea>
            </div>
        </div>
    </div>

    {{-- Story --}}
    <div class="card p-6">
        <p class="eyebrow mb-3">Our story</p>
        <textarea name="story" rows="4" placeholder="How you met, the proposal…" class="lux-input">{{ $wedding->story }}</textarea>
    </div>

    {{-- Slides (after mode) --}}
    <div class="card p-6">
        <p class="eyebrow mb-2">Slideshow pictures with beautiful words <span class="normal-case">(after-wedding hero)</span></p>
        <p class="text-xs text-muted mb-4">Each slide is a full-screen photo with its caption fading in. Add your best moments.</p>
        @if($settings->slides && count($settings->slides))
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                @foreach($settings->slides as $i => $slide)
                    <div class="relative rounded-lg overflow-hidden group">
                        <img src="{{ asset('storage/'.$slide['image']) }}" class="w-full h-28 object-cover">
                        <p class="text-xs p-2 text-muted truncate">{{ $slide['caption'] ?: '—' }}</p>
                        <label class="absolute top-2 right-2 bg-white/90 rounded px-1.5 py-0.5 text-xs cursor-pointer">
                            <input type="checkbox" name="remove_slides[]" value="{{ $i }}"> remove
                        </label>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="grid md:grid-cols-2 gap-3" id="slide-rows">
            <div class="flex gap-2"><input type="file" name="slide_images[]" accept="image/*" class="lux-input"><input name="slide_captions[]" placeholder="Beautiful words for this photo…" class="lux-input"></div>
            <div class="flex gap-2"><input type="file" name="slide_images[]" accept="image/*" class="lux-input"><input name="slide_captions[]" placeholder="Caption…" class="lux-input"></div>
        </div>
    </div>

    {{-- Albums summary --}}
    <div class="card p-6">
        <p class="eyebrow mb-2">After-wedding albums</p>
        <p class="text-xs text-muted mb-3">Albums (by event or location) are managed in <a href="{{ route('photos.index', $wedding) }}" class="text-gold hover:underline">Photos & Albums</a>. Public albums appear on the website in after-mode, each with its own style and color.</p>
        <div class="flex flex-wrap gap-2">
            @forelse($albums as $album)
                <span class="text-xs px-3 py-1.5 rounded-full border" style="border-color: {{ $album->accent_color ?: '#E8E2D9' }}; color: #6B573A;">{{ $album->name }} · {{ $album->photos_count }} photos · {{ $album->style }}{{ $album->public ? '' : ' · hidden' }}</span>
            @empty
                <span class="text-xs text-muted">No albums yet.</span>
            @endforelse
        </div>
    </div>

    {{-- Sections + publish --}}
    <div class="card p-6">
        <p class="eyebrow mb-4">Sections shown</p>
        <div class="flex flex-wrap gap-4">
            @foreach(['hero' => 'Banner & names', 'countdown' => 'Countdown (before)', 'couple' => 'Bride & Groom profiles', 'story' => 'Our Story', 'details' => 'Event Details', 'timeline' => 'The Day (timeline)', 'menus' => 'Menus', 'slides' => 'Slideshow (after)', 'albums' => 'Albums (after)'] as $key => $label)
                <label class="flex items-center gap-2 text-sm text-inktext">
                    <input type="checkbox" name="sections[]" value="{{ $key }}" @checked(in_array($key, $settings->sections ?? [])) class="accent-[#B08D57]"> {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2 text-sm text-inktext">
            <input type="checkbox" name="published" value="1" @checked($settings->published) class="accent-[#B08D57]"> Published (visible to anyone with the link)
        </label>
        <button class="btn-gold px-8 py-3">Save website</button>
    </div>
</form>
@endsection
