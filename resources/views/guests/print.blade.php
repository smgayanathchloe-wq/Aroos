<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Guest list · {{ $wedding->coupleNames() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Georgia, serif; color: #222; padding: 2.5rem; }
        h1 { font-size: 1.5rem; }
        .sub { color: #777; font-size: .85rem; margin: .4rem 0 1.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .5rem .6rem; text-align: left; }
        th { font-size: .7rem; text-transform: uppercase; letter-spacing: .1em; color: #999; }
        .toolbar { margin-bottom: 1.5rem; }
        .stamp { margin-top: 2rem; font-size: .7rem; color: #999; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()" style="padding: .6rem 1.6rem; cursor: pointer;">Print</button></div>
    <h1>{{ $wedding->coupleNames() }} — Guest list</h1>
    <p class="sub">{{ optional($wedding->wedding_date)->format('F j, Y') }} · {{ $guests->count() }} parties · {{ $guests->sum('party_count') }} people</p>
    <table>
        <thead><tr><th>#</th><th>Name</th><th>Contact</th><th>Party</th><th>Side</th><th>Group</th><th>RSVP</th><th>Table</th></tr></thead>
        <tbody>
            @foreach($guests as $i => $g)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $g->fullName() }}</td>
                <td>{{ $g->phone ?? $g->email }}</td>
                <td>{{ $g->party_count }}</td>
                <td>{{ str_replace(['partner_a', 'partner_b', 'both'], [$wedding->partner_a, $wedding->partner_b, 'Both'], $g->side) }}</td>
                <td>{{ $g->group_label }}</td>
                <td>{{ ucfirst($g->rsvp_status) }}</td>
                <td>{{ $g->seatingTable?->name ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="stamp">Generated {{ now()->format('F j, Y H:i') }} · Aroos · Powered by LetsVenture</p>
</body>
</html>
