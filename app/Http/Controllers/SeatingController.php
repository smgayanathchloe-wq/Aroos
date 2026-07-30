<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\SeatingTable;
use App\Models\Wedding;
use Illuminate\Http\Request;

class SeatingController extends Controller
{
    public function index(Wedding $wedding)
    {
        $tables = $wedding->seatingTables()->with('guests')->get();
        $unseated = $wedding->guests()->whereNull('seating_table_id')->orderBy('first_name')->get();

        return view('seating.index', compact('wedding', 'tables', 'unseated'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'shape' => ['required', 'in:round,rectangular,square'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);
        unset($data['quantity']);

        if ($quantity === 1) {
            $wedding->seatingTables()->create($data);
        } else {
            for ($i = 1; $i <= $quantity; $i++) {
                $wedding->seatingTables()->create([
                    ...$data,
                    'name' => $data['name'].' '.$i,
                ]);
            }
        }

        return back()->with('success', 'Table(s) added.');
    }

    public function update(Request $request, Wedding $wedding, SeatingTable $table)
    {
        abort_unless($table->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'shape' => ['required', 'in:round,rectangular,square'],
        ]);
        $table->update($data);

        return back()->with('success', 'Table updated.');
    }

    public function destroy(Wedding $wedding, SeatingTable $table)
    {
        abort_unless($table->wedding_id === $wedding->id, 404);
        $table->delete();

        return back()->with('success', 'Table deleted.');
    }

    public function assign(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'guest_id' => ['required', 'integer'],
            'seating_table_id' => ['nullable', 'integer'],
        ]);

        $guest = Guest::findOrFail($data['guest_id']);
        abort_unless($guest->wedding_id === $wedding->id, 404);

        if ($data['seating_table_id']) {
            $table = SeatingTable::findOrFail($data['seating_table_id']);
            abort_unless($table->wedding_id === $wedding->id, 404);

            if ($table->seatsUsed() + $guest->party_count > $table->capacity) {
                return back()->withErrors(['seating' => 'Not enough seats at '.$table->name.'.']);
            }
        }

        $guest->update(['seating_table_id' => $data['seating_table_id'] ?: null]);

        return back()->with('success', 'Seating updated.');
    }
}
