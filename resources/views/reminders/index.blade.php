@extends('layouts.app')

@section('title', 'Messages')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Communications</p>
    <h1 class="font-serif text-3xl mt-1 text-inktext">Messages & Reminders</h1>
    <p class="text-sm text-muted mt-2 max-w-2xl">Send SMS or email to exactly the right people: guests by RSVP or attendance, pledgers with balances, vendors, the wedding crew, or one specific person. Placeholders: <code class="text-gold">{name}</code> <code class="text-gold">{couple}</code> <code class="text-gold">{date}</code> <code class="text-gold">{venue}</code> <code class="text-gold">{table}</code> <code class="text-gold">{rsvp_link}</code> <code class="text-gold">{amount}</code> <code class="text-gold">{balance}</code> <code class="text-gold">{team}</code></p>
</div>

<div class="card p-6 mb-8">
    <p class="eyebrow mb-4">New message</p>
    <form method="POST" action="{{ route('reminders.store', $wedding) }}" class="space-y-4">
        @csrf
        <div class="grid md:grid-cols-3 gap-3">
            <input name="name" placeholder="Internal name (e.g. Pledge reminder round 2) *" required class="lux-input">
            <select name="audience" class="lux-input" id="audience-select"
                onchange="document.querySelectorAll('.aud-panel').forEach(el => el.style.display = 'none'); const t = document.getElementById('aud-' + this.value); if (t) t.style.display = 'block';">
                <option value="guests">To: Guests</option>
                <option value="pledgers">To: Pledgers (michango)</option>
                <option value="vendors">To: Vendors</option>
                <option value="crew">To: Wedding crew</option>
                <option value="person">To: A specific person</option>
            </select>
            <select name="channel" class="lux-input">
                <option value="sms">Via: SMS</option>
                <option value="email">Via: Email</option>
                <option value="both">Via: Email + SMS</option>
            </select>
        </div>

        {{-- Guests panel --}}
        <div id="aud-guests" class="aud-panel rounded-lg p-4" style="background:#FAF7F2; border:1px solid #F0E9DC;">
            <p class="text-xs text-muted mb-2">Guests with RSVP status:</p>
            <div class="flex flex-wrap gap-4 mb-3">
                @foreach(['pending' => 'Pending', 'attending' => 'Attending', 'maybe' => 'Maybe', 'declined' => 'Declined'] as $v => $l)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="target_rsvp_statuses[]" value="{{ $v }}" @checked(in_array($v, ['pending', 'attending', 'maybe'])) class="accent-[#B08D57]"> {{ $l }}</label>
                @endforeach
            </div>
            <p class="text-xs text-muted mb-2">Attendance (day-of):</p>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="attendance" value="any" checked class="accent-[#B08D57]"> Everyone</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="attendance" value="checked_in" class="accent-[#B08D57]"> Only checked-in (attended)</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="attendance" value="not_checked_in" class="accent-[#B08D57]"> Not yet arrived</label>
            </div>
        </div>

        {{-- Pledgers panel --}}
        <div id="aud-pledgers" class="aud-panel rounded-lg p-4" style="display:none; background:#FAF7F2; border:1px solid #F0E9DC;">
            <p class="text-xs text-muted mb-2">Pledgers with status:</p>
            <div class="flex flex-wrap gap-4">
                @foreach(['pledged' => 'Pledged (nothing paid)', 'partial' => 'Partially paid', 'fulfilled' => 'Fulfilled (say thanks!)'] as $v => $l)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="target_pledge_statuses[]" value="{{ $v }}" @checked($v !== 'fulfilled') class="accent-[#B08D57]"> {{ $l }}</label>
                @endforeach
            </div>
        </div>

        {{-- Vendors panel --}}
        <div id="aud-vendors" class="aud-panel rounded-lg p-4" style="display:none; background:#FAF7F2; border:1px solid #F0E9DC;">
            <p class="text-xs text-muted mb-2">Vendor status filter (optional):</p>
            <select name="vendor_status" class="lux-input w-56">
                <option value="">All vendors</option>
                @foreach(['booked', 'quote', 'meeting', 'contacted', 'researching'] as $s)
                    <option value="{{ $s }}">{{ ucfirst($s) }} only</option>
                @endforeach
            </select>
            <p class="text-xs text-muted mt-2">{{ $wedding->vendors->count() }} vendors in the system.</p>
        </div>

        {{-- Crew panel --}}
        <div id="aud-crew" class="aud-panel rounded-lg p-4" style="display:none; background:#FAF7F2; border:1px solid #F0E9DC;">
            <p class="text-xs text-muted">Sends to every crew member with a phone/email. {{ $wedding->crewTeams->flatMap->members->count() }} members across {{ $wedding->crewTeams->count() }} teams. Use <code class="text-gold">{team}</code> in your message.</p>
        </div>

        {{-- Person panel --}}
        <div id="aud-person" class="aud-panel rounded-lg p-4" style="display:none; background:#FAF7F2; border:1px solid #F0E9DC;">
            <div class="grid md:grid-cols-3 gap-3">
                <input name="person_name" placeholder="Name" class="lux-input">
                <input type="email" name="person_email" placeholder="Email" class="lux-input">
                <input name="person_phone" placeholder="Phone (+255…)" class="lux-input">
            </div>
        </div>

        <input name="subject" placeholder="Email subject (used for email only)" class="lux-input">
        <textarea name="body" rows="4" required placeholder="Habari {name}, …" class="lux-input"></textarea>
        <button class="btn-gold">Create message</button>
    </form>
</div>

<div class="space-y-4">
    @forelse($reminders as $reminder)
        <div class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-serif text-lg text-inktext">{{ $reminder->name }}</h3>
                        <span class="text-xs px-2.5 py-0.5 rounded-full" style="background:rgba(176,141,87,.12); color:#8a6d3f;">To: {{ ucfirst($reminder->audience ?? 'guests') }}</span>
                        <span class="text-xs px-2.5 py-0.5 rounded-full border" style="border-color:#E8E2D9; color:#8A7E6F;">{{ strtoupper($reminder->channel) }}</span>
                        @if($reminder->status === 'sent')
                            <span class="text-xs text-gold">Sent {{ $reminder->sent_at->format('M j, H:i') }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-muted mt-1 max-w-xl truncate">{{ $reminder->subject ?: $reminder->body }}</p>
                    <p class="text-xs text-muted mt-1">{{ $reminder->logs_count }} deliveries logged</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reminders.preview', [$wedding, $reminder]) }}" class="btn-ghost text-xs">Preview recipients</a>
                    <a href="{{ route('reminders.logs', [$wedding, $reminder]) }}" class="btn-ghost text-xs">Logs</a>
                    <form method="POST" action="{{ route('reminders.send', [$wedding, $reminder]) }}" onsubmit="return confirm('Send now to all matching recipients?')">
                        @csrf
                        <button class="btn-gold text-xs">Send now</button>
                    </form>
                    <form method="POST" action="{{ route('reminders.destroy', [$wedding, $reminder]) }}" onsubmit="return confirm('Delete this message?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost text-xs">×</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="card p-10 text-center text-muted">
            <p class="font-serif text-xl mb-2 text-inktext">No messages yet</p>
            <p class="text-sm">Create pledge reminders, guest invites, vendor briefs and crew notes above.</p>
        </div>
    @endforelse
</div>
@endsection
