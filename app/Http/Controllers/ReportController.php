<?php

namespace App\Http\Controllers;

use App\Models\Wedding;

class ReportController extends Controller
{
    public function index(Wedding $wedding)
    {
        $wedding->load([
            'guests.seatingTable', 'budgetCategories', 'seatingTables.guests',
            'vendors', 'crewTeams.members', 'timelineEvents', 'menus.items',
            'pledges.contributions', 'contributions.pledge', 'checklistPhases.tasks',
            'reminders.logs',
        ]);

        $report = [
            'guests' => [
                'parties' => $wedding->guests->count(),
                'people' => $wedding->guests->sum('party_count'),
                'attending' => $wedding->guests->where('rsvp_status', 'attending')->sum('party_count'),
                'declined' => $wedding->guests->where('rsvp_status', 'declined')->count(),
                'pending' => $wedding->guests->where('rsvp_status', 'pending')->count(),
                'checked_in' => $wedding->guests->whereNotNull('checked_in_at')->count(),
                'checked_in_people' => $wedding->guests->sum('checked_in_count'),
            ],
            'budget' => [
                'total' => (float) $wedding->total_budget,
                'allocated' => $wedding->budgetCategories->sum('budgeted'),
                'spent' => $wedding->budgetCategories->sum('spent'),
            ],
            'pledges' => [
                'count' => $wedding->pledges->count(),
                'pledged' => $wedding->pledges->sum('amount'),
                'collected' => $wedding->contributions->sum('amount'),
                'fulfilled' => $wedding->pledges->where('status', 'fulfilled')->count(),
                'outstanding_amount' => $wedding->pledges->whereIn('status', ['pledged', 'partial'])->sum(fn ($p) => $p->balance()),
            ],
            'vendors' => [
                'count' => $wedding->vendors->count(),
                'booked' => $wedding->vendors->where('status', 'booked')->count(),
                'cost' => $wedding->vendors->where('status', 'booked')->sum('price_estimate'),
                'paid' => $wedding->vendors->sum('amount_paid'),
            ],
            'tasks' => [
                'total' => $wedding->checklistPhases->flatMap->tasks->count(),
                'done' => $wedding->checklistPhases->flatMap->tasks->where('completed', true)->count(),
            ],
            'messages' => [
                'sent' => $wedding->reminders->flatMap->logs->where('status', 'sent')->count(),
                'failed' => $wedding->reminders->flatMap->logs->where('status', 'failed')->count(),
            ],
        ];

        return view('report.index', compact('wedding', 'report'));
    }
}
