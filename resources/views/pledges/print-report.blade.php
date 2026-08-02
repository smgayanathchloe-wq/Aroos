@extends('layouts.base')

@section('title', 'Pledge & Contribution Report')

@section('body')
<div style="background: #FAF7F2; min-height: 100vh; padding: 2rem;">
    <div style="max-width: 900px; margin: 0 auto;">
        {{-- Header --}}
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-family: 'Playfair Display', serif; font-size: 2rem; color: #2D2820; margin: 0;">{{ $wedding->coupleNames() }}</h1>
            <p style="color: #8A7E6F; font-size: 0.9rem; margin: 0.5rem 0;">Pledge & Contribution Report</p>
            <p style="color: #8A7E6F; font-size: 0.85rem;">Generated {{ now()->format('F d, Y') }}</p>
        </div>

        {{-- Summary Cards --}}
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: #fff; border: 1px solid #E8E2D9; padding: 1.5rem; border-radius: 0.5rem; text-align: center;">
                <p style="color: #8A7E6F; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.1em;">Pledged</p>
                <p style="font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #2D2820; margin: 0.5rem 0;">{{ $stats['pledged']['count'] }}</p>
                <p style="color: #B08D57; font-size: 0.85rem; margin: 0;">{{ number_format($stats['pledged']['total_amount']) }}</p>
            </div>
            <div style="background: #fff; border: 1px solid #E8E2D9; padding: 1.5rem; border-radius: 0.5rem; text-align: center;">
                <p style="color: #8A7E6F; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.1em;">Partial</p>
                <p style="font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #2D2820; margin: 0.5rem 0;">{{ $stats['partial']['count'] }}</p>
                <p style="color: #B08D57; font-size: 0.85rem; margin: 0;">{{ number_format($stats['partial']['collected']) }}</p>
            </div>
            <div style="background: #fff; border: 1px solid #E8E2D9; padding: 1.5rem; border-radius: 0.5rem; text-align: center;">
                <p style="color: #8A7E6F; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.1em;">Fulfilled</p>
                <p style="font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #2D2820; margin: 0.5rem 0;">{{ $stats['fulfilled']['count'] }}</p>
                <p style="color: #B08D57; font-size: 0.85rem; margin: 0;">{{ number_format($stats['fulfilled']['collected']) }}</p>
            </div>
            <div style="background: #fff; border: 1px solid #E8E2D9; padding: 1.5rem; border-radius: 0.5rem; text-align: center;">
                <p style="color: #8A7E6F; font-size: 0.75rem; margin: 0; text-transform: uppercase; letter-spacing: 0.1em;">Special Guests</p>
                <p style="font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #2D2820; margin: 0.5rem 0;">{{ $stats['special']['count'] }}</p>
            </div>
        </div>

        {{-- Pledged Only Section --}}
        @if($pledged->count())
        <div style="background: #fff; border: 1px solid #E8E2D9; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 1.2rem; color: #2D2820; margin: 0 0 1rem;">Pledged but Not Yet Contributed</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #E8E2D9;">
                        <th style="text-align: left; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Name</th>
                        <th style="text-align: right; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Pledge Amount</th>
                        <th style="text-align: right; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Party Size</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pledged as $guest)
                    <tr style="border-bottom: 1px solid #F0E9DC;">
                        <td style="padding: 0.75rem; color: #2D2820;">{{ $guest->fullName() }}</td>
                        <td style="text-align: right; padding: 0.75rem; color: #2D2820;">{{ number_format($guest->pledges()->sum('amount')) }}</td>
                        <td style="text-align: right; padding: 0.75rem; color: #8A7E6F;">{{ $guest->party_count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Partial Section --}}
        @if($partial->count())
        <div style="background: #fff; border: 1px solid #E8E2D9; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 1.2rem; color: #2D2820; margin: 0 0 1rem;">Partial Contributions</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #E8E2D9;">
                        <th style="text-align: left; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Name</th>
                        <th style="text-align: right; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Pledged</th>
                        <th style="text-align: right; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Collected</th>
                        <th style="text-align: right; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($partial as $guest)
                        @php
                            $pledged = $guest->pledges()->sum('amount');
                            $collected = $guest->pledges()
                                ->with('contributions')
                                ->get()
                                ->sum(fn($p) => $p->contributions->sum('amount'));
                        @endphp
                    <tr style="border-bottom: 1px solid #F0E9DC;">
                        <td style="padding: 0.75rem; color: #2D2820;">{{ $guest->fullName() }}</td>
                        <td style="text-align: right; padding: 0.75rem; color: #2D2820;">{{ number_format($pledged) }}</td>
                        <td style="text-align: right; padding: 0.75rem; color: #B08D57; font-weight: 600;">{{ number_format($collected) }}</td>
                        <td style="text-align: right; padding: 0.75rem; color: #666;">{{ number_format($pledged - $collected) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Fulfilled Section --}}
        @if($fulfilled->count())
        <div style="background: #fff; border: 1px solid #E8E2D9; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 1.2rem; color: #2D2820; margin: 0 0 1rem;">✓ Pledges Fulfilled</h2>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #E8E2D9;">
                        <th style="text-align: left; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Name</th>
                        <th style="text-align: right; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Amount</th>
                        <th style="text-align: left; padding: 0.75rem; color: #8A7E6F; text-transform: uppercase; font-size: 0.75rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fulfilled as $guest)
                    <tr style="border-bottom: 1px solid #F0E9DC;">
                        <td style="padding: 0.75rem; color: #2D2820;">{{ $guest->fullName() }}</td>
                        <td style="text-align: right; padding: 0.75rem; color: #B08D57; font-weight: 600;">{{ number_format($guest->pledges()->sum('amount')) }}</td>
                        <td style="text-align: left; padding: 0.75rem; color: #5C9D4F;">✓ Complete</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Special Guests Section --}}
        @if($special->count())
        <div style="background: #fff; border: 1px solid #E8E2D9; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 1.2rem; color: #2D2820; margin: 0 0 1rem;">Special Guests</h2>
            <p style="color: #8A7E6F; font-size: 0.85rem; margin: 0 0 1rem;">Guests invited without pledge requirement</p>
            <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach($special as $guest)
                <li style="padding: 0.75rem 0; border-bottom: 1px solid #F0E9DC; color: #2D2820;">{{ $guest->fullName() }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Print Button --}}
        <div style="text-align: center; margin-top: 2rem; no-print-div">
            <button onclick="window.print()" style="background: #B08D57; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-size: 0.9rem;">🖨️ Print Report</button>
        </div>
    </div>

    <style>
        @media print {
            .no-print-div { display: none; }
            body { background: white; padding: 0; }
            div[style*="max-width"] { max-width: 100%; }
        }
    </style>
</div>
@endsection
