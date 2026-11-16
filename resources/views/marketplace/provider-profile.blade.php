@extends('layouts.app')

@section('title', $provider->business_name)

@use('App\Marketplace\Money')

@section('content')
<a href="{{ route('marketplace.index', ['category' => $provider->primary_category_id]) }}" class="text-xs text-muted">← {{ $provider->primaryCategory?->name ?? 'Marketplace' }}</a>

@unless($provider->isListed())
    <div class="mt-3 px-4 py-3 rounded-lg text-sm" style="background: rgba(220,80,80,.08); border: 1px solid rgba(220,80,80,.3); color: #b04a4a;">
        Preview — this profile is not visible in the marketplace ({{ $provider->status === 'active' ? 'awaiting verification' : $provider->status }}).
    </div>
@endunless

<div class="card overflow-hidden mt-3 mb-6">
    @if($provider->coverUrl())
        <img src="{{ $provider->coverUrl() }}" alt="" class="h-56 md:h-72 w-full object-cover">
    @else
        <div class="h-40 w-full" style="background: linear-gradient(135deg, #2D2820, #B08D57);" aria-hidden="true"></div>
    @endif
    <div class="p-6 flex flex-wrap items-start gap-5">
        @if($provider->logoUrl())<img src="{{ $provider->logoUrl() }}" alt="{{ $provider->business_name }} logo" class="w-20 h-20 rounded-full object-cover border-4 border-white -mt-16 shadow bg-white">@endif
        <div class="flex-1 min-w-0">
            <h1 class="font-serif text-3xl">{{ $provider->business_name }}
                @if($provider->verification_status === 'verified')<span class="text-sm text-gold align-middle" title="Verified by Aroos">✔ Verified by Aroos</span>@endif
            </h1>
            <p class="text-sm text-muted mt-1">{{ $provider->categories->pluck('name')->join(' · ') }}</p>
            <p class="text-sm text-muted">{{ collect([$provider->city, $provider->district, $provider->region?->name])->filter()->join(', ') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($showPhone && $provider->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $provider->phone) }}" class="btn-ghost">📞 Call</a>@endif
            @if($showPhone && $provider->whatsapp)<a href="https://wa.me/{{ \App\Outreach\Phone::normalize($provider->whatsapp) }}" target="_blank" rel="noopener noreferrer" class="btn-ghost">💬 WhatsApp</a>@endif
            <form method="POST" action="{{ route('marketplace.favourite', $provider) }}">@csrf<button class="btn-ghost">{{ $isFavourite ? '♥ Saved' : '♡ Save' }}</button></form>
            <a href="#request" class="btn-gold">Request a quotation</a>
        </div>
    </div>
    <nav class="flex gap-5 px-6 border-t border-line text-sm overflow-x-auto" aria-label="Profile sections">
        @foreach(['overview' => 'Overview', 'portfolio' => 'Portfolio', 'services' => 'Services & packages', 'info' => 'Business info', 'request' => 'Request'] as $id => $label)
            <a href="#{{ $id }}" class="py-3 text-muted hover:text-gold whitespace-nowrap">{{ $label }}</a>
        @endforeach
    </nav>
</div>

