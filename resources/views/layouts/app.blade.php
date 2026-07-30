@extends('layouts.base')

@section('body')
<div class="flex min-h-screen">
    {{-- Sidebar --}}
    <aside class="w-64 shrink-0 border-r border-line bg-paper hidden lg:flex flex-col">
        <div class="px-6 py-6 border-b border-line">
            <a href="{{ route('weddings.index') }}" class="font-serif text-2xl text-cream">Wed<span class="text-gold">Flow</span></a>
            @isset($wedding)
                <p class="text-xs text-muted mt-2 truncate">{{ $wedding->coupleNames() }}</p>
            @endisset
        </div>
        @isset($wedding)
        <nav class="flex-1 overflow-y-auto py-4 text-sm">
            @php
                $nav = [
                    ['wedding.dashboard', 'Dashboard', '◆'],
                    ['checklist.index', 'Checklist', '✓'],
                    ['budget.index', 'Budget', '¤'],
                    ['guests.index', 'Guests', '♥'],
                    ['seating.index', 'Seating', '◉'],
                    ['vendors.index', 'Vendors', '✦'],
                    ['crew.index', 'Wedding Crew', '☰'],
                    ['timeline.index', 'Timeline', '◷'],
                    ['menus.index', 'Menus', '❧'],
                    ['inspiration.index', 'Inspiration', '✧'],
                    ['photos.index', 'Photos', '▣'],
                    ['memories.index', 'Memories', '♪'],
                    ['pledges.index', 'Pledges & Gifts', '❦'],
                    ['reminders.index', 'Reminders', '✉'],
                    ['collaborators.index', 'Collaborators', '⚭'],
                    ['website.edit', 'Website', '✎'],
                ];
            @endphp
            @foreach($nav as [$route, $label, $icon])
                <a href="{{ route($route, $wedding) }}"
                   class="flex items-center gap-3 px-6 py-2.5 transition {{ request()->routeIs(str_replace('.index', '.*', $route)) || request()->routeIs($route) ? 'text-gold bg-card border-r-2 border-gold' : 'text-cream/70 hover:text-cream hover:bg-card/50' }}">
                    <span class="text-gold/70 w-4 text-center">{{ $icon }}</span> {{ $label }}
                </a>
            @endforeach
        </nav>
        @endisset
        <div class="px-6 py-4 border-t border-line">
            @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.index') }}" class="block text-xs mb-3 {{ request()->routeIs('admin.*') ? 'text-gold' : 'text-muted hover:text-gold' }}">◆ Admin Panel</a>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-xs text-muted truncate">{{ auth()->user()->name }} <span class="text-gold/60">· {{ str_replace('_', ' ', auth()->user()->role) }}</span></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-xs text-muted hover:text-gold">Log out</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 min-w-0">
        {{-- Mobile top bar --}}
        <div class="lg:hidden flex items-center justify-between px-4 py-3 border-b border-line bg-paper">
            <a href="{{ route('weddings.index') }}" class="font-serif text-xl">Wed<span class="text-gold">Flow</span></a>
            @isset($wedding)
            <details class="relative">
                <summary class="btn-ghost cursor-pointer list-none">Menu</summary>
                <nav class="absolute right-0 mt-2 w-56 card p-2 z-50 max-h-96 overflow-y-auto">
                    @foreach($nav as [$route, $label, $icon])
                        <a href="{{ route($route, $wedding) }}" class="block px-3 py-2 rounded hover:bg-ink text-sm">{{ $label }}</a>
                    @endforeach
                </nav>
            </details>
            @endisset
        </div>

        <main class="p-6 lg:p-10 max-w-7xl">
            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg border border-gold/40 bg-gold/10 text-goldlight text-sm">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-lg border border-red-500/40 bg-red-500/10 text-red-300 text-sm">
                    <ul class="list-disc ml-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
@endsection
