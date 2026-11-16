@extends('layouts.app')

@section('title', 'AI Outreach')

@section('content')
<div class="mb-6">
    <p class="eyebrow">Michango · AI</p>
    <h1 class="font-serif text-3xl mt-1">AI Pledge Outreach</h1>
    <p class="text-sm text-muted mt-2 max-w-2xl">{{ $config->get('branding.assistant_name') }} contacts pledgers on WhatsApp, SMS or by phone in {{ $config->language() === 'sw' ? 'Kiswahili' : 'English' }}, asks when they expect to complete their pledge, answers questions about the wedding, and records every answer here.</p>
    @unless($config->get('enabled'))
        <p class="text-sm mt-3 text-red-400">Outreach is switched off. Turn it on in <a class="underline" href="{{ route('outreach.settings', $wedding) }}">Settings</a> (<code>"enabled": true</code>) after adding credentials.</p>
    @endunless
</div>

@include('outreach._tabs')

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-5"><p class="eyebrow">Open promises</p><p class="font-serif text-2xl mt-2">{{ $stats['promises'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Scheduled contacts</p><p class="font-serif text-2xl mt-2">{{ $stats['pending_jobs'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Tasks for committee</p><p class="font-serif text-2xl mt-2">{{ $stats['tasks'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Opted out</p><p class="font-serif text-2xl mt-2">{{ $stats['opt_outs'] }}</p></div>
</div>

<form method="POST" id="bulk" class="card p-5 mb-4 grid gap-3 md:grid-cols-[1fr_auto_auto] items-end">
    @csrf
    <div>
        <p class="eyebrow mb-2">Campaign for selected pledgers</p>
        <div class="flex gap-4 text-sm flex-wrap">
            @foreach(['whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'voice' => 'Voice call'] as $v => $l)
                <label class="flex items-center gap-1.5"><input type="checkbox" name="channels[]" value="{{ $v }}" {{ $v === 'whatsapp' ? 'checked' : '' }}> {{ $l }}</label>
            @endforeach
            <label class="flex items-center gap-1.5">Send at <input type="datetime-local" name="scheduled_at" class="lux-input py-1"> <span class="text-xs text-muted">({{ $timezone }}; empty = now)</span></label>
        </div>
    </div>
    <button formaction="{{ route('outreach.campaign', $wedding) }}" class="btn-gold">Launch</button>
    <div class="flex gap-2">
        <input type="hidden" name="consent" value="1" id="consent-value">
        <button formaction="{{ route('outreach.consent', $wedding) }}" onclick="document.getElementById('consent-value').value=1" class="text-xs border border-line rounded-full px-3 py-2 text-muted hover:text-cream">Mark consent</button>
        <button formaction="{{ route('outreach.consent', $wedding) }}" onclick="document.getElementById('consent-value').value=0" class="text-xs border border-line rounded-full px-3 py-2 text-muted hover:text-cream">Remove consent</button>
    </div>
</form>

<div class="flex gap-1 mb-3">
    @foreach(['' => 'All', 'none' => 'Not pledged', 'pledged' => 'Pledged', 'partial' => 'Partial', 'paid' => 'Paid'] as $v => $l)
        <a href="{{ route('outreach.index', [$wedding, 'state' => $v]) }}" class="text-xs px-3 py-1 rounded-full border {{ request('state', '') === $v ? 'border-gold text-gold' : 'border-line text-muted hover:text-cream' }}">{{ $l }}</a>
    @endforeach
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-3 py-3"><input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)" aria-label="Select all"></th>
                <th class="px-3 py-3">Pledger</th>
                <th class="px-3 py-3">Status</th>
                <th class="px-3 py-3">Pledged / paid</th>
                <th class="px-3 py-3">Consent</th>
                <th class="px-3 py-3">Last contact</th>
                <th class="px-3 py-3">Last AI summary</th>
                <th class="px-3 py-3"></th>
            </tr>
        </thead>
        <tbody>
        @forelse($pledges as $p)
            @php $state = $p->outreachState(); @endphp
            <tr class="border-b border-line/50 align-top">
                <td class="px-3 py-3"><input type="checkbox" class="pick" name="pledge_ids[]" value="{{ $p->id }}" form="bulk" aria-label="Select {{ $p->pledger_name }}"></td>
                <td class="px-3 py-3"><p>{{ $p->pledger_name }}</p><p class="text-xs text-muted">{{ $p->pledger_phone ?? 'no phone' }}</p></td>
                <td class="px-3 py-3 text-xs">{{ ['none' => 'Not pledged', 'pledged' => 'Pledged', 'partial' => 'Partial', 'paid' => 'Paid'][$state] }}</td>
                <td class="px-3 py-3 text-xs">{{ number_format((float) $p->amount) }} / <span class="text-gold">{{ number_format($p->totalContributed()) }}</span></td>
                <td class="px-3 py-3 text-xs">{{ $p->outreach_consent ? 'Yes' : '—' }}</td>
                <td class="px-3 py-3 text-xs text-muted">{{ $p->last_contacted_at?->setTimezone($timezone)->format('M j, H:i') ?? '—' }}</td>
                <td class="px-3 py-3 text-xs text-muted max-w-xs">{{ \Illuminate\Support\Str::limit($p->last_ai_summary, 140) ?: '—' }}</td>
                <td class="px-3 py-3 text-right whitespace-nowrap">
                    @if($p->pledger_phone)
                        @foreach(['whatsapp' => 'WhatsApp', 'voice' => 'Call'] as $ch => $label)
                            <form method="POST" action="{{ route('outreach.contact', [$wedding, $p]) }}" class="inline">
                                @csrf <input type="hidden" name="channel" value="{{ $ch }}">
                                <button class="text-xs text-muted hover:text-gold ml-2">{{ $label }} now</button>
                            </form>
                        @endforeach
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-10 text-center text-muted">No pledgers yet. Add people on the Pledges page (amount 0 for those who have not pledged).</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
