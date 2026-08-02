@php
    $designs = [
        'royal-gold' => ['bg' => '#FFFDF8', 'card' => '#FFFFFF', 'text' => '#2B2620', 'muted' => '#9A8E7C', 'accent' => '#B08D57', 'line' => '#EFE7D8', 'dark' => '#191512', 'darktext' => '#F4EDE3'],
        'blush-rose' => ['bg' => '#FFF7F5', 'card' => '#FFFFFF', 'text' => '#4A2E2B', 'muted' => '#B08D86', 'accent' => '#C4756B', 'line' => '#F5E3DF', 'dark' => '#3A211E', 'darktext' => '#FBEFEC'],
        'emerald-lux' => ['bg' => '#F8FAF7', 'card' => '#FFFFFF', 'text' => '#1E2B24', 'muted' => '#84948A', 'accent' => '#2E6B4F', 'line' => '#E2EAE4', 'dark' => '#122A1F', 'darktext' => '#EAF2EC'],
        'midnight-pearl' => ['bg' => '#101527', 'card' => '#1A2036', 'text' => '#EDEAE0', 'muted' => '#8B90A8', 'accent' => '#C9B37E', 'line' => '#252C47', 'dark' => '#0A0E1C', 'darktext' => '#EDEAE0'],
    ];
    $d = $designs[$settings->design ?? 'royal-gold'] ?? $designs['royal-gold'];
    if ($settings->accent_override) { $d['accent'] = $settings->accent_override; }
    $sections = $settings->sections ?? ['hero', 'countdown', 'couple', 'story', 'details', 'timeline'];
    $isAfter = ($settings->mode ?? 'before') === 'after';
    $headFont = match ($settings->font_style ?? 'serif') {
        'script' => "'Great Vibes', cursive",
        'modern' => "'Inter', sans-serif",
        default => "'Playfair Display', serif",
    };
    $brideName = $settings->bride_name ?: $wedding->partner_a;
    $groomName = $settings->groom_name ?: $wedding->partner_b;
    $days = $wedding->daysToGo();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $wedding->coupleNames() }} · {{ $isAfter ? 'Our Wedding Story' : 'The Wedding' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        :root { --bg: {{ $d['bg'] }}; --card: {{ $d['card'] }}; --text: {{ $d['text'] }}; --muted: {{ $d['muted'] }}; --accent: {{ $d['accent'] }}; --line: {{ $d['line'] }}; --dark: {{ $d['dark'] }}; --darktext: {{ $d['darktext'] }}; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { background: var(--bg); color: var(--text); font-family: Inter, sans-serif; line-height: 1.75; }
        .head-font { font-family: {{ $headFont }}; }
        .serif { font-family: 'Playfair Display', serif; }
        .script { font-family: 'Great Vibes', cursive; }
        .eyebrow { letter-spacing: .32em; text-transform: uppercase; font-size: .62rem; color: var(--accent); }
        section { max-width: 920px; margin: 0 auto; padding: 5.5rem 1.5rem; }
        .divider { width: 64px; height: 1px; background: var(--accent); margin: 2rem auto; position: relative; }
        .divider::after { content: '❦'; position: absolute; top: -0.85rem; left: 50%; transform: translateX(-50%); color: var(--accent); background: var(--bg); padding: 0 .6rem; font-size: .9rem; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1.1rem; padding: 2.2rem; }

        /* Hero */
        .hero { min-height: 96vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; position: relative; overflow: hidden; padding: 2rem 1.5rem; }
        .hero-banner { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .hero-veil { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.25), rgba(0,0,0,.55)); }
        .hero-plain { position: absolute; inset: 0; background:
            radial-gradient(ellipse at 20% 15%, color-mix(in srgb, var(--accent) 14%, transparent), transparent 55%),
            radial-gradient(ellipse at 85% 80%, color-mix(in srgb, var(--accent) 10%, transparent), transparent 50%); }
        .hero-content { position: relative; z-index: 2; }
        .hero.with-img .hero-content { color: #fff; }
        .hero.with-img .eyebrow { color: #EAD9B8; }
        .names { font-size: clamp(3rem, 9vw, 6rem); line-height: 1.12; font-family: {{ $headFont }}; }
        .amp { color: var(--accent); font-style: italic; font-family: 'Great Vibes', cursive; }
        .hero.with-img .amp { color: #EAD9B8; }
        .ring { width: 90px; height: 90px; border: 1px solid var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.6rem; font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--accent); }
        .hero.with-img .ring { border-color: #EAD9B8; color: #EAD9B8; }

        /* Countdown */
        .countdown { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .cd-box { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 1.4rem 1.2rem; min-width: 96px; text-align: center; }
        .cd-num { font-family: 'Playfair Display', serif; font-size: 2.4rem; color: var(--accent); line-height: 1; }
        .cd-lbl { font-size: .6rem; letter-spacing: .25em; text-transform: uppercase; color: var(--muted); margin-top: .5rem; }

        /* Couple profiles */
        .couple-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 2rem; }
        .profile { text-align: center; }
        .avatar { width: 168px; height: 168px; border-radius: 50%; object-fit: cover; margin: 0 auto 1.4rem; border: 4px solid var(--accent); box-shadow: 0 18px 45px -18px color-mix(in srgb, var(--accent) 55%, transparent); }
        .avatar-placeholder { width: 168px; height: 168px; border-radius: 50%; margin: 0 auto 1.4rem; border: 2px dashed var(--accent); display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 3rem; color: var(--accent); background: color-mix(in srgb, var(--accent) 7%, var(--card)); }

        /* Timeline */
        .tl-item { display: flex; gap: 1.6rem; padding: 1.1rem 0; border-bottom: 1px solid var(--line); }
        .tl-time { color: var(--accent); min-width: 72px; font-weight: 600; font-size: .95rem; }

        /* Slides (after) */
        .slides { position: relative; height: 78vh; overflow: hidden; }
        .slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1.6s ease; }
        .slide.active { opacity: 1; }
        .slide img { width: 100%; height: 100%; object-fit: cover; }
        .slide-caption { position: absolute; bottom: 3.2rem; left: 50%; transform: translateX(-50%); text-align: center; color: #fff; z-index: 3; width: min(90%, 700px); }
        .slide-caption p { font-family: {{ $headFont }}; font-size: clamp(1.6rem, 4vw, 2.6rem); text-shadow: 0 4px 30px rgba(0,0,0,.55); }
        .slide-veil { position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,.55)); z-index: 2; }

        /* Albums */
        .album-tabs { display: flex; gap: .6rem; flex-wrap: wrap; justify-content: center; margin-bottom: 2.2rem; }
        .album-tab { border: 1px solid var(--line); background: var(--card); color: var(--text); border-radius: 999px; padding: .55rem 1.4rem; font-size: .82rem; cursor: pointer; transition: all .2s; }
        .album-tab.active { border-color: var(--accent); color: var(--accent); }
        .album-panel { display: none; }
        .album-panel.active { display: block; }
        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
        .gallery-masonry { columns: 3 220px; column-gap: 1rem; }
        .gallery-masonry figure { break-inside: avoid; margin-bottom: 1rem; }
        figure { position: relative; border-radius: .9rem; overflow: hidden; }
        figure img { width: 100%; display: block; transition: transform .5s; }
        figure:hover img { transform: scale(1.04); }
        figcaption { position: absolute; inset: auto 0 0 0; padding: 1.6rem .9rem .7rem; font-size: .75rem; color: #fff; background: linear-gradient(transparent, rgba(0,0,0,.65)); }
        .carousel { display: flex; gap: 1rem; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: .6rem; }
        .carousel figure { flex: 0 0 78%; max-width: 560px; scroll-snap-align: center; }

        .dark-strip { background: var(--dark); color: var(--darktext); text-align: center; padding: 4.5rem 1.5rem; }
        footer { text-align: center; padding: 3rem 1.5rem; color: var(--muted); font-size: .74rem; }
        h2.sec-title { font-family: {{ $headFont }}; font-size: clamp(1.9rem, 4.5vw, 2.7rem); margin-bottom: .6rem; }
        .fade-up { opacity: 0; transform: translateY(26px); transition: all .9s ease; }
        .fade-up.vis { opacity: 1; transform: none; }
    </style>
</head>
<body>

{{-- HERO --}}
@if(in_array('hero', $sections))
<div class="hero {{ $settings->banner_image ? 'with-img' : '' }}">
    @if($settings->banner_image)
        <img src="{{ asset('storage/'.$settings->banner_image) }}" class="hero-banner" alt="">
        <div class="hero-veil"></div>
    @else
        <div class="hero-plain"></div>
    @endif
    <div class="hero-content">
        <div class="ring">{{ mb_substr($brideName, 0, 1) }}·{{ mb_substr($groomName, 0, 1) }}</div>
        <p class="eyebrow">{{ $isAfter ? 'We said "I do"' : 'Together with their families' }}</p>
        <h1 class="names">{{ $brideName }} <span class="amp">&</span> {{ $groomName }}</h1>
        <div class="divider" style="{{ $settings->banner_image ? 'background:#EAD9B8;' : '' }}"></div>
        <p style="color: {{ $settings->banner_image ? 'rgba(255,255,255,.85)' : 'var(--muted)' }}; font-size: .95rem;">
            {{ optional($wedding->wedding_date)->format('l, F j, Y') ?? 'A date to remember' }}
            @if($wedding->venue_name) · {{ $wedding->venue_name }}@endif
            @if($wedding->venue_city), {{ $wedding->venue_city }}@endif
        </p>
        @if(!$isAfter && $settings->welcome_message)
            <p style="max-width: 520px; margin: 1.5rem auto 0; font-style: italic; color: {{ $settings->banner_image ? 'rgba(255,255,255,.8)' : 'var(--muted)' }};">{{ $settings->welcome_message }}</p>
        @endif
        @if($isAfter && $settings->after_message)
            <p style="max-width: 520px; margin: 1.5rem auto 0; font-style: italic; color: {{ $settings->banner_image ? 'rgba(255,255,255,.8)' : 'var(--muted)' }};">{{ $settings->after_message }}</p>
        @endif
    </div>
</div>
@endif

{{-- COUNTDOWN (before mode only) --}}
@if(!$isAfter && in_array('countdown', $sections) && $wedding->wedding_date && $days >= 0)
<section style="text-align: center;" class="fade-up">
    <p class="eyebrow">The countdown</p>
    <h2 class="sec-title">Until we say "I do"</h2>
    <div class="countdown" id="countdown" data-date="{{ $wedding->wedding_date->format('Y-m-d') }}{{ $wedding->wedding_time ? 'T'.$wedding->wedding_time : 'T09:00:00' }}">
        <div class="cd-box"><p class="cd-num" id="cd-d">{{ $days }}</p><p class="cd-lbl">Days</p></div>
        <div class="cd-box"><p class="cd-num" id="cd-h">–</p><p class="cd-lbl">Hours</p></div>
        <div class="cd-box"><p class="cd-num" id="cd-m">–</p><p class="cd-lbl">Minutes</p></div>
        <div class="cd-box"><p class="cd-num" id="cd-s">–</p><p class="cd-lbl">Seconds</p></div>
    </div>
</section>
@endif

{{-- SLIDES (after mode) --}}
@if($isAfter && in_array('slides', $sections) && $settings->slides && count($settings->slides))
<div class="slides" id="slides">
    @foreach($settings->slides as $i => $slide)
        <div class="slide {{ $i === 0 ? 'active' : '' }}">
            <img src="{{ asset('storage/'.$slide['image']) }}" alt="">
            <div class="slide-veil"></div>
            @if(!empty($slide['caption']))
                <div class="slide-caption"><p>{{ $slide['caption'] }}</p></div>
            @endif
        </div>
    @endforeach
</div>
@endif

{{-- COUPLE PROFILES - Enhanced --}}
@if(in_array('couple', $sections))
<section class="fade-up">
    <div style="text-align: center;">
        <p class="eyebrow">The couple</p>
        <h2 class="sec-title">{{ $isAfter ? 'Mr & Mrs' : 'Two hearts, one story' }}</h2>
        <div class="divider"></div>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; margin-top: 3rem;">
        {{-- Bride --}}
        <div style="text-align: center;">
            @if($settings->bride_photo)
                <div style="position: relative; margin-bottom: 2rem;">
                    <img src="{{ asset('storage/'.$settings->bride_photo) }}" 
                         style="width: 100%; max-width: 280px; height: 340px; object-fit: cover; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.15); display: block; margin: 0 auto;"
                         alt="{{ $brideName }}">
                    <div style="position: absolute; -bottom: 1rem; left: 50%; transform: translateX(-50%); background: var(--accent); color: white; padding: 0.5rem 1.5rem; border-radius: 999px; font-size: .8rem; font-weight: 600;">The Bride</div>
                </div>
            @else
                <div style="width: 280px; height: 340px; margin: 0 auto 2rem; border-radius: 8px; background: color-mix(in srgb, var(--accent) 15%, var(--card)); display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 4rem; color: var(--accent);">
                    {{ mb_substr($brideName, 0, 1) }}
                </div>
            @endif
            <h3 class="head-font" style="font-size: 2rem; margin: 1rem 0 .5rem;">{{ $brideName }}</h3>
            <p style="color: var(--muted); font-size: .95rem; line-height: 1.6; max-width: 340px; margin: 0 auto;">{{ $settings->bride_bio ?: 'The heart of every room she enters. Warm, graceful, and brilliantly kind.' }}</p>
        </div>
        {{-- Groom --}}
        <div style="text-align: center;">
            @if($settings->groom_photo)
                <div style="position: relative; margin-bottom: 2rem;">
                    <img src="{{ asset('storage/'.$settings->groom_photo) }}" 
                         style="width: 100%; max-width: 280px; height: 340px; object-fit: cover; border-radius: 8px; box-shadow: 0 20px 60px rgba(0,0,0,.15); display: block; margin: 0 auto;"
                         alt="{{ $groomName }}">
                    <div style="position: absolute; -bottom: 1rem; left: 50%; transform: translateX(-50%); background: var(--accent); color: white; padding: 0.5rem 1.5rem; border-radius: 999px; font-size: .8rem; font-weight: 600;">The Groom</div>
                </div>
            @else
                <div style="width: 280px; height: 340px; margin: 0 auto 2rem; border-radius: 8px; background: color-mix(in srgb, var(--accent) 15%, var(--card)); display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 4rem; color: var(--accent);">
                    {{ mb_substr($groomName, 0, 1) }}
                </div>
            @endif
            <h3 class="head-font" style="font-size: 2rem; margin: 1rem 0 .5rem;">{{ $groomName }}</h3>
            <p style="color: var(--muted); font-size: .95rem; line-height: 1.6; max-width: 340px; margin: 0 auto;">{{ $settings->groom_bio ?: 'Steady, thoughtful, and utterly devoted. A gentleman with a great laugh.' }}</p>
        </div>
    </div>
</section>
@endif

{{-- STORY --}}
@if(in_array('story', $sections) && $wedding->story)
<div class="dark-strip">
    <section style="padding: 0; max-width: 720px;" class="fade-up">
        <p class="eyebrow">Our story</p>
        <p class="script" style="font-size: 2rem; color: var(--accent); margin: 1rem 0;">How it all began…</p>
        <p style="white-space: pre-line; color: color-mix(in srgb, var(--darktext) 80%, transparent); font-size: .95rem;">{{ $wedding->story }}</p>
    </section>
</div>
@endif

{{-- DETAILS (before mode emphasis) --}}
@if(!$isAfter && in_array('details', $sections))
<section class="fade-up">
    <div style="text-align: center;">
        <p class="eyebrow">Save the date</p>
        <h2 class="sec-title">Event details</h2>
        <div class="divider"></div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.4rem; margin-top: 2rem;">
        <div class="card" style="text-align: center;">
            <p style="font-size: 1.6rem;">📅</p>
            <p class="eyebrow" style="margin-top: .6rem;">When</p>
            <p class="serif" style="font-size: 1.25rem; margin-top: .5rem;">{{ optional($wedding->wedding_date)->format('F j, Y') ?? 'To be announced' }}</p>
            @if($wedding->wedding_time)<p style="color: var(--muted); font-size: .85rem;">from {{ \Carbon\Carbon::parse($wedding->wedding_time)->format('H:i') }}</p>@endif
        </div>
        <div class="card" style="text-align: center;">
            <p style="font-size: 1.6rem;">📍</p>
            <p class="eyebrow" style="margin-top: .6rem;">Where</p>
            <p class="serif" style="font-size: 1.25rem; margin-top: .5rem;">{{ $wedding->venue_name ?? 'To be announced' }}</p>
            <p style="color: var(--muted); font-size: .85rem;">{{ collect([$wedding->venue_city, $wedding->venue_country])->filter()->join(', ') }}</p>
        </div>
    </div>
</section>
@endif

{{-- OTHER EVENTS (Proposal, Engagement, Send-off, etc) --}}
@if(in_array('other_events', $sections) && ($wedding->timelineEvents->where('category', '!=', 'ceremony')->count() > 0 || !$isAfter))
<section class="fade-up">
    <div style="text-align: center;">
        <p class="eyebrow">Related celebrations</p>
        <h2 class="sec-title">Our story together</h2>
        <div class="divider"></div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem; margin-top: 2rem;">
        @php
            $otherEvents = $wedding->timelineEvents()
                ->where('category', '!=', 'ceremony')
                ->orderBy('event_date')
                ->get();
        @endphp
        @forelse($otherEvents as $evt)
            <div class="card" style="overflow: hidden; transition: transform .3s;">
                @if($evt->photo_url)
                    <div style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 60%, var(--bg))); overflow: hidden; margin-bottom: 1.5rem;">
                        <img src="{{ asset('storage/'.$evt->photo_url) }}" style="width: 100%; height: 100%; object-fit: cover;" alt="{{ $evt->title }}">
                    </div>
                @else
                    <div style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 60%, var(--bg))); display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem; font-size: 3rem;">
                        {{ match($evt->category ?? 'other') {
                            'proposal' => '💍',
                            'engagement' => '✨',
                            'sendoff' => '🚗',
                            'bachelor' => '🎉',
                            'rehearsal' => '🎭',
                            default => '🎊'
                        } }}
                    </div>
                @endif
                <h3 class="head-font" style="font-size: 1.3rem; margin: 0 0 .5rem; text-transform: capitalize;">{{ $evt->title }}</h3>
                <p class="eyebrow" style="margin: 0.5rem 0;">{{ optional($evt->event_date)->format('F j, Y') }}</p>
                @if($evt->location)
                    <p style="color: var(--muted); font-size: .85rem; margin: 0.3rem 0;">📍 {{ $evt->location }}</p>
                @endif
                @if($evt->description)
                    <p style="color: var(--muted); font-size: .9rem; margin: 1rem 0;">{{ Str::limit($evt->description, 120) }}</p>
                @endif
                @if($evt->album_id)
                    <a href="#albums" style="display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; background: color-mix(in srgb, var(--accent) 20%, var(--bg)); color: var(--accent); border-radius: 999px; text-decoration: none; font-size: .85rem; font-weight: 600;">View photos →</a>
                @endif
            </div>
        @empty
            @if(!$isAfter)
                <p style="grid-column: 1 / -1; text-align: center; color: var(--muted); font-style: italic;">Add engagement, proposal, or other special moments to your timeline.</p>
            @endif
        @endforelse
    </div>
