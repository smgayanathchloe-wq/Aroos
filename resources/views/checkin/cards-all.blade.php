<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>All Seat Cards · {{ $wedding->coupleNames() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,500;0,600&family=Great+Vibes&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F2EDE4; font-family: Inter, sans-serif; padding: 2rem; }
        .toolbar { text-align: center; margin-bottom: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 1.5rem; }
        .card { background: linear-gradient(160deg, #14110E, #241C13); color: #F4EDE3; border-radius: 16px; padding: 2rem 1.6rem; text-align: center; position: relative; overflow: hidden; page-break-inside: avoid; }
        .card::before { content: ''; position: absolute; inset: 8px; border: 1px solid rgba(176, 141, 87, .4); border-radius: 10px; pointer-events: none; }
        .eyebrow { letter-spacing: .3em; text-transform: uppercase; font-size: .55rem; color: #C9AB7C; }
        .serif { font-family: 'Playfair Display', serif; }
        .script { font-family: 'Great Vibes', cursive; }
        .qr-wrap { background: #fff; width: 120px; height: 120px; margin: 1rem auto; padding: 8px; border-radius: 8px; }
        .seats { display: flex; justify-content: center; gap: 1.6rem; margin-top: .6rem; font-size: .95rem; }
        .seats p:first-child { letter-spacing: .2em; text-transform: uppercase; font-size: .5rem; color: #C9AB7C; }
        @media print { body { background: #fff; padding: 0; } .toolbar { display: none; } .grid { gap: .8rem; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()" style="background:#B08D57; color:#fff; border:none; padding:.8rem 2rem; border-radius:8px; cursor:pointer;">Print {{ $guests->count() }} cards</button>
    </div>
    <div class="grid">
        @foreach($guests as $guest)
        <div class="card">
            <p class="script" style="font-size:1.5rem; color:#C9AB7C;">{{ $wedding->partner_a }} & {{ $wedding->partner_b }}</p>
            <p class="eyebrow">{{ optional($wedding->wedding_date)->format('F j, Y') ?? 'The Wedding' }}</p>
            <p class="serif" style="font-size:1.2rem; margin-top:.7rem;">{{ $guest->fullName() }}</p>
            @if($guest->party_count > 1)<p style="font-size:.7rem; color:#8A7E6F;">Party of {{ $guest->party_count }}</p>@endif
            <div class="qr-wrap" data-url="{{ route('public.checkin.scan', [$wedding->slug, $guest->checkin_token]) }}"></div>
            <div class="seats">
                <div><p>Table</p><p class="serif">{{ $guest->seatingTable?->name ?? 'TBA' }}</p></div>
                <div><p>Seats</p><p class="serif">{{ $guest->seat_numbers ?: $guest->party_count }}</p></div>
            </div>
        </div>
        @endforeach
    </div>
    <script>
        document.querySelectorAll('.qr-wrap').forEach(function (el) {
            new QRCode(el, { text: el.dataset.url, width: 104, height: 104, colorDark: '#14110E', correctLevel: QRCode.CorrectLevel.M });
        });
    </script>
</body>
</html>
