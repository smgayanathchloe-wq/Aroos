<?php

namespace App\Http\Controllers;

use App\Models\Wedding;
use App\Services\ChecklistSeeder;
use Illuminate\Http\Request;

class WeddingController extends Controller
{
    public function index(Request $request)
    {
        $weddings = $request->user()->accessibleWeddings();

        return view('weddings.index', compact('weddings'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canCreateWeddings(), 403, 'Couple accounts cannot create weddings. Ask your planner.');

        $data = $request->validate([
            'partner_a' => ['required', 'string', 'max:100'],
            'partner_b' => ['required', 'string', 'max:100'],
            'wedding_date' => ['nullable', 'date'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:100'],
            'venue_country' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $wedding = $request->user()->weddings()->create($data);

        ChecklistSeeder::seed($wedding);

        $wedding->websiteSetting()->create([
            'theme' => 'atelier',
            'sections' => ['hero', 'story', 'details', 'timeline', 'rsvp'],
        ]);

        return redirect()->route('wedding.dashboard', $wedding)->with('success', 'Wedding created. Let\'s plan!');
    }

    public function dashboard(Wedding $wedding)
    {
        $wedding->load(['guests', 'budgetCategories', 'seatingTables.guests', 'checklistPhases.tasks', 'pledges.contributions', 'contributions']);

        $stats = [
            'days_to_go' => $wedding->daysToGo(),
            'guest_count' => $wedding->guests->sum('party_count'),
            'rsvp_attending' => $wedding->guests->where('rsvp_status', 'attending')->sum('party_count'),
            'rsvp_received' => $wedding->guests->whereNotIn('rsvp_status', ['pending'])->count(),
            'budgeted' => $wedding->budgetCategories->sum('budgeted'),
            'spent' => $wedding->budgetCategories->sum('spent'),
            'tasks_total' => $wedding->checklistPhases->flatMap->tasks->count(),
            'tasks_done' => $wedding->checklistPhases->flatMap->tasks->where('completed', true)->count(),
            'seats_total' => $wedding->seatingTables->sum('capacity'),
            'seats_used' => $wedding->seatingTables->sum(fn ($t) => $t->seatsUsed()),
            'pledged' => $wedding->pledges->sum('amount'),
            'contributed' => $wedding->contributions->sum('amount'),
        ];

        return view('weddings.dashboard', compact('wedding', 'stats'));
    }

    public function update(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'partner_a' => ['required', 'string', 'max:100'],
            'partner_b' => ['required', 'string', 'max:100'],
            'wedding_date' => ['nullable', 'date'],
            'wedding_time' => ['nullable'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_city' => ['nullable', 'string', 'max:100'],
            'venue_country' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'story' => ['nullable', 'string'],
        ]);

        $wedding->update($data);

        return back()->with('success', 'Wedding details updated.');
    }

    public function destroy(Request $request, Wedding $wedding)
    {
        if ($wedding->user_id !== $request->user()->id && ! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $wedding->delete();

        return redirect()->route('weddings.index')->with('success', 'Wedding deleted.');
    }
}
