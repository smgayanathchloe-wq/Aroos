@extends('layouts.base')

@section('title', 'A wedding, composed.')

@section('body')
<div class="min-h-screen flex flex-col">
    <header class="flex items-center justify-between px-6 lg:px-16 py-6">
        <span class="font-serif text-2xl">Wed<span class="text-gold">Flow</span></span>
        <nav class="flex items-center gap-4 text-sm">
            @auth
                <a href="{{ route('weddings.index') }}" class="btn-gold">My Weddings</a>
            @else
                <a href="{{ route('login') }}" class="text-cream/80 hover:text-gold">Sign in</a>
                <a href="{{ route('register') }}" class="btn-gold">Begin planning</a>
            @endauth
        </nav>
    </header>

    <section class="flex-1 flex flex-col items-center justify-center text-center px-6 py-24">
        <p class="eyebrow mb-6">Vol. I · The Modern Wedding Atelier</p>
        <h1 class="font-serif text-5xl lg:text-7xl leading-tight max-w-4xl">A wedding, <em class="text-gold">composed.</em></h1>
        <p class="mt-6 max-w-2xl text-cream/70 leading-relaxed">Budget, guests, seating, vendors, pledges and the whole story — kept with the quiet care of a planner you trust, in one calm space.</p>
        <div class="mt-10 flex gap-4">
            <a href="{{ route('register') }}" class="btn-gold px-8 py-3">Begin planning</a>
            <a href="{{ route('login') }}" class="btn-ghost px-8 py-3">Enter studio</a>
        </div>

        <div class="mt-24 grid grid-cols-2 lg:grid-cols-4 gap-6 max-w-4xl w-full">
            @foreach([
                ['Tariff', 'Track every shilling', 'Budget categories, pledges & contributions without the spreadsheet.'],
                ['Roster', 'Tend to the guests', 'RSVPs, dietaries, invites & reminders — one list, no sprawl.'],
                ['Room', 'Set the room', 'Tables, seating charts and the crew who runs the day.'],
                ['Hours', 'Hold the timeline', 'A day-of schedule your vendors, family and crew all share.'],
            ] as [$label, $title, $desc])
            <div class="card p-6 text-left">
                <p class="eyebrow">{{ $label }}</p>
                <h3 class="font-serif text-lg mt-2">{{ $title }}</h3>
                <p class="text-xs text-muted mt-2 leading-relaxed">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </section>

    <footer class="text-center py-8 text-xs text-muted border-t border-line">WedFlow — A wedding, composed.</footer>
</div>
@endsection
