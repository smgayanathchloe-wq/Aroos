<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seat Card · {{ $guest->fullName() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&family=Great+Vibes&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F2EDE4; font-family: Inter, sans-serif; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; gap: 1.5rem; }
        .card { background: linear-gradient(160deg, #14110E, #241C13); color: #F4EDE3; width: 360px; border-radius: 18px; padding: 2.6rem 2rem 2.2rem; text-align: center; position: relative; box-shadow: 0 30px 70px rgba(60, 45, 25, .35); overflow: hidden; }
        .card::before { content: ''; position: absolute; inset: 9px; border: 1px solid rgba(176, 141, 87, .4); border-radius: 12px; pointer-events: none; }
        .eyebrow { letter-spacing: .35em; text-transform: uppercase; font-size: .58rem; color: #C9AB7C; }
        .serif { font-family: 'Playfair Display', serif; }
        .script { font-family: 'Great Vibes', cursive; }
        .qr-wrap { background: #fff; width: 148px; height: 148px; margin: 1.2rem auto; padding: 10px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .divider { width: 44px; height: 1px; background: #B08D57; margin: 1rem auto; }
        .seat-box { display: flex; justify-content: center; gap: 2rem; margin-top: .8rem; }
        .seat-box div p:first-child { letter-spacing: .25em; text-transform: uppercase; font-size: .55rem; color: #C9AB7C; margin-bottom: .2rem; }
        .seat-box div p:last-child { font-family: 'Playfair Display', serif; font-size: 1.3rem; }
        .no-print { font-size: .8rem; color: #8A7E6F; }
        @media print { body { background: #fff; } .no-print { display: none; } .card { box-shadow: none; } }
    </style>
</head>
<body>
    <div class="card">
        <p class="script" style="font-size: 1.9rem; color: #C9AB7C;">{{ $wedding->partner_a }} & {{ $wedding->partner_b }}</p>
        <p class="eyebrow" style="margin-top: .4rem;">{{ optional($wedding->wedding_date)->format('F j, Y') ?? 'The Wedding' }}</p>
        <div class="divider"></div>
        <p class="eyebrow">Guest of honour</p>
        <p class="serif" style="font-size: 1.5rem; margin-top: .4rem;">{{ $guest->fullName() }}</p>
        @if($guest->party_count > 1)<p style="font-size: .75rem; color: #8A7E6F;">Party of {{ $guest->party_count }}</p>@endif

        <div class="qr-wrap"><div id="qr"></div></div>

        <div class="seat-box">
            <div><p>Table</p><p>{{ $guest->seatingTable?->name ?? 'TBA' }}</p></div>
            <div><p>Seats</p><p>{{ $guest->seat_numbers ?: $guest->party_count }}</p></div>
        </div>
        <div class="divider"></div>
        <p style="font-size: .62rem; color: #8A7E6F;">Present this card at the entrance · {{ $wedding->venue_name ?? '' }}</p>
    </div>

    <div class="no-print" style="display: flex; gap: 1rem;">
        <button onclick="window.print()" style="background: #B08D57; color: #fff; border: none; padding: .7rem 1.6rem; border-radius: 8px; cursor: pointer; font-size: .85rem;">Print card</button>
        <button onclick="navigator.clipboard.writeText('{{ route('public.checkin.scan', [$wedding->slug, $guest->checkin_token]) }}').then(() => alert('Link copied — share digitally!'))" style="background: #fff; border: 1px solid #D8CDB9; padding: .7rem 1.6rem; border-radius: 8px; cursor: pointer; font-size: .85rem;">Copy digital link</button>
    </div>

    <script>
        new QRCode(document.getElementById('qr'), {
            text: @json(route('public.checkin.scan', [$wedding->slug, $guest->checkin_token])),
            width: 128,
            height: 128,
            colorDark: '#14110E',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    </script>
</body>
</html>
