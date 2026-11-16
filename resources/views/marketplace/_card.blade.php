{{-- Provider card. Expects $p (ProviderProfile) and optional $favourite (bool). --}}
<article class="card overflow-hidden flex flex-col">
    <a href="{{ route('marketplace.provider', $p) }}" class="block relative">
        @if($p->coverUrl())
            <img src="{{ $p->coverUrl() }}" alt="" class="h-40 w-full object-cover" loading="lazy">
        @else
            <div class="h-40 w-full" style="background: linear-gradient(135deg, #2D2820, #B08D57);" aria-hidden="true"></div>
        @endif
        @if($p->isSponsoredNow())
            <span class="absolute top-2 left-2 text-[10px] uppercase tracking-wide bg-white/90 rounded px-2 py-0.5">Sponsored</span>
        @endif
    </a>
    <div class="p-4 flex-1 flex flex-col">
        <div class="flex items-start gap-3">
            @if($p->logoUrl())<img src="{{ $p->logoUrl() }}" alt="" class="w-10 h-10 rounded-full object-cover border border-line -mt-9 bg-white">@endif
            <div class="min-w-0">
                <h3 class="font-serif text-lg leading-snug"><a href="{{ route('marketplace.provider', $p) }}" class="hover:text-gold">{{ $p->business_name }}</a>
                    @if($p->verification_status === 'verified')<span class="text-xs text-gold align-middle" title="Verified by Aroos">✔ Verified</span>@endif
                </h3>
                <p class="text-xs text-muted">{{ $p->primaryCategory?->name }} · {{ $p->region?->name }}{{ $p->city ? ', '.$p->city : '' }}</p>
                @if($p->rating_count)<p class="text-xs mt-0.5"><span class="text-gold">★ {{ number_format((float) $p->rating_avg, 1) }}</span> <span class="text-muted">({{ $p->rating_count }})</span></p>@endif
            </div>
        </div>
        <p class="text-sm text-muted mt-3 line-clamp-2">{{ \Illuminate\Support\Str::limit($p->description, 120) }}</p>
        <div class="mt-auto pt-4 flex items-center justify-between gap-2">
            <p class="text-sm">
                @if($p->starting_price !== null)
                    <span class="text-xs text-muted">From</span> <strong>{{ \App\Marketplace\Money::format($p->starting_price) }}</strong>
                @else
                    <span class="text-xs text-muted">Price on request</span>
                @endif
            </p>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('marketplace.favourite', $p) }}">@csrf
                    <button class="text-lg leading-none {{ ($favourite ?? false) ? 'text-gold' : 'text-muted hover:text-gold' }}" aria-label="{{ ($favourite ?? false) ? 'Remove from saved' : 'Save provider' }}">{{ ($favourite ?? false) ? '♥' : '♡' }}</button>
                </form>
                <a href="{{ route('marketplace.provider', $p) }}" class="btn-ghost text-xs">View profile</a>
            </div>
        </div>
    </div>
</article>
