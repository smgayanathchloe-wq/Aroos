<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Wedding;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function index(Wedding $wedding)
    {
        $guests = $wedding->guests()->with('seatingTable')->orderBy('first_name')->get();

        $stats = [
            'total_parties' => $guests->count(),
            'total_people' => $guests->sum('party_count'),
            'attending' => $guests->where('rsvp_status', 'attending')->sum('party_count'),
            'checked_in_parties' => $guests->whereNotNull('checked_in_at')->count(),
            'checked_in_people' => $guests->sum('checked_in_count'),
        ];

        return view('checkin.index', compact('wedding', 'guests', 'stats'));
    }

    public function card(Wedding $wedding, Guest $guest)
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);
        $guest->load('seatingTable');

        return view('checkin.card', compact('wedding', 'guest'));
    }

    public function cardsAll(Wedding $wedding)
    {
        $guests = $wedding->guests()->with('seatingTable')->orderBy('first_name')->get();

        return view('checkin.cards-all', compact('wedding', 'guests'));
    }

    // Public scan endpoint (ushers open this by scanning the QR).
    public function scan(string $slug, string $token)
    {
        $wedding = Wedding::where('slug', $slug)->firstOrFail();
        $guest = Guest::where('wedding_id', $wedding->id)->where('checkin_token', $token)->with('seatingTable')->firstOrFail();

        $alreadyIn = $guest->isCheckedIn();

        return view('checkin.scan', compact('wedding', 'guest', 'alreadyIn'));
    }

    // Confirm arrival (public POST from the scan page).
    public function confirm(Request $request, string $slug, string $token)
    {
        $wedding = Wedding::where('slug', $slug)->firstOrFail();
        $guest = Guest::where('wedding_id', $wedding->id)->where('checkin_token', $token)->firstOrFail();

        $count = (int) $request->input('count', $guest->party_count);
        $count = max(1, min($count, $guest->party_count));

        if (! $guest->isCheckedIn()) {
            $guest->update([
                'checked_in_at' => now(),
                'checked_in_count' => $count,
            ]);
        }

        return redirect()->route('public.checkin.scan', [$slug, $token]);
    }

    public function undo(Wedding $wedding, Guest $guest)
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);
        $guest->update(['checked_in_at' => null, 'checked_in_count' => 0]);

        return back()->with('success', 'Check-in reset for '.$guest->fullName().'.');
    }

    public function manual(Wedding $wedding, Guest $guest)
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);
        $guest->update(['checked_in_at' => now(), 'checked_in_count' => $guest->party_count]);

        return back()->with('success', $guest->fullName().' checked in.');
    }

    public function printList(Request $request, Wedding $wedding)
    {
        $filter = $request->get('filter', 'all'); // all, attended, not_attended
        $query = $wedding->guests()->with('seatingTable')->orderBy('first_name');

        if ($filter === 'attended') {
            $query->whereNotNull('checked_in_at');
        } elseif ($filter === 'not_attended') {
            $query->whereNull('checked_in_at');
        }

        $guests = $query->get();

        return view('checkin.print-list', compact('wedding', 'guests', 'filter'));
    }
}