<div class="grid lg:grid-cols-[1fr_360px] gap-6">
<div class="space-y-6 min-w-0">
    <section id="overview" class="card p-6">
        <h2 class="eyebrow mb-3">Overview</h2>
        <p class="text-sm whitespace-pre-line">{{ $provider->description ?: 'This provider has not added a description yet.' }}</p>
        @php $coverage = $provider->services->flatMap(fn ($s) => $s->coverage_regions ?? [])->unique(); @endphp
        @if($coverage->isNotEmpty())
            <p class="text-xs text-muted mt-4">Serves: {{ $regions->whereIn('id', $coverage)->pluck('name')->join(', ') }}</p>
        @endif
    </section>

    <section id="portfolio" class="card p-6">
        <h2 class="eyebrow mb-3">Portfolio</h2>
        @if($provider->media->isEmpty())
            <p class="text-sm text-muted">No portfolio yet.</p>
        @else
            @foreach($provider->media->groupBy(fn ($m) => $m->album ?: 'Gallery') as $album => $items)
                <h3 class="font-serif text-lg mt-4 mb-2">{{ $album }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($items as $m)
                        <figure class="rounded-lg overflow-hidden bg-canvas">
                            @if($m->type === 'image')
                                <a href="{{ $m->fileUrl() }}" target="_blank" rel="noopener"><img src="{{ $m->fileUrl() }}" alt="{{ $m->caption }}" class="w-full h-40 object-cover" loading="lazy"></a>
                            @elseif($m->type === 'video')
                                <video src="{{ $m->fileUrl() }}" controls preload="metadata" class="w-full h-40 object-cover bg-black"></video>
                            @elseif($m->embedUrl())
                                <iframe src="{{ $m->embedUrl() }}" class="w-full h-40" title="{{ $m->caption ?: 'Video' }}" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                            @else
                                <a href="{{ $m->url }}" target="_blank" rel="nofollow noopener noreferrer" class="flex h-40 items-center justify-center text-sm text-gold border border-line">▶ Watch on {{ $m->platform() ?? 'the web' }}</a>
                            @endif
                            @if($m->caption)<figcaption class="text-xs text-muted px-2 py-1.5">{{ $m->caption }}</figcaption>@endif
                        </figure>
                    @endforeach
                </div>
            @endforeach
        @endif
    </section>

    <section id="services" class="card p-6">
        <h2 class="eyebrow mb-3">Services & packages</h2>
        @foreach($provider->services as $s)
            <div class="border-t border-line first:border-0 py-5 first:pt-0">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-serif text-xl">{{ $s->title }}</h3>
                    <p class="text-sm">
                        @if($s->pricing_type === 'fixed' && $s->base_price !== null) <strong>{{ Money::format($s->base_price) }}</strong>
                        @elseif($s->startingPrice() !== null) <span class="text-xs text-muted">From</span> <strong>{{ Money::format($s->startingPrice()) }}</strong>
                        @else <span class="text-muted">Request a quotation</span>@endif
                        @if($s->is_negotiable || $s->pricing_type === 'negotiable')<span class="text-xs text-gold ml-1">· Negotiable</span>@endif
                    </p>
                </div>
                @if($s->description)<p class="text-sm text-muted mt-2 whitespace-pre-line">{{ $s->description }}</p>@endif
                <div class="grid md:grid-cols-2 gap-4 mt-3 text-sm">
                    @if($s->inclusions)<div><p class="text-xs text-muted mb-1">Included</p><ul class="list-disc ml-5">@foreach($s->inclusions as $i)<li>{{ $i }}</li>@endforeach</ul></div>@endif
                    @if($s->exclusions)<div><p class="text-xs text-muted mb-1">Not included</p><ul class="list-disc ml-5">@foreach($s->exclusions as $i)<li>{{ $i }}</li>@endforeach</ul></div>@endif
                </div>
                @if($s->activePackages->isNotEmpty())
                    <div class="grid md:grid-cols-3 gap-3 mt-4">
                        @foreach($s->activePackages as $pkg)
                            <div class="rounded-lg border border-line p-4">
                                <p class="font-medium">{{ $pkg->name }}</p>
                                <p class="font-serif text-xl text-gold mt-1">{{ $pkg->price !== null ? Money::format($pkg->price) : 'On request' }}</p>
                                @if($pkg->description)<p class="text-xs text-muted mt-2">{{ $pkg->description }}</p>@endif
                                @if($pkg->features)<ul class="text-xs mt-2 space-y-1">@foreach($pkg->features as $f)<li>✓ {{ $f }}</li>@endforeach</ul>@endif
                                @if($pkg->extras)<p class="text-xs text-muted mt-2">Extras: {{ collect($pkg->extras)->map(fn ($e) => $e['name'].($e['price'] !== null ? ' ('.Money::format($e['price']).')' : ''))->join(', ') }}</p>@endif
                                @if($pkg->deposit_percent)<p class="text-xs text-muted mt-1">Deposit: {{ $pkg->deposit_percent }}%</p>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </section>

    <section id="info" class="card p-6 text-sm">
        <h2 class="eyebrow mb-3">Business information</h2>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-2">
            @if($provider->address)<dt class="text-muted">Address</dt><dd>{{ $provider->address }}</dd>@endif
            <dt class="text-muted">Phone</dt><dd>{{ $showPhone ? ($provider->phone ?: '—') : 'Shared after you send a request' }}</dd>
            <dt class="text-muted">Email</dt><dd>{{ $showEmail ? ($provider->email ?: '—') : 'Shared after you send a request' }}</dd>
            @if($provider->website)<dt class="text-muted">Website</dt><dd><a href="{{ $provider->website }}" target="_blank" rel="nofollow noopener noreferrer" class="text-gold underline">{{ parse_url($provider->website, PHP_URL_HOST) }}</a></dd>@endif
            @if($provider->operating_hours['text'] ?? null)<dt class="text-muted">Hours</dt><dd>{{ $provider->operating_hours['text'] }}</dd>@endif
        </dl>
        @if($provider->social_links)
            <div class="flex flex-wrap gap-2 mt-4">
                @foreach($provider->social_links as $platform => $url)
                    <a href="{{ $url }}" target="_blank" rel="nofollow noopener noreferrer" class="btn-ghost text-xs">{{ ucfirst($platform) }}</a>
                @endforeach
            </div>
        @endif
    </section>
</div>

<aside id="request" class="card p-6 h-fit lg:sticky lg:top-6">
    <h2 class="font-serif text-xl mb-1">Request a quotation</h2>
    <p class="text-xs text-muted mb-4">The provider sees your request and contact details for this request only. Nothing is booked until you accept an offer.</p>
    @if($provider->memberFor(auth()->user()))
        <p class="text-sm text-muted">This is your business — customers see the request form here.</p>
    @elseif($weddings->isEmpty())
        <p class="text-sm text-muted">Create or join a wedding first, then request services for it.</p>
        <a href="{{ route('weddings.index') }}" class="btn-gold mt-3">My weddings</a>
    @elseif(! $provider->isListed())
        <p class="text-sm text-muted">This provider is not taking requests right now.</p>
    @else
        <form method="POST" action="{{ route('marketplace.enquire', $provider) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="client_token" value="{{ $clientToken }}">
            <label class="block text-xs text-muted">Wedding
                <select name="wedding_id" class="lux-input mt-1" required>
                    @foreach($weddings as $w)<option value="{{ $w->id }}" @selected(old('wedding_id') == $w->id)>{{ $w->coupleNames() }}{{ $w->wedding_date ? ' · '.$w->wedding_date->format('j M Y') : '' }}</option>@endforeach
                </select>
            </label>
            <label class="block text-xs text-muted">Service
                <select name="service_id" class="lux-input mt-1" required>
                    @foreach($provider->services as $s)<option value="{{ $s->id }}" @selected(old('service_id') == $s->id)>{{ $s->title }}</option>@endforeach
                </select>
            </label>
            @php $packages = $provider->services->flatMap(fn ($s) => $s->activePackages->map(fn ($p) => [$p, $s])); @endphp
            @if($packages->isNotEmpty())
                <label class="block text-xs text-muted">Package (optional)
                    <select name="package_id" class="lux-input mt-1">
                        <option value="">No package — I'll describe what I need</option>
                        @foreach($packages as [$pkg, $s])<option value="{{ $pkg->id }}" @selected(old('package_id') == $pkg->id)>{{ $s->title }} · {{ $pkg->name }}</option>@endforeach
                    </select>
                </label>
            @endif
            <div class="grid grid-cols-3 gap-2">
                <label class="block text-xs text-muted col-span-3">Event date <input type="date" name="event_date" value="{{ old('event_date', $weddings->first()?->wedding_date?->toDateString()) }}" min="{{ today()->toDateString() }}" required class="lux-input mt-1"></label>
                <label class="block text-xs text-muted">From <input type="time" name="event_start" value="{{ old('event_start') }}" class="lux-input mt-1"></label>
                <label class="block text-xs text-muted">To <input type="time" name="event_end" value="{{ old('event_end') }}" class="lux-input mt-1"></label>
                <label class="block text-xs text-muted">Guests <input type="number" name="guests" value="{{ old('guests') }}" min="1" class="lux-input mt-1"></label>
            </div>
            <input name="venue" value="{{ old('venue') }}" placeholder="Venue / location" class="lux-input" aria-label="Venue">
            <select name="region_id" class="lux-input" aria-label="Region">
                <option value="">Region</option>
                @foreach($regions as $r)<option value="{{ $r->id }}" @selected(old('region_id') == $r->id)>{{ $r->name }}</option>@endforeach
            </select>
            <input type="number" name="budget" value="{{ old('budget') }}" min="0" placeholder="Budget ({{ config('marketplace.currency') }}, optional)" class="lux-input" aria-label="Budget">
            <textarea name="requirements" rows="4" placeholder="What do you need? Special requests, timings, style…" class="lux-input" aria-label="Requirements">{{ old('requirements') }}</textarea>
            <div class="grid grid-cols-2 gap-2">
                <select name="preferred_contact" class="lux-input" aria-label="Preferred contact">
                    @foreach(['aroos' => 'Aroos messages', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $v => $l)<option value="{{ $v }}" @selected(old('preferred_contact') === $v)>{{ $l }}</option>@endforeach
                </select>
                <input name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" placeholder="Your phone" class="lux-input" aria-label="Your phone">
            </div>
            <button class="btn-gold w-full">Send request</button>
        </form>
    @endif
</aside>
</div>
@endsection
