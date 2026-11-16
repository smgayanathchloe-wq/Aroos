@extends('layouts.app')

@section('title', 'Follow-ups')

@section('content')
<h1 class="font-serif text-3xl mb-6">Follow-ups & questions</h1>
@include('outreach._tabs')

@php
    $labels = [
        'no_reply' => 'Reminder (no reply)', 'no_answer_retry' => 'Retry call (no answer)', 'callback' => 'Contact again (asked)',
        'promise_due' => 'Reminder before promised date', 'promise_passed' => 'Check-in after promised date',
        'low_confidence' => 'Review needed', 'verify_payment' => 'Verify claimed payment', 'review_amount' => 'Confirm pledge amount',
        'coordinator_question' => 'Answer guest question',
    ];
@endphp

<p class="eyebrow mb-3">Unanswered guest questions</p>
<div class="card p-5 mb-8 space-y-4">
    @forelse($questions as $q)
        <form method="POST" action="{{ route('outreach.questions.answer', [$wedding, $q]) }}" class="grid md:grid-cols-[1fr_1fr_auto] gap-2 items-start">
            @csrf
            <div>
                <input name="question" value="{{ $q->question }}" class="lux-input w-full" aria-label="Question">
                <p class="text-xs text-muted mt-1">{{ $q->pledge?->pledger_name }} · {{ $q->created_at->format('M j') }}</p>
            </div>
            <textarea name="answer" rows="2" placeholder="Answer (added to the knowledge base)" class="lux-input w-full" required></textarea>
            <button class="btn-gold">Save answer</button>
        </form>
    @empty
        <p class="text-sm text-muted">No unanswered questions. The knowledge base covered everything guests asked.</p>
    @endforelse
</div>

<p class="eyebrow mb-3">Follow-ups</p>
<div class="card overflow-x-auto mb-8">
    <table class="w-full text-sm">
        <thead><tr class="border-b border-line text-left text-xs text-muted">
            <th class="px-4 py-3">Due</th><th class="px-4 py-3">Pledger</th><th class="px-4 py-3">What</th><th class="px-4 py-3">How</th><th class="px-4 py-3">Notes</th><th class="px-4 py-3"></th>
        </tr></thead>
        <tbody>
        @forelse($followUps as $f)
            <tr class="border-b border-line/50 align-top">
                <td class="px-4 py-3 text-xs">{{ $f->due_at->setTimezone($timezone)->format('M j, H:i') }}</td>
                <td class="px-4 py-3">{{ $f->pledge?->pledger_name }}</td>
                <td class="px-4 py-3 text-xs">{{ $labels[$f->reason] ?? $f->reason }}</td>
                <td class="px-4 py-3 text-xs">{{ $f->channel ? 'Automatic · '.$f->channel : 'Committee task' }}</td>
                <td class="px-4 py-3 text-xs text-muted max-w-sm">{{ $f->notes }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @foreach(($f->channel ? ['cancelled' => 'Cancel'] : ['done' => 'Done', 'cancelled' => 'Dismiss']) as $status => $label)
                        <form method="POST" action="{{ route('outreach.followups.close', [$wedding, $f]) }}" class="inline">
                            @csrf <input type="hidden" name="status" value="{{ $status }}">
                            <button class="text-xs text-muted hover:text-gold ml-2">{{ $label }}</button>
                        </form>
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-muted">Nothing pending.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<p class="eyebrow mb-3">Scheduled campaign contacts</p>
<div class="card overflow-x-auto mb-8">
    <table class="w-full text-sm">
        <tbody>
        @forelse($jobs as $job)
            <tr class="border-b border-line/50">
                <td class="px-4 py-3 text-xs">{{ $job->scheduled_at->setTimezone($timezone)->format('M j, H:i') }}</td>
                <td class="px-4 py-3">{{ $job->pledge?->pledger_name }}</td>
                <td class="px-4 py-3 text-xs">{{ $job->channel }}</td>
                <td class="px-4 py-3 text-xs text-muted">{{ $job->result }}</td>
                <td class="px-4 py-3 text-right">
                    <form method="POST" action="{{ route('outreach.jobs.cancel', [$wedding, $job]) }}">@csrf<button class="text-xs text-muted hover:text-red-400">Cancel</button></form>
                </td>
            </tr>
        @empty
            <tr><td class="px-4 py-8 text-center text-muted">No scheduled contacts.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<p class="eyebrow mb-1">Do-not-contact list</p>
<p class="text-xs text-muted mb-3">Permanent. These numbers are never contacted again on any channel.</p>
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <tbody>
        @forelse($optOuts as $o)
            <tr class="border-b border-line/50">
                <td class="px-4 py-3">+{{ $o->phone }}</td>
                <td class="px-4 py-3 text-xs">{{ $o->channel ?? '—' }}</td>
                <td class="px-4 py-3 text-xs text-muted">{{ $o->reason }}</td>
                <td class="px-4 py-3 text-xs text-muted">{{ $o->created_at?->format('M j, Y') }}</td>
            </tr>
        @empty
            <tr><td class="px-4 py-8 text-center text-muted">Nobody has opted out.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
