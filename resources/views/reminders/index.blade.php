@extends('layouts.app')

@section('title', 'Reminders')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Communications</p>
    <h1 class="font-serif text-3xl mt-1">Reminders</h1>
    <p class="text-sm text-muted mt-2 max-w-2xl">Send email and SMS messages to guests filtered by RSVP status, or pledge reminders to anyone with an outstanding balance. Use placeholders like <code class="text-gold">{name}</code>, <code class="text-gold">{couple}</code>, <code class="text-gold">{date}</code>, <code class="text-gold">{venue}</code>, <code class="text-gold">{rsvp_link}</code>, <code class="text-gold">{amount}</code>, <code class="text-gold">{balance}</code>.</p>
</div>

<div class="card p-6 mb-8">
    <p class="eyebrow mb-4">Create reminder</p>
    <form method="POST" action="{{ route('reminders.store', $wedding) }}" class="grid md:grid-cols-3 gap-3" id="reminder-form">
        @csrf
        <input name="name" placeholder="Name (e.g. RSVP follow-up) *" required class="lux-input">
        <select name="stage" class="lux-input" id="stage-select" onchange="document.getElementById('pledge-targets').style.display = this.value === 'pledge_reminder' ? 'block' : 'none'; document.getElementById('rsvp-targets').style.display = this.value === 'pledge_reminder' ? 'none' : 'block';">
            <option value="save_the_date">Stage: Save the Date</option>
            <option value="invitation">Stage: Invitation</option>
            <option value="rsvp_followup">Stage: RSVP follow-up</option>
            <option value="pledge_reminder">Stage: Pledge reminder</option>
            <option value="contribution_thanks">Stage: Contribution thanks</option>
            <option value="week_before">Stage: Week before</option>
            <option value="day_before">Stage: Day before</option>
            <option value="custom">Stage: Custom</option>
        </select>
        <select name="channel" class="lux-input">
            <option value="email">Channel: Email</option>
            <option value="sms">Channel: SMS</option>
            <option value="both">Channel: Email + SMS</option>
        </select>

        <div id="rsvp-targets" class="md:col-span-3">
            <p class="text-xs text-muted mb-2">Send to guests with RSVP status:</p>
            <div class="flex flex-wrap gap-3">
                @foreach(['pending' => 'Pending', 'attending' => 'Attending', 'maybe' => 'Maybe', 'declined' => 'Declined'] as $v => $l)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="target_rsvp_statuses[]" value="{{ $v }}" @checked(in_array($v, ['pending', 'attending', 'maybe'])) class="accent-[#B89668]"> {{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div id="pledge-targets" class="md:col-span-3" style="display:none">
            <p class="text-xs text-muted mb-2">Send to pledgers with status:</p>
            <div class="flex flex-wrap gap-3">
                @foreach(['pledged' => 'Pledged (unpaid)', 'partial' => 'Partially paid', 'fulfilled' => 'Fulfilled'] as $v => $l)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="target_pledge_statuses[]" value="{{ $v }}" @checked($v !== 'fulfilled') class="accent-[#B89668]"> {{ $l }}</label>
                @endforeach
            </div>
        </div>

        <input name="subject" placeholder="Email subject" class="lux-input md:col-span-3">
        <textarea name="body" rows="4" required placeholder="Dear {name}, ... " class="lux-input md:col-span-3"></textarea>
        <button class="btn-gold justify-self-start">Create reminder</button>
    </form>
</div>

<div class="space-y-4">
    @forelse($reminders as $reminder)
        <div class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h3 class="font-serif text-lg">{{ $reminder->name }}</h3>
                        <span class="text-xs border border-line rounded-full px-2.5 py-0.5 text-muted">{{ str_replace('_', ' ', $reminder->stage) }}</span>
                        <span class="text-xs border border-gold/40 rounded-full px-2.5 py-0.5 text-goldlight">{{ strtoupper($reminder->channel) }}</span>
                        @if($reminder->status === 'sent')
                            <span class="text-xs text-gold">Sent {{ $reminder->sent_at->format('M j, H:i') }}</span>
                        @endif
                    </div>
                    <p class="text-sm text-muted mt-1 max-w-xl truncate">{{ $reminder->subject ?? $reminder->body }}</p>
                    <p class="text-xs text-muted mt-1">{{ $reminder->logs_count }} deliveries logged</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reminders.logs', [$wedding, $reminder]) }}" class="btn-ghost text-xs">Logs</a>
                    <form method="POST" action="{{ route('reminders.send', [$wedding, $reminder]) }}" onsubmit="return confirm('Send this reminder to all matching recipients now?')">
                        @csrf
                        <button class="btn-gold text-xs">Send now</button>
                    </form>
                    <form method="POST" action="{{ route('reminders.destroy', [$wedding, $reminder]) }}" onsubmit="return confirm('Delete this reminder?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost text-xs hover:border-red-400 hover:text-red-400">×</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="card p-10 text-center text-muted">
            <p class="font-serif text-xl mb-2">No reminders yet</p>
            <p class="text-sm">Create save-the-dates, RSVP follow-ups and pledge reminders above.</p>
        </div>
    @endforelse
</div>
@endsection
