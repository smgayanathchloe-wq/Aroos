<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Wedding;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function index(Request $request, Wedding $wedding)
    {
        $query = $wedding->guests()->with('seatingTable');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('rsvp_status', $status);
        }

        $guests = $query->orderBy('first_name')->get();

        return view('guests.index', compact('wedding', 'guests'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $this->validated($request);
        $wedding->guests()->create($data);

        return back()->with('success', 'Guest added.');
    }

    public function update(Request $request, Wedding $wedding, Guest $guest)
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);
        $data = $this->validated($request);
        if (($data['rsvp_status'] ?? null) !== $guest->rsvp_status) {
            $data['rsvp_at'] = now();
        }
        $guest->update($data);

        return back()->with('success', 'Guest updated.');
    }

    public function destroy(Wedding $wedding, Guest $guest)
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);
        $guest->delete();

        return back()->with('success', 'Guest removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'party_count' => ['required', 'integer', 'min:1', 'max:20'],
            'side' => ['required', 'in:partner_a,partner_b,both'],
            'group_label' => ['nullable', 'string', 'max:100'],
            'meal_preference' => ['nullable', 'string', 'max:100'],
            'dietary_notes' => ['nullable', 'string'],
            'rsvp_status' => ['required', 'in:pending,attending,declined,maybe'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
