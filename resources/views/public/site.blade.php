@php
    $themes = [
        'atelier' => ['bg' => '#0E0C0A', 'card' => '#1B1713', 'text' => '#F4EDE3', 'muted' => '#8A7E6F', 'accent' => '#B89668', 'line' => '#2A241E'],
        'blush' => ['bg' => '#FDF3F0', 'card' => '#FFFFFF', 'text' => '#3B2C28', 'muted' => '#A08A82', 'accent' => '#C97B63', 'line' => '#F0DDD6'],
        'royal' => ['bg' => '#0B1230', 'card' => '#131B3F', 'text' => '#EFEBDD', 'muted' => '#8B90A8', 'accent' => '#C9A860', 'line' => '#232C55'],
        'garden' => ['bg' => '#F6F5EE', 'card' => '#FFFFFF', 'text' => '#2E362B', 'muted' => '#8B9184', 'accent' => '#5F7A5A', 'line' => '#E3E4D7'],
    ];
    $t = $themes[$settings->theme] ?? $themes['atelier'];
    $sections = $settings->sections ?? ['hero', 'story', 'details', 'timeline', 'rsvp'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $wedding->coupleNames() }} · The Wedding</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --bg: {{ $t['bg'] }}; --card: {{ $t['card'] }}; --text: {{ $t['text'] }}; --muted: {{ $t['muted'] }}; --accent: {{ $t['accent'] }}; --line: {{ $t['line'] }}; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg); color: var(--text); font-family: Inter, sans-serif; line-height: 1.7; }
        .serif { font-family: 'Playfair Display', serif; }
        .eyebrow { letter-spacing: .3em; text-transform: uppercase; font-size: .65rem; color: var(--accent); }
        section { max-width: 860px; margin: 0 auto; padding: 5rem 1.5rem; }
        .hero { min-height: 92vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; position: relative; overflow: hidden; }
        .hero-img { position: absolute; inset: 0; object-fit: cover; width: 100%; height: 100%; opacity: .25; }
        .hero-content { position: relative; z-index: 1; padding: 1.5rem; }
        .names { font-size: clamp(2.8rem, 8vw, 5.5rem); line-height: 1.1; }
        .amp { color: var(--accent); font-style: italic; }
        .divider { width: 60px; height: 1px; background: var(--accent); margin: 2rem auto; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 1rem; padding: 2rem; }
        .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; }
        .tl-item { display: flex; gap: 1.5rem; padding: 1rem 0; border-bottom: 1px solid var(--line); }
        .tl-time { color: var(--accent); min-width: 70px; font-weight: 500; }
        .btn { display: inline-block; background: var(--accent); color: var(--bg); padding: .8rem 2.2rem; border-radius: .5rem; text-decoration: none; font-weight: 600; font-size: .9rem; }
        footer { text-align: center; padding: 3rem; color: var(--muted); font-size: .75rem; }
        h2.serif { font-size: 2rem; margin-bottom: 1.5rem; }
        .countdown { display: flex; gap: 2rem; justify-content: center; margin-top: 2.5rem; }
        .countdown div { text-align: center; }
        .countdown .num { font-size: 2rem; color: var(--accent); font-family: 'Playfair Display', serif; }
        .countdown .lbl { font-size: .65rem; letter-spacing: .2em; text-transform: uppercase; color: var(--muted); }
    </style>
</head>
<body>
    @if(in_array('hero', $sections))
    <div class="hero">
        @if($settings->hero_image)
            <img src="{{ asset('storage/'.$settings->hero_image) }}" class="hero-img" alt="">
        @endif
        <div class="hero-content">
            <p class="eyebrow">The Wedding Of</p>
            <h1 class="serif names">{{ $wedding->partner_a }} <span class="amp">&</span> {{ $wedding->partner_b }}</h1>
            <div class="divider"></div>
            <p style="color: var(--muted)">
                {{ optional($wedding->wedding_date)->format('l, F j, Y') ?? 'Date to be announced' }}
                @if($wedding->venue_name) · {{ $wedding->venue_name }}@endif
                @if($wedding->venue_city), {{ $wedding->venue_city }}@endif
            </p>
            @if($settings->welcome_message)
                <p style="max-width: 480px; margin: 1.5rem auto 0; color: var(--muted); font-style: italic">{{ $settings->welcome_message }}</p>
            @endif
            @if($wedding->daysToGo() !== null && $wedding->daysToGo() > 0)
                <div class="countdown">
                    <div><p class="num">{{ $wedding->daysToGo() }}</p><p class="lbl">Days</p></div>
                </div>
            @endif
        </div>
    </div>
    @endif

    @if(in_array('story', $sections) && $wedding->story)
    <section>
        <p class="eyebrow">Chapter I</p>
        <h2 class="serif">Our Story</h2>
        <p style="color: var(--muted); white-space: pre-line">{{ $wedding->story }}</p>
    </section>
    @endif

    @if(in_array('details', $sections))
    <section>
        <p class="eyebrow">Chapter II</p>
        <h2 class="serif">Event Details</h2>
        <div class="grid2">
            <div class="card">
                <p class="eyebrow">When</p>
                <p class="serif" style="font-size: 1.3rem; margin-top: .6rem">{{ optional($wedding->wedding_date)->format('F j, Y') ?? 'TBA' }}</p>
                @if($wedding->wedding_time)<p style="color: var(--muted)">{{ \Carbon\Carbon::parse($wedding->wedding_time)->format('H:i') }}</p>@endif
            </div>
            <div class="card">
                <p class="eyebrow">Where</p>
                <p class="serif" style="font-size: 1.3rem; margin-top: .6rem">{{ $wedding->venue_name ?? 'TBA' }}</p>
                <p style="color: var(--muted)">{{ collect([$wedding->venue_city, $wedding->venue_country])->filter()->join(', ') }}</p>
            </div>
        </div>
    </section>
    @endif

    @if(in_array('timeline', $sections) && $wedding->timelineEvents->count())
    <section>
        <p class="eyebrow">Chapter III</p>
        <h2 class="serif">The Day</h2>
        @foreach($wedding->timelineEvents as $event)
            <div class="tl-item">
                <span class="tl-time">{{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}</span>
                <div>
                    <p>{{ $event->title }}</p>
                    @if($event->location)<p style="color: var(--muted); font-size: .85rem">{{ $event->location }}</p>@endif
                </div>
            </div>
        @endforeach
    </section>
    @endif

    @if(in_array('menus', $sections) && $wedding->menus->count())
    <section>
        <p class="eyebrow">On the menu</p>
        <h2 class="serif">Menus</h2>
        <div class="grid2">
            @foreach($wedding->menus as $menu)
                <div class="card">
                    <p class="serif" style="font-size: 1.2rem; margin-bottom: 1rem">{{ $menu->name }}</p>
                    @foreach($menu->items as $item)
                        <p style="font-size: .9rem">{{ $item->name }}</p>
                        @if($item->description)<p style="color: var(--muted); font-size: .78rem; margin-bottom: .5rem">{{ $item->description }}</p>@endif
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
    @endif

    @if(in_array('rsvp', $sections))
    <section style="text-align: center">
        <p class="eyebrow">Reply</p>
        <h2 class="serif">Will you join us?</h2>
        <p style="color: var(--muted); max-width: 460px; margin: 0 auto 2rem">Please respond using the personal RSVP link in your invitation email or SMS.</p>
    </section>
    @endif

    <footer>{{ $wedding->coupleNames() }} · Powered by WedFlow</footer>
</body>
</html>
