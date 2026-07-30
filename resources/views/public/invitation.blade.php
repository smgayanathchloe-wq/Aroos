<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation · {{ $wedding->coupleNames() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #191512; font-family: Inter, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .serif { font-family: 'Playfair Display', serif; }
        .invite { background: #0E0C0A; color: #F4EDE3; max-width: 430px; width: 100%; border: 1px solid #B8966855; border-radius: 4px; padding: 3.5rem 2.5rem; text-align: center; position: relative; box-shadow: 0 30px 80px rgba(0,0,0,.6); }
        .invite::before { content: ''; position: absolute; inset: 10px; border: 1px solid #B8966833; border-radius: 2px; pointer-events: none; }
        .eyebrow { letter-spacing: .35em; text-transform: uppercase; font-size: .6rem; color: #B89668; }
        .names { font-size: 2.4rem; line-height: 1.2; margin: 1.4rem 0; }
        .amp { color: #B89668; font-style: italic; }
        .divider { width: 46px; height: 1px; background: #B89668; margin: 1.4rem auto; }
        .muted { color: #8A7E6F; font-size: .85rem; }
        .detail { margin: 1.6rem 0; }
        .detail p:first-child { letter-spacing: .25em; text-transform: uppercase; font-size: .6rem; color: #B89668; margin-bottom: .3rem; }
        .btn { display: inline-block; background: linear-gradient(135deg, #B89668, #D4B98C); color: #0E0C0A; padding: .8rem 2rem; border-radius: .4rem; text-decoration: none; font-weight: 600; font-size: .85rem; margin-top: 1.4rem; }
        .ornament { color: #B89668; font-size: 1.4rem; letter-spacing: .5em; }
        @media print {
            body { background: white; padding: 0; }
            .invite { box-shadow: none; }
            .btn, .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="invite">
        <p class="ornament">❦</p>
        <p class="eyebrow" style="margin-top: 1.2rem">Together with their families</p>
        <h1 class="serif names">{{ $wedding->partner_a }}<br><span class="amp">&</span><br>{{ $wedding->partner_b }}</h1>
        <p class="muted">request the pleasure of the company of</p>
        <p class="serif" style="font-size: 1.35rem; margin: .8rem 0; font-style: italic">{{ $guest->fullName() }}{{ $guest->party_count > 1 ? ' & party' : '' }}</p>
        <p class="muted">at the celebration of their marriage</p>
        <div class="divider"></div>
        <div class="detail">
            <p>When</p>
            <p class="serif" style="font-size: 1.05rem">{{ optional($wedding->wedding_date)->format('l, F j, Y') ?? 'Date to be announced' }}</p>
            @if($wedding->wedding_time)<p class="muted">at {{ \Carbon\Carbon::parse($wedding->wedding_time)->format('H:i') }}</p>@endif
        </div>
        @if($wedding->venue_name)
        <div class="detail">
            <p>Where</p>
            <p class="serif" style="font-size: 1.05rem">{{ $wedding->venue_name }}</p>
            <p class="muted">{{ collect([$wedding->venue_city, $wedding->venue_country])->filter()->join(', ') }}</p>
        </div>
        @endif
        <a href="{{ route('public.rsvp', [$wedding->slug, $guest->invite_token]) }}" class="btn">RSVP</a>
        <p class="muted no-print" style="font-size: .65rem; margin-top: 1.6rem">Kindly respond at your earliest convenience</p>
    </div>
</body>
</html>
