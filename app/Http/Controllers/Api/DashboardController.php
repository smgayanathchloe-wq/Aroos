<?php

namespace App\Http\Controllers\Api;

use App\Models\BudgetCategory;
use App\Models\ChecklistTask;
use App\Models\Contribution;
use App\Models\Guest;
use App\Models\Pledge;
use App\Models\TimelineEvent;
use App\Models\Vendor;
use App\Models\Wedding;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The couple's home screen: how long to go, who is coming, what it costs
 * and what still needs doing.
 */
class DashboardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $wedding = $this->wedding($request);
        $user = $this->user($request);

        return $this->ok([
            'wedding' => $this->weddingPayload($wedding, $user),
            'countdown' => $this->countdown($wedding),
            'guests' => $this->guestStats($wedding),
            'budget' => $this->budgetStats($wedding),
            'pledges' => $this->pledgeStats($wedding),
            'checklist' => $this->checklistStats($wedding),
            'vendors' => $this->vendorStats($wedding),
            'next_events' => $this->nextEvents($wedding),
            'rsvp_breakdown' => $this->rsvpBreakdown($wedding),
            'contribution_trend' => $this->contributionTrend($wedding),
        ]);
    }

    protected function weddingPayload(Wedding $wedding, $user): array
    {
        return [
            'id' => $wedding->id,
            'slug' => $wedding->slug,
            'couple' => trim($wedding->partner_a.' & '.$wedding->partner_b),
            'partner_a' => $wedding->partner_a,
            'partner_b' => $wedding->partner_b,
            'wedding_date' => optional($wedding->wedding_date)->toDateString(),
            'wedding_time' => $wedding->wedding_time,
            'venue_name' => $wedding->venue_name,
            'venue_city' => $wedding->venue_city,
            'venue_country' => $wedding->venue_country,
            'currency' => $wedding->currency,
            'total_budget' => (float) $wedding->total_budget,
            'cover_photo' => $wedding->cover_photo ? url('storage/'.$wedding->cover_photo) : null,
            'story' => $wedding->story,
            'can_edit' => $user->canEditWedding($wedding),
        ];
    }

    /**
     * Days remaining, or how long ago the wedding was.
     */
    protected function countdown(Wedding $wedding): array
    {
        if (! $wedding->wedding_date) {
            return ['has_date' => false, 'days_remaining' => null, 'label' => 'Date to be confirmed'];
        }

        $date = Carbon::parse($wedding->wedding_date)->startOfDay();
        $today = Carbon::now()->startOfDay();
        $days = (int) $today->diffInDays($date, false);

        return [
            'has_date' => true,
            'date' => $date->toDateString(),
            'days_remaining' => $days,
            'is_past' => $days < 0,
            'is_today' => $days === 0,
            'weeks_remaining' => $days > 0 ? (int) floor($days / 7) : 0,
            'label' => match (true) {
                $days === 0 => 'Today is the day',
                $days === 1 => 'Tomorrow',
                $days > 0 => "{$days} days to go",
                $days === -1 => 'Yesterday',
                default => abs($days).' days ago',
            },
        ];
    }

    protected function guestStats(Wedding $wedding): array
    {
        $rows = Guest::where('wedding_id', $wedding->id)->get();

        return [
            'total' => $rows->count(),
            // party_count covers plus-ones, so headcount is not just rows.
            'expected_headcount' => (int) $rows->sum(fn ($g) => max(1, (int) $g->party_count)),
            'attending' => $rows->where('rsvp_status', 'attending')->count(),
            'declined' => $rows->where('rsvp_status', 'declined')->count(),
            'pending' => $rows->whereNotIn('rsvp_status', ['attending', 'declined'])->count(),
            'checked_in' => $rows->whereNotNull('checked_in_at')->count(),
            'seated' => $rows->whereNotNull('seating_table_id')->count(),
            'special' => $rows->where('is_special_guest', 1)->count(),
        ];
    }

    protected function budgetStats(Wedding $wedding): array
    {
        $categories = BudgetCategory::where('wedding_id', $wedding->id)->get();

        $budgeted = (float) $categories->sum('budgeted');
        $spent = (float) $categories->sum('spent');
        $total = (float) $wedding->total_budget;

        return [
            'total_budget' => $total,
            'allocated' => round($budgeted, 2),
            'spent' => round($spent, 2),
            'remaining' => round(($total > 0 ? $total : $budgeted) - $spent, 2),
            'percent_spent' => $budgeted > 0 ? round($spent / $budgeted * 100, 1) : 0,
            'over_budget' => $spent > ($total > 0 ? $total : $budgeted),
            'categories' => $categories->count(),
            'top_categories' => $categories->sortByDesc('spent')->take(5)->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'icon' => $c->icon,
                'budgeted' => (float) $c->budgeted,
                'spent' => (float) $c->spent,
                'percent' => $c->budgeted > 0 ? round($c->spent / $c->budgeted * 100, 1) : 0,
            ])->values(),
        ];
    }

    /**
     * Pledges are promises; contributions are money actually received.
     */
    protected function pledgeStats(Wedding $wedding): array
    {
        $pledges = Pledge::where('wedding_id', $wedding->id)->get();
        $received = (float) Contribution::where('wedding_id', $wedding->id)->sum('amount');
        $promised = (float) $pledges->sum('amount');

        return [
            'total_pledged' => round($promised, 2),
            'total_received' => round($received, 2),
            'outstanding' => round(max(0, $promised - $received), 2),
            'fulfilment_rate' => $promised > 0 ? round($received / $promised * 100, 1) : 0,
            'count' => $pledges->count(),
            'paid' => $pledges->where('status', 'paid')->count(),
            'partial' => $pledges->where('status', 'partial')->count(),
            'pending' => $pledges->whereNotIn('status', ['paid', 'partial'])->count(),
        ];
    }

    protected function checklistStats(Wedding $wedding): array
    {
        $tasks = ChecklistTask::whereIn(
            'checklist_phase_id',
            DB::table('checklist_phases')->where('wedding_id', $wedding->id)->pluck('id'),
        )->get();

        $done = $tasks->where('completed', 1)->count();

        return [
            'total' => $tasks->count(),
            'completed' => $done,
            'remaining' => $tasks->count() - $done,
            'percent_complete' => $tasks->count() > 0 ? round($done / $tasks->count() * 100, 1) : 0,
            // Anything still open whose due date has passed.
            'overdue' => $tasks->filter(fn ($t) => ! $t->completed
                && $t->due_date
                && Carbon::parse($t->due_date)->isPast())->count(),
        ];
    }

    protected function vendorStats(Wedding $wedding): array
    {
        $vendors = Vendor::where('wedding_id', $wedding->id)->get();

        return [
            'total' => $vendors->count(),
            'booked' => $vendors->where('status', 'booked')->count(),
        ];
    }

    protected function nextEvents(Wedding $wedding): array
    {
        return TimelineEvent::where('wedding_id', $wedding->id)
            ->orderBy('start_time')
            ->limit(5)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'start_time' => $e->start_time,
                'end_time' => $e->end_time,
                'location' => $e->location ?? null,
            ])->values()->all();
    }

    protected function rsvpBreakdown(Wedding $wedding): array
    {
        return Guest::where('wedding_id', $wedding->id)
            ->selectRaw('COALESCE(rsvp_status, ?) as status, COUNT(*) as total', ['pending'])
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => ['label' => ucfirst($r->status), 'total' => (int) $r->total])
            ->values()->all();
    }

    /**
     * Six months of received contributions, for the dashboard chart.
     */
    protected function contributionTrend(Wedding $wedding): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        $rows = Contribution::where('wedding_id', $wedding->id)
            ->whereDate('received_on', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(received_on, '%Y-%m') as ym, SUM(amount) as total")
            ->groupByRaw("DATE_FORMAT(received_on, '%Y-%m')")
            ->get();

        $series = [];

        for ($i = 0; $i < 6; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $series[] = [
                'month' => $key,
                'label' => $month->format('M'),
                'short_label' => $month->format('M'),
                'total' => (float) ($rows->firstWhere('ym', $key)->total ?? 0),
            ];
        }

        return $series;
    }
}
