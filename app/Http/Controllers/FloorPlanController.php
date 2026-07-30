<?php

namespace App\Http\Controllers;

use App\Models\FloorPlanElement;
use App\Models\Wedding;
use Illuminate\Http\Request;

class FloorPlanController extends Controller
{
    public function index(Wedding $wedding)
    {
        $elements = $wedding->floorPlanElements()->with('seatingTable.guests')->get();
        $tables = $wedding->seatingTables()->with('guests')->get();

        // Auto-create elements for seating tables that don't have one yet.
        $existing = $elements->pluck('seating_table_id')->filter()->all();
        $col = 0;
        $row = 0;
        foreach ($tables as $table) {
            if (! in_array($table->id, $existing)) {
                $wedding->floorPlanElements()->create([
                    'seating_table_id' => $table->id,
                    'type' => $table->shape === 'round' ? 'table_round' : 'table_rect',
                    'label' => $table->name,
                    'x' => 120 + ($col * 160),
                    'y' => 140 + ($row * 160),
                    'width' => 90,
                    'height' => 90,
                ]);
                $col++;
                if ($col > 3) {
                    $col = 0;
                    $row++;
                }
            }
        }

        $elements = $wedding->floorPlanElements()->with('seatingTable.guests')->get();

        return view('floorplan.index', compact('wedding', 'elements', 'tables'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $defaults = [
            'dance_floor' => [200, 140],
            'stage' => [240, 80],
            'dj' => [80, 60],
            'bar' => [140, 60],
            'entrance' => [120, 40],
            'buffet' => [200, 50],
            'cake' => [70, 70],
            'photo_booth' => [90, 90],
            'head_table' => [220, 70],
        ];
        [$w, $h] = $defaults[$data['type']] ?? [100, 80];

        $wedding->floorPlanElements()->create([
            ...$data,
            'x' => 300,
            'y' => 300,
            'width' => $w,
            'height' => $h,
        ]);

        return back()->with('success', 'Element added.');
    }

    public function bulkUpdate(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'elements' => ['required', 'array'],
            'elements.*.id' => ['required', 'integer'],
            'elements.*.x' => ['required', 'numeric'],
            'elements.*.y' => ['required', 'numeric'],
            'elements.*.width' => ['required', 'numeric', 'min:20'],
            'elements.*.height' => ['required', 'numeric', 'min:20'],
            'elements.*.rotation' => ['nullable', 'numeric'],
        ]);

        foreach ($data['elements'] as $el) {
            FloorPlanElement::where('id', $el['id'])->where('wedding_id', $wedding->id)->update([
                'x' => $el['x'],
                'y' => $el['y'],
                'width' => $el['width'],
                'height' => $el['height'],
                'rotation' => $el['rotation'] ?? 0,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Wedding $wedding, FloorPlanElement $element)
    {
        abort_unless($element->wedding_id === $wedding->id, 404);

        if ($element->seating_table_id) {
            return back()->withErrors(['floorplan' => 'This table is managed from the Seating page. Delete it there.']);
        }

        $element->delete();

        return back()->with('success', 'Element removed.');
    }
}
