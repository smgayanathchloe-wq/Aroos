<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Aroos') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Great+Vibes&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ink: '#0E0C0A',
                        paper: '#14110E',
                        sidecard: '#1B1713',
                        line: '#E8E2D9',
                        darkline: '#2A241E',
                        gold: '#B08D57',
                        goldlight: '#C9AB7C',
                        cream: '#F4EDE3',
                        canvas: '#FAF7F2',
                        surface: '#FFFFFF',
                        inktext: '#2D2820',
                        muted: '#8A7E6F',
                    },
                    fontFamily: {
                        serif: ['"Playfair Display"', 'serif'],
                        sans: ['Inter', 'sans-serif'],
                        script: ['"Great Vibes"', 'cursive'],
                    },
                },
            },
        };
    </script>
    <style>
        body { background: #FAF7F2; color: #2D2820; font-family: Inter, sans-serif; }
        .gold-gradient { background: linear-gradient(135deg, #B08D57, #C9AB7C); }
        .lux-input { background: #FFFFFF; border: 1px solid #E8E2D9; border-radius: 0.5rem; padding: 0.55rem 0.85rem; color: #2D2820; width: 100%; font-size: 0.875rem; }
        .lux-input:focus { outline: none; border-color: #B08D57; box-shadow: 0 0 0 3px rgba(176, 141, 87, .12); }
        .lux-input::placeholder { color: #B3A995; }
        select.lux-input option { background: #FFFFFF; }
        .btn-gold { background: linear-gradient(135deg, #B08D57, #C9AB7C); color: #FFFFFF; font-weight: 600; border-radius: 0.5rem; padding: 0.55rem 1.25rem; font-size: 0.875rem; transition: opacity .15s; display: inline-block; box-shadow: 0 4px 14px rgba(176, 141, 87, .3); }
        .btn-gold:hover { opacity: .9; }
        .btn-ghost { border: 1px solid #E8E2D9; color: #2D2820; background: #fff; border-radius: 0.5rem; padding: 0.5rem 1.1rem; font-size: 0.875rem; transition: all .15s; display: inline-block; }
        .btn-ghost:hover { border-color: #B08D57; color: #B08D57; }
        .card { background: #FFFFFF; border: 1px solid #E8E2D9; border-radius: 0.9rem; box-shadow: 0 1px 3px rgba(45, 40, 32, .04); }
        .eyebrow { letter-spacing: 0.25em; text-transform: uppercase; font-size: 0.65rem; color: #B08D57; }
        .text-mutedd { color: #8A7E6F; }
        @media print {
            .no-print, aside, .sidebar { display: none !important; }
            body { background: white; }
            .card { box-shadow: none; border-color: #ddd; }
        }
    </style>
    @stack('head')
</head>
<body class="antialiased min-h-screen">
@yield('body')
@stack('scripts')
</body>
</html>
