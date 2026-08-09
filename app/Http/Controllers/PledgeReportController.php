<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use App\Support\PledgeReportEntry;

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
                match ($status) {
                    'pledged' => $pledged[] = $guest,
                    'partial' => $partial[] = $guest,
                    'fulfilled' => $fulfilled[] = $guest,
                    'none' => $none[] = $guest,
                    default => $none[] = $guest,
                };
            }
        }

        // Pledges are not always tied to a guest: imported ledgers and pledges
        // recorded by name only have no guest record. Grouping solely by guest
        // would leave those out of the report entirely, which for an imported
        // ledger means every single row is missing.
        $orphanPledges = $wedding->pledges()
            ->whereNull('guest_id')
            ->with('contributions')
            ->orderBy('pledger_name')
            ->get();

        foreach ($orphanPledges as $pledge) {
            // Present each pledge with the same interface the view expects of
            // a guest, so one template handles both sources.
            $entry = new PledgeReportEntry($pledge);

            match ($pledge->status) {
                'partial' => $partial[] = $entry,
                'fulfilled' => $fulfilled[] = $entry,
                default => $pledged[] = $entry,
            };
        }

        // Calculate totals.
        //
        // Entries are a mix of guests (whose pledges come via a relation) and
        // guest-less pledges wrapped in PledgeReportEntry. Both expose
        // pledges() and contributions(), so summing goes through those rather
        // than reaching into a relation that only one of them has.
        $sumPledged = fn (array $entries) => collect($entries)
            ->sum(fn ($entry) => $entry->pledges()->sum('amount'));

        $sumCollected = fn (array $entries) => collect($entries)
            ->sum(fn ($entry) => $entry instanceof PledgeReportEntry
                ? $entry->totalContributed()
                : $entry->pledges->flatMap->contributions->sum('amount'));

        $stats = [
            'pledged' => [
                'count' => count($pledged),
                'total_amount' => $sumPledged($pledged),
                'collected' => 0,
            ],
            'partial' => [
                'count' => count($partial),
                'total_amount' => $sumPledged($partial),
                'collected' => $sumCollected($partial),
            ],
            'fulfilled' => [
                'count' => count($fulfilled),
                'total_amount' => $sumPledged($fulfilled),
                'collected' => $sumCollected($fulfilled),
            ],
            'special' => [
                'count' => count($special),
            ],
        ];

        return view('pledges.print-report', [
            'wedding' => $wedding,
            // The view calls ->count() and other collection methods on these,
            // so they must be collections rather than plain arrays.
            'pledged' => collect($pledged),
            'partial' => collect($partial),
            'fulfilled' => collect($fulfilled),
            'special' => collect($special),
            'none' => collect($none),
            'stats' => $stats,
        ]);
    }
}
