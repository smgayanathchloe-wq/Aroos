<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Guest list ({{ $filter }}) · {{ $wedding->coupleNames() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Georgia, serif; color: #222; padding: 2.5rem; }
        h1 { font-size: 1.5rem; }
        .sub { color: #777; font-size: .85rem; margin: .4rem 0 1.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        th, td { border-bottom: 1px solid #ddd; padding: .55rem .6rem; text-align: left; }
        th { font-size: .7rem; text-transform: uppercase; letter-spacing: .1em; color: #999; }
        .toolbar { margin-bottom: 1.5rem; }
        .stamp { margin-top: 2rem; font-size: .7rem; color: #999; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()" style="padding: .6rem 1.6rem; cursor: pointer;">Print</button></div>
    <h1>{{ $wedding->coupleNames() }} — Guest list ({{ str_replace('_', ' ', $filter) }})</h1>
    <p class="sub">{{ optional($wedding->wedding_date)->format('F j, Y') }} · {{ $wedding->venue_name }} · {{ $guests->count() }} parties · {{ $guests->sum('party_count') }} people @if($filter === 'attended') · {{ $guests->sum('checked_in_count') }} arrived @endif</p>
    <table>
        <thead>
            <tr><th>#</th><th>Name</th><th>Party</th><th>Table</th><th>RSVP</th><th>Arrival</th><th>Time</th></tr>
        </thead>
        <tbody>
            @foreach($guests as $i => $guest)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $guest->fullName() }}</td>
                <td>{{ $guest->party_count }}</td>
                <td>{{ $guest->seatingTable?->name ?? '—' }}</td>
                <td>{{ ucfirst($guest->rsvp_status) }}</td>
                <td>{{ $guest->isCheckedIn() ? 'Arrived ('.$guest->checked_in_count.')' : '—' }}</td>
                <td>{{ $guest->checked_in_at?->format('H:i') ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <p class="stamp">Generated {{ now()->format('F j, Y H:i') }} · Aroos · Powered by LetsVenture</p>
</body>
</html>