</section>
@endif

{{-- TIMELINE --}}
@if(in_array('timeline', $sections) && $wedding->timelineEvents->count())
<section class="fade-up">
    <div style="text-align: center;">
        <p class="eyebrow">{{ $isAfter ? 'How the day unfolded' : 'The day' }}</p>
        <h2 class="sec-title">{{ $isAfter ? 'A day we will never forget' : 'Order of the day' }}</h2>
        <div class="divider"></div>
    </div>
    <div style="max-width: 640px; margin: 1.6rem auto 0;">
        @foreach($wedding->timelineEvents as $event)
            <div class="tl-item">
                <span class="tl-time">{{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}</span>
                <div>
                    <p style="font-weight: 500;">{{ $event->title }}</p>
                    @if($event->location)<p style="color: var(--muted); font-size: .82rem;">{{ $event->location }}</p>@endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- MENUS --}}
@if(in_array('menus', $sections) && $wedding->menus->count())
<section class="fade-up">
    <div style="text-align: center;">
        <p class="eyebrow">On the table</p>
        <h2 class="sec-title">The menu</h2>
        <div class="divider"></div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.4rem; margin-top: 2rem;">
        @foreach($wedding->menus as $menu)
            <div class="card">
                <p class="serif" style="font-size: 1.2rem; margin-bottom: .9rem; color: var(--accent);">{{ $menu->name }}</p>
                @foreach($menu->items as $item)
                    <p style="font-size: .9rem;">{{ $item->name }}</p>
                    @if($item->description)<p style="color: var(--muted); font-size: .76rem; margin-bottom: .5rem;">{{ $item->description }}</p>@endif
                @endforeach
            </div>
        @endforeach
    </div>
