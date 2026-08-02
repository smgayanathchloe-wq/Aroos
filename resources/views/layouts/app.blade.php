@extends('layouts.base')

@section('body')
<div class="flex min-h-screen">
    {{-- Sidebar (stays dark) --}}
    <aside class="w-64 shrink-0 hidden lg:flex flex-col no-print" style="background: #14110E; border-right: 1px solid #2A241E;">
        <div class="px-6 py-6" style="border-bottom: 1px solid #2A241E;">
            <a href="{{ route('weddings.index') }}" class="font-serif text-2xl" style="color: #F4EDE3;">Ar<span class="text-gold">oo</span>s</a>
            @isset($wedding)
                <p class="text-xs mt-2 truncate" style="color: #8A7E6F;">{{ $wedding->coupleNames() }}</p>
            @endisset
        </div>
        @isset($wedding)
        <nav class="flex-1 overflow-y-auto py-4 text-sm">
            @php
                $nav = [
                    ['wedding.dashboard', 'Dashboard', '📊'],
                    ['checklist.index', 'Checklist', '✅'],
                    ['budget.index', 'Budget', '💰'],
                    ['guests.index', 'Guests', '👥'],
                    ['seating.index', 'Seating', '🪑'],
                    ['floorplan.index', 'Floor Plan', '📐'],
                    ['checkin.index', 'Check-in & QR', '🎫'],
                    ['vendors.index', 'Vendors', '🤝'],
                    ['crew.index', 'Wedding Crew', '🧑‍🤝‍🧑'],
                    ['timeline.index', 'Timeline', '🕐'],
                    ['menus.index', 'Menus', '🍽️'],
                    ['inspiration.index', 'Inspiration', '💡'],
                    ['photos.index', 'Photos & Albums', '📸'],
                    ['memories.index', 'Memories', '💌'],
                    ['pledges.index', 'Pledges & Gifts', '🎁'],
                    ['reminders.index', 'Messages', '✉️'],
                    ['collaborators.index', 'Collaborators', '🔑'],
                    ['website.edit', 'Website', '🌐'],
                    ['report.index', 'Report', '📄'],
                ];
            @endphp
            @foreach($nav as [$route, $label, $icon])
                @php $active = request()->routeIs(str_replace('.index', '.*', $route)) || request()->routeIs($route) || ($route === 'website.edit' && request()->routeIs('website.*')); @endphp
                <a href="{{ route($route, $wedding) }}"
                   class="flex items-center gap-3 px-6 py-2 transition"
                   style="{{ $active ? 'color:#C9AB7C;background:#1B1713;border-right:2px solid #B08D57;' : 'color:rgba(244,237,227,.65);' }}">
                    <span class="w-5 text-center text-xs">{{ $icon }}</span> {{ $label }}
                </a>
            @endforeach
        </nav>
        @endisset
        <div class="px-6 py-4" style="border-top: 1px solid #2A241E;">
            @if(auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.index') }}" class="block text-xs mb-1.5" style="color: {{ request()->routeIs('admin.index') ? '#C9AB7C' : '#8A7E6F' }};">⚙️ Admin Panel</a>
                <a href="{{ route('admin.packages.index') }}" class="block text-xs mb-1.5" style="color: {{ request()->routeIs('admin.packages*') ? '#C9AB7C' : '#8A7E6F' }};">📦 Packages</a>
                <a href="{{ route('admin.settings') }}" class="block text-xs mb-1.5" style="color: {{ request()->routeIs('admin.settings*') ? '#C9AB7C' : '#8A7E6F' }};">🔧 Gateways & Payments</a>
                <a href="{{ route('admin.requests') }}" class="block text-xs mb-3" style="color: {{ request()->routeIs('admin.requests*') ? '#C9AB7C' : '#8A7E6F' }};">📥 Requests</a>
            @endif
            <div class="flex items-center justify-between">
                <span class="text-xs truncate" style="color: #8A7E6F;">{{ auth()->user()->name }} <span style="color: rgba(176,141,87,.7);">· {{ str_replace('_', ' ', auth()->user()->role) }}</span></span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-xs" style="color: #8A7E6F;">Log out</button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main (light theme) --}}
    <div class="flex-1 min-w-0 flex flex-col" style="background: #FAF7F2;">
        {{-- Mobile top bar --}}
        <div class="lg:hidden flex items-center justify-between px-4 py-3 no-print" style="background: #14110E; border-bottom: 1px solid #2A241E;">
            <a href="{{ route('weddings.index') }}" class="font-serif text-xl" style="color: #F4EDE3;">Ar<span style="color: #C4A560;">oo</span>s</a>
            @isset($wedding)
            <details class="relative">
                <summary class="cursor-pointer list-none text-sm px-3 py-1.5 rounded border" style="color: #F4EDE3; border-color: #2A241E;">Menu</summary>
                <nav class="absolute right-0 mt-2 w-60 card p-2 z-50 max-h-96 overflow-y-auto" style="background: #FAF7F2;">
                    @foreach($nav as [$route, $label, $icon])
                        <a href="{{ route($route, $wedding) }}" class="block px-3 py-2 rounded hover:bg-gray-200 text-sm" style="color: #14110E;">{{ $icon }} {{ $label }}</a>
                    @endforeach
                </nav>
            </details>
            @endisset
        </div>

        <main class="p-6 lg:p-10 max-w-7xl w-full flex-1" style="background: #FAF7F2;">
            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-lg text-sm no-print" style="background: rgba(196,165,96,.08); border: 1px solid rgba(196,165,96,.3); color: #7a6a3f;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-lg text-sm no-print" style="background: rgba(220,80,80,.08); border: 1px solid rgba(220,80,80,.3); color: #b04a4a;">
                    <ul class="list-disc ml-4">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>

        <footer class="text-center py-6 text-xs no-print" style="color: #8A7E6F; background: #FAF7F2;">Powered by <a href="https://letsventure.co.tz" class="hover:underline" style="color: #C4A560;">LetsVenture</a></footer>
    </div>
</div>
@endsection
