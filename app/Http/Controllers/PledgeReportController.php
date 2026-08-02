<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use Illuminate\Http\Request;

class PledgeReportController extends Controller
{
    /**
     * Print comprehensive pledge report with all statuses
     */
    public function print(Wedding $wedding)
    {
        $wedding->load(['guests.pledges.contributions']);

        // Group guests by contribution status
        $pledged = []; // Only pledged, not yet contributed
        $partial = []; // Partial contributions
        $fulfilled = []; // Fully contributed
        $special = []; // Special guests (no pledge)
        $none = []; // No pledge at all

        foreach ($wedding->guests as $guest) {
            if ($guest->is_special_guest) {
                $special[] = $guest;
            } else {
                $status = $guest->getContributionStatus();
                match($status) {
                    'pledged' => $pledged[] = $guest,
                    'partial' => $partial[] = $guest,
                    'fulfilled' => $fulfilled[] = $guest,
                    'none' => $none[] = $guest,
                    default => $none[] = $guest,
                };
            }
        }

        // Calculate totals
        $stats = [
            'pledged' => [
                'count' => count($pledged),
                'total_amount' => collect($pledged)
                    ->flatMap->pledges
                    ->sum('amount'),
                'collected' => 0,
            ],
            'partial' => [
                'count' => count($partial),
                'total_amount' => collect($partial)
                    ->flatMap->pledges
                    ->sum('amount'),
                'collected' => collect($partial)
                    ->flatMap(fn($g) => $g->pledges)
                    ->flatMap->contributions
                    ->sum('amount'),
            ],
            'fulfilled' => [
                'count' => count($fulfilled),
                'total_amount' => collect($fulfilled)
                    ->flatMap->pledges
                    ->sum('amount'),
                'collected' => collect($fulfilled)
                    ->flatMap(fn($g) => $g->pledges)
                    ->flatMap->contributions
                    ->sum('amount'),
            ],
            'special' => [
                'count' => count($special),
            ],
        ];

        return view('pledges.print-report', compact(
            'wedding',
            'pledged',
            'partial',
            'fulfilled',
            'special',
            'none',
            'stats'
        ));
    }
}
