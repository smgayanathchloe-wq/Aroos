<?php

namespace App\Http\Controllers;

use App\Models\ChecklistPhase;
use App\Models\ChecklistTask;
use App\Models\Wedding;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function index(Wedding $wedding)
    {
        $phases = $wedding->checklistPhases()->with('tasks')->get();

        return view('checklist.index', compact('wedding', 'phases'));
    }

    public function storePhase(Request $request, Wedding $wedding)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $data['sort_order'] = ($wedding->checklistPhases()->max('sort_order') ?? 0) + 1;
        $wedding->checklistPhases()->create($data);

        return back()->with('success', 'Phase added.');
    }

    public function updatePhase(Request $request, Wedding $wedding, ChecklistPhase $phase)
    {
        abort_unless($phase->wedding_id === $wedding->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $phase->update($data);

        return back()->with('success', 'Phase renamed.');
    }

    public function destroyPhase(Wedding $wedding, ChecklistPhase $phase)
    {
        abort_unless($phase->wedding_id === $wedding->id, 404);
        $phase->delete();

        return back()->with('success', 'Phase deleted.');
    }

    public function storeTask(Request $request, Wedding $wedding, ChecklistPhase $phase)
    {
        abort_unless($phase->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);
        $data['sort_order'] = ($phase->tasks()->max('sort_order') ?? 0) + 1;
        $phase->tasks()->create($data);

        return back()->with('success', 'Task added.');
    }

    public function toggleTask(Wedding $wedding, ChecklistTask $task)
    {
        abort_unless($task->phase->wedding_id === $wedding->id, 404);
        $task->update(['completed' => ! $task->completed]);

        return back();
    }

    public function destroyTask(Wedding $wedding, ChecklistTask $task)
    {
        abort_unless($task->phase->wedding_id === $wedding->id, 404);
        $task->delete();

        return back()->with('success', 'Task deleted.');
    }
}
