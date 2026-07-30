<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thank you · {{ $wedding->coupleNames() }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500&family=Playfair+Display:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0E0C0A; color: #F4EDE3; font-family: Inter, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; text-align: center; }
        .serif { font-family: 'Playfair Display', serif; }
        .eyebrow { letter-spacing: .3em; text-transform: uppercase; font-size: .65rem; color: #B89668; }
        .divider { width: 50px; height: 1px; background: #B89668; margin: 1.5rem auto; }
    </style>
</head>
<body>
    <div>
        <p class="eyebrow">Thank you, {{ $guest->first_name }}</p>
        <h1 class="serif" style="font-size: 2.5rem; margin-top: 1rem">
            @if($guest->rsvp_status === 'attending')
                We look forward to celebrating with you.
            @elseif($guest->rsvp_status === 'maybe')
                We hope you can make it — keep us posted.
            @else
                We'll miss you, but thank you for letting us know.
            @endif
        </h1>
        <div class="divider"></div>
        <p style="color: #8A7E6F">{{ $wedding->coupleNames() }}@if($wedding->wedding_date) · {{ $wedding->wedding_date->format('F j, Y') }}@endif</p>
    </div>
</body>
</html>
