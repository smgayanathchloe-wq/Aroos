@extends('layouts.app')

@section('title', 'Pledges & Gifts')

@section('content')
<div class="mb-8">
    <p class="eyebrow">Michango</p>
    <h1 class="font-serif text-3xl mt-1">Pledges & Contributions</h1>
    <p class="text-sm text-muted mt-2 max-w-2xl">Record pledges from family and friends, track contributions as they arrive, and send reminders to anyone with an outstanding balance from the Reminders page.</p>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="card p-5"><p class="eyebrow">Total pledged</p><p class="font-serif text-2xl mt-2">{{ number_format((float) $stats['total_pledged']) }} <span class="text-xs text-muted">{{ $wedding->currency }}</span></p></div>
    <div class="card p-5"><p class="eyebrow">Collected</p><p class="font-serif text-2xl mt-2 text-gold">{{ number_format((float) $stats['total_contributed']) }}</p></div>
    <div class="card p-5"><p class="eyebrow">Fulfilled</p><p class="font-serif text-2xl mt-2">{{ $stats['fulfilled'] }}</p></div>
    <div class="card p-5"><p class="eyebrow">Outstanding</p><p class="font-serif text-2xl mt-2">{{ $stats['outstanding'] }}</p></div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mb-8">
    <div class="card p-6">
        <p class="eyebrow mb-4">Record a pledge</p>
        <form method="POST" action="{{ route('pledges.store', $wedding) }}" class="grid grid-cols-2 gap-3">
            @csrf
            <input name="pledger_name" placeholder="Pledger name *" required class="lux-input col-span-2">
            <input type="email" name="pledger_email" placeholder="Email" class="lux-input">
            <input name="pledger_phone" placeholder="Phone (+255…)" class="lux-input">
            <input type="number" step="0.01" name="amount" placeholder="Amount ({{ $wedding->currency }}) *" required class="lux-input">
            <input type="date" name="due_date" title="Due date" class="lux-input">
            <select name="guest_id" class="lux-input col-span-2">
                <option value="">Link to a guest (optional)</option>
                @foreach($wedding->guests as $g)
                    <option value="{{ $g->id }}">{{ $g->fullName() }}</option>
                @endforeach
            </select>
            <button class="btn-gold col-span-2">Record pledge</button>
        </form>
    </div>

    <div class="card p-6">
        <p class="eyebrow mb-4">Record a contribution</p>
        <form method="POST" action="{{ route('contributions.store', $wedding) }}" class="grid grid-cols-2 gap-3">
            @csrf
            <input name="contributor_name" placeholder="Contributor name *" required class="lux-input col-span-2">
            <input type="number" step="0.01" name="amount" placeholder="Amount *" required class="lux-input">
            <select name="method" class="lux-input">
                @foreach(['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'bank' => 'Bank transfer', 'paypal' => 'PayPal', 'stripe' => 'Card (Stripe)', 'other' => 'Other'] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
            <select name="pledge_id" class="lux-input col-span-2">
                <option value="">Apply to a pledge (optional)</option>
                @foreach($pledges->whereIn('status', ['pledged', 'partial']) as $p)
                    <option value="{{ $p->id }}">{{ $p->pledger_name }} — balance {{ number_format($p->balance()) }}</option>
                @endforeach
            </select>
            <input name="reference" placeholder="Reference / receipt no." class="lux-input">
            <input type="date" name="received_on" value="{{ now()->format('Y-m-d') }}" class="lux-input">
            <button class="btn-gold col-span-2">Record contribution</button>
        </form>
    </div>
</div>

<div class="flex items-center gap-3 mb-4">
    <p class="eyebrow">Pledges</p>
    <div class="flex gap-1">
        @foreach(['' => 'All', 'pledged' => 'Pledged', 'partial' => 'Partial', 'fulfilled' => 'Fulfilled'] as $v => $l)
            <a href="{{ route('pledges.index', [$wedding, 'status' => $v]) }}" class="text-xs px-3 py-1 rounded-full border {{ request('status', '') === $v ? 'border-gold text-gold' : 'border-line text-muted hover:text-cream' }}">{{ $l }}</a>
        @endforeach
    </div>
</div>

<div class="card overflow-x-auto mb-10">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">Pledger</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Paid</th>
                <th class="px-4 py-3">Balance</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Due</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($pledges as $pledge)
                <tr class="border-b border-line/50 hover:bg-ink/40">
                    <td class="px-4 py-3">
                        <p>{{ $pledge->pledger_name }}</p>
                        <p class="text-xs text-muted">{{ $pledge->pledger_email ?? $pledge->pledger_phone }}</p>
                    </td>
                    <td class="px-4 py-3">{{ number_format((float) $pledge->amount) }}</td>
                    <td class="px-4 py-3 text-gold">{{ number_format($pledge->totalContributed()) }}</td>
                    <td class="px-4 py-3">{{ number_format($pledge->balance()) }}</td>
                    <td class="px-4 py-3">
                        @php $pc = ['pledged' => 'text-muted border-line', 'partial' => 'text-goldlight border-gold/40', 'fulfilled' => 'text-gold border-gold', 'cancelled' => 'text-red-400 border-red-400/40']; @endphp
                        <span class="text-xs border rounded-full px-2.5 py-1 {{ $pc[$pledge->status] }}">{{ ucfirst($pledge->status) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-muted">{{ optional($pledge->due_date)->format('M j, Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('pledges.destroy', [$wedding, $pledge]) }}" onsubmit="return confirm('Remove this pledge?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-muted hover:text-red-400">×</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No pledges yet. Record your first above.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<p class="eyebrow mb-4">Contribution history</p>
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-line text-left text-xs text-muted">
                <th class="px-4 py-3">Contributor</th>
                <th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Method</th>
                <th class="px-4 py-3">Reference</th>
                <th class="px-4 py-3">Received</th>
                <th class="px-4 py-3">Pledge</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($contributions as $c)
                <tr class="border-b border-line/50 hover:bg-ink/40">
                    <td class="px-4 py-3">{{ $c->contributor_name }}</td>
                    <td class="px-4 py-3 text-gold">{{ number_format((float) $c->amount) }}</td>
                    <td class="px-4 py-3 text-xs">{{ strtoupper($c->method) }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $c->reference ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ optional($c->received_on)->format('M j, Y') ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $c->pledge?->pledger_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('contributions.destroy', [$wedding, $c]) }}" onsubmit="return confirm('Remove this contribution?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-muted hover:text-red-400">×</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-muted">No contributions recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
