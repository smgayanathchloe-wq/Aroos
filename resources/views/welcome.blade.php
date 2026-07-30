@extends('layouts.base')

@section('title', 'Plan a wedding they will never forget')

@section('body')
<div class="min-h-screen" style="background: linear-gradient(180deg, #FFFDF9 0%, #FAF5EC 100%);">

    {{-- Nav --}}
    <header class="flex items-center justify-between px-6 lg:px-20 py-5 sticky top-0 z-50" style="background: rgba(255,253,249,.85); backdrop-filter: blur(12px); border-bottom: 1px solid #F0E9DC;">
        <span class="font-serif text-2xl text-inktext">Ar<span class="text-gold">oo</span>s</span>
        <nav class="hidden md:flex items-center gap-8 text-sm text-muted">
            <a href="#story" class="hover:text-gold transition">The Experience</a>
            <a href="#features" class="hover:text-gold transition">What's Inside</a>
            <a href="#pricing" class="hover:text-gold transition">Packages</a>
            <a href="#contact" class="hover:text-gold transition">Talk to us</a>
        </nav>
        <div class="flex items-center gap-3">
            @auth
                <a href="{{ route('weddings.index') }}" class="btn-gold">My Weddings</a>
            @else
                <a href="{{ route('login') }}" class="text-sm text-muted hover:text-gold transition">Sign in</a>
                <a href="{{ route('register') }}" class="btn-gold">Start free</a>
            @endauth
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full opacity-40" style="background: radial-gradient(circle, #F3E3C8, transparent 70%);"></div>
        <div class="absolute top-40 -left-24 w-72 h-72 rounded-full opacity-30" style="background: radial-gradient(circle, #EAD8BC, transparent 70%);"></div>

        <div class="max-w-6xl mx-auto px-6 pt-20 pb-16 text-center relative">
            <p class="font-script text-3xl text-gold mb-4">From "yes" to "I do" — beautifully</p>
            <h1 class="font-serif text-5xl lg:text-7xl leading-[1.08] text-inktext max-w-4xl mx-auto">
                Every detail of your wedding, in <em class="text-gold not-italic font-serif italic">one graceful place</em>
            </h1>
            <p class="mt-6 max-w-2xl mx-auto text-muted leading-relaxed">
                The guest list settles itself. Pledges arrive with gentle reminders. Every seat has a name,
                every hour has a plan — and your wedding website leaves everyone speechless.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-4">
                <a href="{{ route('register') }}" class="btn-gold px-9 py-3.5 text-base">Begin your story</a>
                <a href="#pricing" class="btn-ghost px-9 py-3.5 text-base">See packages</a>
            </div>
            <p class="mt-5 text-xs text-muted">Free to start · No card needed · Built for weddings in East Africa & beyond</p>

            {{-- Hero visual: dashboard mockup --}}
            <div class="mt-16 mx-auto max-w-4xl rounded-2xl overflow-hidden text-left" style="box-shadow: 0 40px 90px -30px rgba(140, 110, 65, .35); border: 1px solid #EFE7D8;">
                <div class="flex items-center gap-2 px-5 py-3" style="background:#14110E;">
                    <span class="w-3 h-3 rounded-full" style="background:#5A5148"></span>
                    <span class="w-3 h-3 rounded-full" style="background:#5A5148"></span>
                    <span class="w-3 h-3 rounded-full" style="background:#B08D57"></span>
                    <span class="ml-3 text-xs" style="color:#8A7E6F">aroos · wedding studio</span>
                </div>
                <div class="grid md:grid-cols-4 gap-4 p-6" style="background:#FFFDF9;">
                    <div class="card p-4"><p class="eyebrow">Days to go</p><p class="font-serif text-3xl mt-1 text-inktext">152</p><p class="text-xs text-muted mt-1">Dec 19 · Serena Ballroom</p></div>
                    <div class="card p-4"><p class="eyebrow">Guests</p><p class="font-serif text-3xl mt-1 text-inktext">248</p><p class="text-xs text-muted mt-1">184 confirmed · 12 tables set</p></div>
                    <div class="card p-4"><p class="eyebrow">Pledges collected</p><p class="font-serif text-3xl mt-1 text-gold">78%</p><p class="text-xs text-muted mt-1">Reminders sent automatically</p></div>
                    <div class="card p-4"><p class="eyebrow">Checked in</p><p class="font-serif text-3xl mt-1 text-inktext">231</p><p class="text-xs text-muted mt-1">QR scan at the door</p></div>
                    <div class="md:col-span-4 card p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full gold-gradient flex items-center justify-center text-white text-sm">✓</span>
                            <div>
                                <p class="text-sm text-inktext">SMS sent to 23 guests with pending pledges</p>
                                <p class="text-xs text-muted">"Habari {name}, salio lako ni TZS {balance}…"</p>
                            </div>
                        </div>
                        <span class="text-xs px-3 py-1 rounded-full" style="background:rgba(176,141,87,.12); color:#8a6d3f;">Delivered</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Story strip --}}
    <section id="story" class="max-w-6xl mx-auto px-6 py-24">
        <div class="text-center mb-14">
            <p class="eyebrow">The experience</p>
            <h2 class="font-serif text-4xl text-inktext mt-2">Imagine your wedding month feeling <em class="italic text-gold">calm</em></h2>
        </div>
        <div class="grid md:grid-cols-3 gap-10">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-2xl mb-5" style="background:#F7EFE2;">🕊️</div>
                <h3 class="font-serif text-xl text-inktext">Before the day</h3>
                <p class="text-sm text-muted mt-3 leading-relaxed">A countdown website your guests adore. Budget, vendors and checklist quietly on track. Pledges collected with polite SMS nudges — you never have to ask twice.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-2xl mb-5" style="background:#F7EFE2;">💍</div>
                <h3 class="font-serif text-xl text-inktext">On the day</h3>
                <p class="text-sm text-muted mt-3 leading-relaxed">Guests scan their elegant QR card at the door, find their exact table and seat on a 2D hall plan, and the crew always knows who has arrived — live.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-2xl mb-5" style="background:#F7EFE2;">🌅</div>
                <h3 class="font-serif text-xl text-inktext">After the day</h3>
                <p class="text-sm text-muted mt-3 leading-relaxed">Your website transforms into a gallery of the night — albums by moment and place, thank-you messages, and one full report of everything, ready to print.</p>
            </div>
        </div>
    </section>

    {{-- Features (indirect showcase) --}}
    <section id="features" class="py-24" style="background:#FFF; border-top:1px solid #F0E9DC; border-bottom:1px solid #F0E9DC;">
        <div class="max-w-6xl mx-auto px-6">
            <div class="text-center mb-14">
                <p class="eyebrow">What's inside</p>
                <h2 class="font-serif text-4xl text-inktext mt-2">Twelve quiet helpers, one graceful studio</h2>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach([
                    ['💰', 'Budget that behaves', 'Categories, spend, and what remains — at a glance.'],
                    ['🎁', 'Pledges & michango', 'Track promises, record M-Pesa & bank contributions.'],
                    ['✉️', 'SMS & email that lands', 'Remind pledgers, invite guests, brief vendors & crew.'],
                    ['👥', 'A guest list with manners', 'RSVPs, dietaries, parties and sides — organised.'],
                    ['🪑', 'Seating without tears', '2D hall plan with every table and chair visible.'],
                    ['🎫', 'QR arrival cards', 'Stylish printable cards. Scan at the door — checked in.'],
                    ['🌐', 'A website that stuns', 'Countdown before. A living gallery after. Yours always.'],
                    ['📄', 'One-click full report', 'Everything that happened, beautifully summarised.'],
                ] as [$icon, $title, $desc])
                <div class="card p-6 hover:shadow-lg transition" style="border-color:#F0E9DC;">
                    <p class="text-2xl">{{ $icon }}</p>
                    <h3 class="font-serif text-lg text-inktext mt-3">{{ $title }}</h3>
                    <p class="text-xs text-muted mt-2 leading-relaxed">{{ $desc }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="max-w-6xl mx-auto px-6 py-24">
        <div class="text-center mb-14">
            <p class="eyebrow">Packages</p>
            <h2 class="font-serif text-4xl text-inktext mt-2">Priced for one perfect day</h2>
            <p class="text-sm text-muted mt-3">Start free. Upgrade once. No subscriptions, ever.</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6 items-stretch">
            {{-- Harusi --}}
            <div class="card p-8 flex flex-col">
                <p class="eyebrow">Harusi</p>
                <p class="font-serif text-4xl text-inktext mt-3">Free</p>
                <p class="text-xs text-muted mt-1">forever</p>
                <ul class="text-sm text-muted mt-6 space-y-2.5 flex-1">
                    <li>✓ 1 wedding</li>
                    <li>✓ Up to 100 guests</li>
                    <li>✓ Checklist & budget</li>
                    <li>✓ Seating & 2D floor plan</li>
                    <li>✓ Wedding website (1 design)</li>
                    <li>✓ 2 collaborators</li>
                </ul>
                <a href="{{ route('register') }}" class="btn-ghost text-center mt-8">Start free</a>
            </div>
            {{-- Shangwe --}}
            <div class="card p-8 flex flex-col relative" style="border-color:#B08D57; box-shadow: 0 20px 50px -20px rgba(176,141,87,.4);">
                <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-xs px-4 py-1 rounded-full text-white gold-gradient">Most loved</span>
                <p class="eyebrow">Shangwe</p>
                <p class="font-serif text-4xl text-inktext mt-3">TZS 120,000</p>
                <p class="text-xs text-muted mt-1">one-time</p>
                <ul class="text-sm text-muted mt-6 space-y-2.5 flex-1">
                    <li>✓ Everything in Harusi</li>
                    <li>✓ Unlimited guests</li>
                    <li>✓ SMS & email campaigns</li>
                    <li>✓ Pledge & contribution tracking</li>
                    <li>✓ QR arrival cards + live check-in</li>
                    <li>✓ All website designs + after-wedding gallery</li>
                    <li>✓ Full wedding report</li>
                </ul>
                <a href="#contact" class="btn-gold text-center mt-8">Choose Shangwe</a>
            </div>
            {{-- Studio --}}
            <div class="card p-8 flex flex-col">
                <p class="eyebrow">Studio</p>
                <p class="font-serif text-4xl text-inktext mt-3">TZS 450,000</p>
                <p class="text-xs text-muted mt-1">per year · for planners</p>
                <ul class="text-sm text-muted mt-6 space-y-2.5 flex-1">
                    <li>✓ Everything in Shangwe</li>
                    <li>✓ Unlimited weddings</li>
                    <li>✓ Client (couple) view-only accounts</li>
                    <li>✓ Team collaboration & roles</li>
                    <li>✓ Priority support</li>
                    <li>✓ Your branding on guest pages</li>
                </ul>
                <a href="#contact" class="btn-ghost text-center mt-8">Talk to us</a>
            </div>
        </div>
    </section>

    {{-- Quote --}}
    <section class="py-20 text-center px-6" style="background: linear-gradient(135deg, #14110E, #241C13);">
        <p class="font-script text-4xl" style="color:#C9AB7C;">"Nobody believed we planned it ourselves."</p>
        <p class="text-xs mt-4 tracking-widest uppercase" style="color:#8A7E6F;">Married couples across Tanzania, Kenya & beyond</p>
    </section>

    {{-- Contact / Request --}}
    <section id="contact" class="max-w-4xl mx-auto px-6 py-24">
        <div class="text-center mb-10">
            <p class="eyebrow">Karibu</p>
            <h2 class="font-serif text-4xl text-inktext mt-2">Tell us about your day</h2>
            <p class="text-sm text-muted mt-3">Request a package, a demo, or just ask — we reply within 24 hours.</p>
        </div>

        @if(session('success'))
            <div class="mb-6 px-4 py-3 rounded-lg text-sm text-center" style="background: rgba(176,141,87,.1); border: 1px solid rgba(176,141,87,.35); color:#8a6d3f;">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-lg text-sm" style="background: rgba(220,80,80,.08); border:1px solid rgba(220,80,80,.3); color:#b04a4a;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('landing.contact') }}" class="card p-8 grid md:grid-cols-2 gap-4">
            @csrf
            <div><label class="text-xs text-muted block mb-1.5">Your name *</label><input name="name" required value="{{ old('name') }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">Email *</label><input type="email" name="email" required value="{{ old('email') }}" class="lux-input"></div>
            <div><label class="text-xs text-muted block mb-1.5">Phone / WhatsApp</label><input name="phone" value="{{ old('phone') }}" class="lux-input" placeholder="+255…"></div>
            <div><label class="text-xs text-muted block mb-1.5">Package of interest</label>
                <select name="package" class="lux-input">
                    <option value="">Just exploring</option>
                    <option value="harusi" @selected(old('package') === 'harusi')>Harusi (Free)</option>
                    <option value="shangwe" @selected(old('package') === 'shangwe')>Shangwe</option>
                    <option value="studio" @selected(old('package') === 'studio')>Studio (Planners)</option>
                </select>
            </div>
            <div><label class="text-xs text-muted block mb-1.5">Wedding date (if known)</label><input type="date" name="wedding_date" value="{{ old('wedding_date') }}" class="lux-input"></div>
            <div class="md:col-span-2"><label class="text-xs text-muted block mb-1.5">Message</label><textarea name="message" rows="4" class="lux-input" placeholder="Tell us about your wedding, your dreams, your questions…">{{ old('message') }}</textarea></div>
            <button class="btn-gold md:col-span-2 py-3">Send request</button>
        </form>
    </section>

    <footer class="text-center py-10 text-xs text-muted" style="border-top:1px solid #F0E9DC;">
        <span class="font-serif text-lg text-inktext">Ar<span class="text-gold">oo</span>s</span>
        <p class="mt-2">Every love story, beautifully organised.</p>
        <p class="mt-3">Powered by <a href="https://letsventure.co.tz" class="text-gold hover:underline">LetsVenture</a></p>
    </footer>
</div>
@endsection
