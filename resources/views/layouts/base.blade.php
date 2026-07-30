<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'WedFlow') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0E0C0A',
                        paper: '#14110E',
                        card: '#1B1713',
                        line: '#2A241E',
                        gold: '#B89668',
                        goldlight: '#D4B98C',
                        cream: '#F4EDE3',
                        muted: '#8A7E6F',
                    },
                    fontFamily: {
                        serif: ['"Playfair Display"', 'serif'],
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    <style>
        body { background: #0E0C0A; color: #F4EDE3; font-family: Inter, sans-serif; }
        .gold-gradient { background: linear-gradient(135deg, #B89668, #D4B98C); }
        .lux-input { background: #1B1713; border: 1px solid #2A241E; border-radius: 0.5rem; padding: 0.55rem 0.85rem; color: #F4EDE3; width: 100%; font-size: 0.875rem; }
        .lux-input:focus { outline: none; border-color: #B89668; }
        .lux-input::placeholder { color: #6B6154; }
        select.lux-input option { background: #1B1713; }
        .btn-gold { background: linear-gradient(135deg, #B89668, #D4B98C); color: #0E0C0A; font-weight: 600; border-radius: 0.5rem; padding: 0.55rem 1.25rem; font-size: 0.875rem; transition: opacity .15s; display: inline-block; }
        .btn-gold:hover { opacity: .9; }
        .btn-ghost { border: 1px solid #2A241E; color: #F4EDE3; border-radius: 0.5rem; padding: 0.5rem 1.1rem; font-size: 0.875rem; transition: border-color .15s; display: inline-block; }
        .btn-ghost:hover { border-color: #B89668; color: #D4B98C; }
        .card { background: #1B1713; border: 1px solid #2A241E; border-radius: 0.9rem; }
        .eyebrow { letter-spacing: 0.25em; text-transform: uppercase; font-size: 0.65rem; color: #B89668; }
    </style>
    @stack('head')
</head>
<body class="antialiased min-h-screen">
@yield('body')
@stack('scripts')
</body>
</html>
