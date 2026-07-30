<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Seating chart · {{ $wedding->coupleNames() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Georgia, serif; color: #222; padding: 2.5rem; }
        h1 { font-size: 1.5rem; }
        .sub { color: #777; font-size: .85rem; margin: .4rem 0 1.5rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.2rem; }
        .table-card { border: 1px solid #ccc; border-radius: 10px; padding: 1rem; page-break-inside: avoid; }
        .table-card h2 { font-size: 1rem; border-bottom: 1px solid #eee; padding-bottom: .4rem; margin-bottom: .5rem; }
        .table-card ul { list-style: none; font-size: .85rem; }
        .table-card li { padding: .2rem 0; }
        .cap { color: #999; font-size: .7rem; font-weight: normal; }
        .toolbar { margin-bottom: 1.5rem; }
        .stamp { margin-top: 2rem; font-size: .7rem; color: #999; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()" style="padding: .6rem 1.6rem; cursor: pointer;">Print</button></div>
    <h1>{{ $wedding->coupleNames() }} — Seating chart</h1>
    <p class="sub">{{ optional($wedding->wedding_date)->format('F j, Y') }} · {{ $wedding->venue_name }} · {{ $tables->count() }} tables</p>
    <div class="grid">
        @foreach($tables as $table)
        <div class="table-card">
            <h2>{{ $table->name }} <span class="cap">{{ $table->seatsUsed() }}/{{ $table->capacity }} · {{ $table->shape }}</span></h2>
            <ul>
                @forelse($table->guests as $g)
                    <li>{{ $g->fullName() }}{{ $g->party_count > 1 ? ' ×'.$g->party_count : '' }}</li>
                @empty
                    <li style="color: #bbb;">Empty</li>
                @endforelse
            </ul>
        </div>
        @endforeach
    </div>
    @if($unseated->count())
        <h2 style="font-size: 1rem; margin-top: 1.5rem;">Unseated ({{ $unseated->count() }})</h2>
        <p style="font-size: .85rem; color: #555;">{{ $unseated->map(fn ($g) => $g->fullName())->join(', ') }}</p>
    @endif
    <p class="stamp">Generated {{ now()->format('F j, Y H:i') }} · Aroos · Powered by LetsVenture</p>
</body>
</html>
