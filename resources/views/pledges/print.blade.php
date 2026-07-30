<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pledges & contributions · {{ $wedding->coupleNames() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Georgia, serif; color: #222; padding: 2.5rem; }
        h1 { font-size: 1.5rem; }
        h2 { font-size: 1.1rem; margin: 1.8rem 0 .6rem; }
        .sub { color: #777; font-size: .85rem; margin: .4rem 0 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .5rem .6rem; text-align: left; }
        th { font-size: .7rem; text-transform: uppercase; letter-spacing: .1em; color: #999; }
        .toolbar { margin-bottom: 1.5rem; }
        .num { text-align: right; }
        .stamp { margin-top: 2rem; font-size: .7rem; color: #999; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()" style="padding: .6rem 1.6rem; cursor: pointer;">Print</button></div>
    <h1>{{ $wedding->coupleNames() }} — Pledges & Contributions ({{ $wedding->currency }})</h1>
    <p class="sub">Pledged: {{ number_format($pledges->sum('amount')) }} · Collected: {{ number_format($contributions->sum('amount')) }} · Outstanding: {{ number_format($pledges->sum(fn ($p) => max(0, $p->balance()))) }}</p>

    <h2>Pledges</h2>
    <table>
        <thead><tr><th>#</th><th>Pledger</th><th>Contact</th><th class="num">Pledged</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th>Due</th></tr></thead>
        <tbody>
            @foreach($pledges as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->pledger_name }}</td>
                <td>{{ $p->pledger_phone ?? $p->pledger_email }}</td>
                <td class="num">{{ number_format((float) $p->amount) }}</td>
                <td class="num">{{ number_format($p->totalContributed()) }}</td>
                <td class="num">{{ number_format($p->balance()) }}</td>
                <td>{{ ucfirst($p->status) }}</td>
                <td>{{ optional($p->due_date)->format('M j') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Contributions received</h2>
    <table>
        <thead><tr><th>#</th><th>Contributor</th><th class="num">Amount</th><th>Method</th><th>Reference</th><th>Date</th></tr></thead>
        <tbody>
            @foreach($contributions as $i => $c)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $c->contributor_name }}</td>
                <td class="num">{{ number_format((float) $c->amount) }}</td>
                <td>{{ strtoupper($c->method) }}</td>
                <td>{{ $c->reference }}</td>
                <td>{{ optional($c->received_on)->format('M j, Y') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="stamp">Generated {{ now()->format('F j, Y H:i') }} · Aroos · Powered by LetsVenture</p>
</body>
</html>
