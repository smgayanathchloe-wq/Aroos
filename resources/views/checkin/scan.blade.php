<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome · {{ $wedding->coupleNames() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #14110E; color: #F4EDE3; font-family: Inter, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .panel { max-width: 420px; width: 100%; text-align: center; }
        .eyebrow { letter-spacing: .3em; text-transform: uppercase; font-size: .6rem; color: #C9AB7C; }
        .serif { font-family: 'Playfair Display', serif; }
        .script { font-family: 'Great Vibes', cursive; }
        .status { border-radius: 16px; padding: 2.2rem 1.8rem; margin-top: 1.4rem; }
        .status.ok { background: rgba(90, 160, 90, .12); border: 1px solid rgba(90, 160, 90, .45); }
        .status.in { background: rgba(200, 150, 60, .1); border: 1px solid rgba(200, 150, 60, .4); }
        .status.new { background: rgba(176, 141, 87, .08); border: 1px solid rgba(176, 141, 87, .4); }
        .big { font-size: 3rem; }
        .seatline { display: flex; justify-content: center; gap: 2.4rem; margin-top: 1.2rem; }
        .seatline p:first-child { letter-spacing: .25em; text-transform: uppercase; font-size: .55rem; color: #C9AB7C; }
        .seatline p:last-child { font-family: 'Playfair Display', serif; font-size: 1.6rem; }
        .btn { display: inline-block; width: 100%; background: linear-gradient(135deg, #B08D57, #C9AB7C); color: #14110E; font-weight: 700; border: none; border-radius: 10px; padding: 1rem; font-size: 1rem; cursor: pointer; margin-top: 1.4rem; }
        select { background: #241C13; color: #F4EDE3; border: 1px solid rgba(176, 141, 87, .4); border-radius: 8px; padding: .7rem; width: 100%; margin-top: 1rem; font-size: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="panel">
        <p class="script" style="font-size: 2.2rem; color: #C9AB7C;">{{ $wedding->partner_a }} & {{ $wedding->partner_b }}</p>
        <p class="eyebrow">{{ optional($wedding->wedding_date)->format('F j, Y') ?? '' }} {{ $wedding->venue_name ? '· '.$wedding->venue_name : '' }}</p>

        @if($alreadyIn)
            <div class="status in">
                <p class="big">⚠️</p>
                <p class="serif" style="font-size: 1.5rem; margin-top: .6rem;">Already scanned</p>
                <p style="font-size: .9rem; color: #C9AB7C; margin-top: .5rem;">{{ $guest->fullName() }} checked in at {{ $guest->checked_in_at->format('H:i') }} ({{ $guest->checked_in_count }} pax)</p>
                <p style="font-size: .75rem; color: #8A7E6F; margin-top: .8rem;">This guest is already inside the hall. If this is unexpected, please alert the hosts.</p>
            </div>
        @else
            <div class="status new">
                <p class="eyebrow">Welcome</p>
                <p class="serif" style="font-size: 1.8rem; margin-top: .5rem;">{{ $guest->fullName() }}</p>
                @if($guest->party_count > 1)<p style="font-size: .8rem; color: #8A7E6F;">Invited party of {{ $guest->party_count }}</p>@endif
                <div class="seatline">
                    <div><p>Table</p><p>{{ $guest->seatingTable?->name ?? 'TBA' }}</p></div>
                    <div><p>Seats</p><p>{{ $guest->seat_numbers ?: $guest->party_count }}</p></div>
                </div>
                <form method="POST" action="{{ route('public.checkin.confirm', [$wedding->slug, $guest->checkin_token]) }}">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    @if($guest->party_count > 1)
                        <select name="count">
                            @for($i = $guest->party_count; $i >= 1; $i--)
                                <option value="{{ $i }}">{{ $i }} arriving</option>
                            @endfor
                        </select>
                    @endif
                    <button class="btn">✓ Check in now</button>
                </form>
            </div>
        @endif

        <p style="font-size: .65rem; color: #8A7E6F; margin-top: 1.6rem;">Powered by LetsVenture</p>
    </div>
</body>
</html>