</section>
@endif

{{-- ALBUMS (after mode) with Enhanced Styling --}}
@if($isAfter && in_array('albums', $sections) && $albums->count())
<section class="fade-up" style="max-width: 1200px; position: relative;">
    @php
        $templates = [
            'minimal' => ['bg' => 'linear-gradient(135deg, #FFFDF9 0%, #FAF5EC 100%)', 'border' => 'none'],
            'floral-rose' => ['bg' => 'linear-gradient(135deg, #FFF5F7 0%, #FFE8ED 100%)', 'border' => 'repeating-linear-gradient(45deg, #FFB6C1, #FFB6C1 10px, #FFC0CB 10px, #FFC0CB 20px)'],
            'floral-lily' => ['bg' => 'linear-gradient(135deg, #FFFAF0 0%, #FFEAA7 100%)', 'border' => 'repeating-linear-gradient(90deg, #FFD700, #FFD700 8px, #FFA500 8px, #FFA500 16px)'],
            'floral-orchid' => ['bg' => 'linear-gradient(135deg, #F5E6F8 0%, #E6D5F8 100%)', 'border' => 'repeating-linear-gradient(45deg, #DA70D6, #DA70D6 12px, #FF1493 12px, #FF1493 24px)'],
            'vintage' => ['bg' => 'linear-gradient(135deg, #F5F1E8 0%, #E8DACC 100%)', 'border' => 'repeating-linear-gradient(0deg, #D4A574, #D4A574 15px, #C19A6B 15px, #C19A6B 30px)'],
            'modern' => ['bg' => 'linear-gradient(135deg, #F0F0F0 0%, #FFFFFF 100%)', 'border' => '2px solid #333']
        ];
    @endphp
    
    <div style="text-align: center; margin-bottom: 2rem;">
        <p class="eyebrow">The gallery</p>
        <h2 class="sec-title">Every moment, kept forever</h2>
        <div class="divider"></div>
    </div>

    {{-- Category Filter Tabs --}}
    <div class="album-tabs" style="justify-content: center; margin-bottom: 2rem;">
        <button class="album-tab active" data-album="all-albums" style="border-radius: 999px;">All</button>
        @php $categories = $albums->pluck('category')->unique(); @endphp
        @foreach($categories as $cat)
            <button class="album-tab" data-album="cat-{{ $cat }}" style="border-radius: 999px; text-transform: capitalize;">{{ $cat }}</button>
        @endforeach
    </div>

    {{-- Albums with Flower Templates --}}
    @foreach($albums as $i => $album)
        @php
            $template = $templates[$album->template] ?? $templates['minimal'];
        @endphp
        <div class="album-panel {{ $i === 0 ? 'active' : '' }}" id="album-{{ $album->id }}" data-category="{{ $album->category }}" style="margin-bottom: 3rem; padding: 2rem; background: {{ $template['bg'] }}; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.08);">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h3 class="head-font" style="font-size: 1.6rem; margin: 0; color: var(--text);">{{ $album->name }}</h3>
                @if($album->description)
                    <p style="color: var(--muted); font-style: italic; margin: 0.5rem 0 0;">{{ $album->description }}</p>
                @endif
            </div>
            
            <div class="{{ $album->style === 'masonry' ? 'gallery-masonry' : ($album->style === 'carousel' ? 'carousel' : 'gallery-grid') }}">
                @forelse($album->photos as $photo)
                    <figure style="border-radius: 8px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,.1);">
                        <img src="{{ asset('storage/'.$photo->file_path) }}" loading="lazy" alt="{{ $photo->caption }}" style="transition: transform .3s ease;">
                        @if($photo->caption)
                            <figcaption style="background: linear-gradient(180deg, transparent, rgba(0,0,0,.7)); color: white; padding: 1.5rem 1rem 1rem;">{{ $photo->caption }}</figcaption>
                        @endif
                    </figure>
                @empty
                    <p style="grid-column: 1 / -1; text-align: center; color: var(--muted); padding: 2rem;">No photos in this album yet.</p>
                @endforelse
            </div>
        </div>
    @endforeach

    <script>
        document.querySelectorAll('.album-tab').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.album-tab').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const target = this.dataset.album;
                document.querySelectorAll('.album-panel').forEach(panel => {
                    if (target === 'all-albums') {
                        panel.style.display = 'block';
                    } else {
                        const cat = target.replace('cat-', '');
                        panel.style.display = panel.dataset.category === cat ? 'block' : 'none';
                    }
                });
            });
        });
    </script>
