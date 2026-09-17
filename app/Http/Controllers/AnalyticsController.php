<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use App\Models\WeddingSubscription;
use App\Models\Package;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Admin analytics dashboard
     */
    public function index()
    {
        // Wedding statistics
        $totalWeddings = Wedding::count();
        $activeWeddings = Wedding::where('wedding_date', '>=', now()->toDateString())->count();
        $completedWeddings = Wedding::where('wedding_date', '<', now()->toDateString())->count();

        // Subscription stats
        $subscriptions = WeddingSubscription::with('package', 'wedding')
            ->where('status', 'active')
            ->latest()
            ->get();

        // A subscription counts as active when it never expires, or has
        // not expired yet. This filters the collection in memory, so it
        // must use a closure: orWhere() only exists on a query builder.
        $activeSubscriptions = $subscriptions
            ->filter(fn ($s) => $s->expires_at === null || $s->expires_at > now())
            ->count();

        $packageStats = Package::with('subscriptions')
            ->get()
            ->map(function ($pkg) {
                $active = $pkg->subscriptions->where('status', 'active');

                return [
                    'name' => $pkg->name,
                    'price' => $pkg->price,
                    'subscriptions' => $active->count(),
                    // Revenue is the package price for each active
                    // subscription. The previous version summed
                    // created_at, which added timestamps together.
                    'revenue' => $active->count() * (float) $pkg->price,
                ];
            });

        // Revenue
        $totalRevenue = WeddingSubscription::where('status', 'active')
            ->with('package')
            ->get()
            ->sum(fn ($s) => (float) ($s->package->price ?? 0));

        // Most recent weddings
        $recentWeddings = Wedding::with('user', 'websiteSetting')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.analytics.index', compact(
            'totalWeddings',
            'activeWeddings',
            'completedWeddings',
            'activeSubscriptions',
            'packageStats',
            'totalRevenue',
            'recentWeddings',
            'subscriptions'
        ));
    }

    /**
     * Wedding deletion with cascade
     */
    public function deleteWedding(Wedding $wedding)
    {
        $coupleNames = $wedding->coupleNames();
        
        // Delete all related data
        $wedding->guests()->delete();
        $wedding->pledges()->delete();
        $wedding->contributions()->delete();
        $wedding->reminders()->delete();
        $wedding->photos()->delete();
        $wedding->albums()->delete();
        $wedding->budgetCategories()->delete();
        $wedding->seatingTables()->delete();
        $wedding->floorPlanElements()->delete();
        $wedding->vendors()->delete();
        $wedding->crewTeams()->delete();
        $wedding->checklistPhases()->delete();
        $wedding->timelineEvents()->delete();
        $wedding->menus()->delete();
        $wedding->memories()->delete();
        $wedding->inspirations()->delete();
        $wedding->collaborators()->delete();
        $wedding->websiteSetting()->delete();
        $wedding->storagePlan()->delete();
        $wedding->subscriptions()->delete();

        $wedding->delete();

        return back()->with('success', "Wedding for {$coupleNames} and all associated data have been deleted.");
    }

    /**
     * Detailed wedding analytics
     */
    public function wedding(Wedding $wedding)
    {
        $wedding->load([
            'guests', 'pledges.contributions', 'budgetCategories',
            'seatingTables.guests', 'vendors', 'crewTeams.members',
            'subscriptions.package', 'photos', 'albums', 'reminders'
        ]);

        $stats = [
            'guests' => [
                'total' => $wedding->guests->count(),
                'attending' => $wedding->guests->where('rsvp_status', 'attending')->count(),
                'checked_in' => $wedding->guests->whereNotNull('checked_in_at')->count(),
            ],
            'pledges' => [
                'total' => $wedding->pledges->count(),
                'amount' => $wedding->pledges->sum('amount'),
                'collected' => $wedding->pledges->sum(fn($p) => $p->contributions->sum('amount')),
            ],
            'budget' => [
                'total' => $wedding->total_budget,
                'spent' => $wedding->budgetCategories->sum('spent'),
            ],
            'storage' => [
                'used' => $wedding->storagePlan?->used_bytes ?? 0,
                'total' => $wedding->storagePlan?->total_bytes ?? 0,
            ],
        ];

        return view('admin.analytics.wedding', compact('wedding', 'stats'));
    }
}
