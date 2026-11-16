@extends('layouts.base')

@section('title', 'Wedding service providers')

@section('body')
<div class="min-h-screen" style="background: linear-gradient(180deg, #FFFDF9 0%, #FAF5EC 100%);">
    <header class="flex items-center justify-between px-6 lg:px-20 py-5 sticky top-0 z-50" style="background: rgba(255,253,249,.85); backdrop-filter: blur(12px); border-bottom: 1px solid #F0E9DC;">
        <a href="{{ route('home') }}" class="font-serif text-2xl text-inktext">Ar<span class="text-gold">oo</span>s</a>
        <nav class="hidden md:flex items-center gap-8 text-sm text-muted">
            <a href="#categories" class="hover:text-gold transition">Categories</a>
            <a href="#couples" class="hover:text-gold transition">For couples</a>
            <a href="#providers" class="hover:text-gold transition">For providers</a>
            <a href="#faq" class="hover:text-gold transition">FAQ</a>
        </nav>
        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('marketplace.index') }}" class="btn-gold">Open marketplace</a>
            @else
                <a href="{{ route('login') }}" class="text-sm text-muted hover:text-gold transition">Sign in</a>
                <a href="{{ route('register') }}" class="btn-gold">Start free</a>
            @endauth
        </div>
    </header>

    <section class="max-w-5xl mx-auto px-6 pt-20 pb-16 text-center">
        <p class="eyebrow">Aroos Marketplace</p>
        <h1 class="font-serif text-4xl md:text-6xl mt-4 leading-tight text-inktext">Everything you need for your perfect wedding, all in one place.</h1>
        <p class="text-lg text-muted mt-6 max-w-2xl mx-auto">Discover talented wedding professionals, explore services, compare packages and bring your dream wedding to life with Aroos.</p>
        <div class="flex flex-wrap gap-3 justify-center mt-10">
            <a href="{{ auth()->check() ? route('marketplace.index') : route('register') }}" class="btn-gold px-8 py-3 text-base">Find providers</a>
            <a href="{{ auth()->check() ? route('provider.register') : route('register') }}" class="btn-ghost px-8 py-3 text-base">Become a service provider</a>
        </div>
    </section>

    <section id="categories" class="max-w-6xl mx-auto px-6 py-16">
        <p class="eyebrow text-center">Browse by service</p>
        <h2 class="font-serif text-3xl text-center mt-2 mb-10">Every part of the day, covered</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach($categories as $c)
                <div class="card overflow-hidden">
                    @if($c->image_path)
                        <img src="{{ asset('storage/'.$c->image_path) }}" alt="" class="h-32 w-full object-cover" loading="lazy">
                    @else
                        <div class="h-32 w-full flex items-center justify-center font-serif text-3xl text-white" style="background: linear-gradient(135deg, hsl({{ ($loop->index * 37) % 360 }}, 28%, 42%), #B08D57);" aria-hidden="true">{{ mb_substr($c->name, 0, 1) }}</div>
                    @endif
                    <div class="p-4">
                        <h3 class="font-serif text-lg leading-snug">{{ $c->name }}</h3>
                        <p class="text-xs text-muted mt-1">{{ $c->description }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="couples" class="py-16" style="background:#FFF; border-top:1px solid #F0E9DC; border-bottom:1px solid #F0E9DC;">
        <div class="max-w-6xl mx-auto px-6 grid md:grid-cols-2 gap-12">
            <div>
                <p class="eyebrow">For couples & planners</p>
                <h2 class="font-serif text-3xl mt-2 mb-6">How it works</h2>
                <ol class="space-y-4 text-sm">
                    @foreach(['Search verified providers by service, region, budget and date.', 'Explore portfolios, packages and prices before you reach out.', 'Send a request for your wedding and receive quotations.', 'Negotiate in one place — every offer is kept on record.', 'Accept the offer you like. The booking lands in your vendors and budget automatically.'] as $i => $step)
                        <li class="flex gap-4"><span class="font-serif text-2xl text-gold w-8 shrink-0">{{ $i + 1 }}</span><span class="pt-1.5">{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
            <div id="providers">
                <p class="eyebrow">For service providers</p>
                <h2 class="font-serif text-3xl mt-2 mb-6">Grow your wedding business</h2>
                <ol class="space-y-4 text-sm">
                    @foreach(['Register your business free with your existing Aroos account.', 'Publish your services — with packages and prices, or simply "request a quote".', 'Show your work: photos, videos, Instagram, TikTok and YouTube.', 'Get verified by Aroos and appear in the marketplace.', 'Answer requests, send quotations and manage bookings on your calendar.'] as $i => $step)
                        <li class="flex gap-4"><span class="font-serif text-2xl text-gold w-8 shrink-0">{{ $i + 1 }}</span><span class="pt-1.5">{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-6 py-16 grid md:grid-cols-3 gap-6">
        @foreach([
            ['Verified professionals', 'Every listed provider is checked by the Aroos team before they appear in the marketplace.'],
            ['Clear offers, no surprises', 'Quotations, counter-offers and the final agreed terms are all saved and visible to both sides.'],
            ['Connected to your plan', 'Confirmed bookings flow into your wedding\'s vendors, budget and report.'],
        ] as [$title, $text])
            <div class="card p-6"><h3 class="font-serif text-xl">{{ $title }}</h3><p class="text-sm text-muted mt-2">{{ $text }}</p></div>
        @endforeach
    </section>

    <section id="faq" class="max-w-3xl mx-auto px-6 py-16">
        <h2 class="font-serif text-3xl text-center mb-8">Frequently asked questions</h2>
        @foreach([
            ['Who can see providers?', 'Signed-in Aroos users planning a wedding. Only providers verified by Aroos are listed.'],
            ['Do providers have to publish prices?', 'No. Providers can publish fixed prices or packages, mark prices as negotiable, or simply invite you to request a quotation.'],
            ['Who sees my contact details?', 'Only the providers you send a request to, for that request.'],
            ['Does accepting a quote take payment?', 'No. Accepting confirms the booking and the agreed price; payment is arranged with the provider.'],
            ['How do I become a verified provider?', 'Register your business, complete your profile and submit your documents. The Aroos team reviews every application.'],
        ] as [$q, $a])
            <details class="card p-5 mb-3"><summary class="font-medium cursor-pointer">{{ $q }}</summary><p class="text-sm text-muted mt-3">{{ $a }}</p></details>
        @endforeach
    </section>

    <section class="py-20 text-center px-6" style="background: linear-gradient(135deg, #14110E, #241C13);">
        <h2 class="font-serif text-3xl" style="color:#F4EDE3;">Become a verified service provider</h2>
        <p class="mt-3 text-sm" style="color:#C9AB7C;">Showcase your work to couples planning their weddings on Aroos.</p>
        <a href="{{ auth()->check() ? route('provider.register') : route('register') }}" class="btn-gold px-8 py-3 mt-8">Register your business</a>
    </section>
    @include('legal._footer')
</div>
@endsection