</section>
@endif

{{-- Closing note --}}
<div class="dark-strip">
    <p class="script" style="font-size: 2.4rem; color: var(--accent);">{{ $isAfter ? 'Thank you for the love' : 'We can\'t wait to celebrate with you' }}</p>
    <p class="eyebrow" style="margin-top: 1rem;">{{ $brideName }} & {{ $groomName }}{{ $wedding->wedding_date ? ' · '.$wedding->wedding_date->format('d.m.Y') : '' }}</p>
</div>

<footer>Powered by <a href="https://letsventure.co.tz" style="color: var(--accent); text-decoration: none;">LetsVenture</a></footer>

<script>
// Countdown
const cd = document.getElementById('countdown');
if (cd) {
    const target = new Date(cd.dataset.date).getTime();
    const tick = () => {
        const diff = Math.max(0, target - Date.now());
        document.getElementById('cd-d').textContent = Math.floor(diff / 864e5);
        document.getElementById('cd-h').textContent = Math.floor(diff % 864e5 / 36e5);
        document.getElementById('cd-m').textContent = Math.floor(diff % 36e5 / 6e4);
        document.getElementById('cd-s').textContent = Math.floor(diff % 6e4 / 1e3);
    };
    tick();
    setInterval(tick, 1000);
}

// Slides
const slides = document.querySelectorAll('#slides .slide');
if (slides.length > 1) {
    let cur = 0;
    setInterval(() => {
        slides[cur].classList.remove('active');
        cur = (cur + 1) % slides.length;
        slides[cur].classList.add('active');
    }, 5200);
}

// Album tabs
document.querySelectorAll('.album-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.album-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.album-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.album).classList.add('active');
    });
});

// Fade-up on scroll
const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('vis'); });
}, { threshold: .12 });
document.querySelectorAll('.fade-up').forEach(el => io.observe(el));
</script>
</body>
</html>
