<?php

namespace App\Http\Controllers;

use App\Models\TimelineEvent;
use App\Models\Wedding;
use Illuminate\Http\Request;

class TimelineController extends Controller
{
    public function index(Wedding $wedding)
    {
        $events = $wedding->timelineEvents;

        return view('timeline.index', compact('wedding', 'events'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $wedding->timelineEvents()->create($this->validated($request));

        return back()->with('success', 'Event added.');
    }

    public function update(Request $request, Wedding $wedding, TimelineEvent $event)
    {
        abort_unless($event->wedding_id === $wedding->id, 404);
        $event->update($this->validated($request));

        return back()->with('success', 'Event updated.');
    }

    public function destroy(Wedding $wedding, TimelineEvent $event)
    {
        abort_unless($event->wedding_id === $wedding->id, 404);
        $event->delete();

        return back()->with('success', 'Event removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'start_time' => ['required'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'location' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'public' => ['nullable', 'boolean'],
        ]);
    }
}
