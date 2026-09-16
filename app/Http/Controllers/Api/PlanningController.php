<?php

namespace App\Http\Controllers\Api;

use App\Models\ChecklistPhase;
use App\Models\ChecklistTask;
use App\Models\CrewMember;
use App\Models\CrewTeam;
use App\Models\Guest;
use App\Models\SeatingTable;
use App\Models\TimelineEvent;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Everything that has to be organised: the checklist, the suppliers, the
 * running order, the tables and the crew.
 */
class PlanningController extends ApiController
{
    // -------------------------------------------------------- checklist

    public function checklist(Request $request): JsonResponse
    {
        $phases = $this->scoped($request, ChecklistPhase::query())
            ->orderBy('sort_order')->get();

        $tasks = ChecklistTask::whereIn('checklist_phase_id', $phases->pluck('id'))
            ->orderBy('sort_order')->get()->groupBy('checklist_phase_id');

        $all = $tasks->flatten();
        $done = $all->where('completed', 1)->count();

        return $this->ok([
            'total' => $all->count(),
            'completed' => $done,
            'percent_complete' => $all->count() > 0 ? round($done / $all->count() * 100, 1) : 0,
            'phases' => $phases->map(function ($phase) use ($tasks) {
                $rows = $tasks->get($phase->id, collect());
                $complete = $rows->where('completed', 1)->count();

                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'sort_order' => (int) $phase->sort_order,
                    'total' => $rows->count(),
                    'completed' => $complete,
                    'percent_complete' => $rows->count() > 0 ? round($complete / $rows->count() * 100, 1) : 0,
                    'tasks' => $rows->map(fn ($t) => $this->task($t))->values(),
                ];
            })->values(),
        ]);
    }

    public function storeTask(Request $request, int $phaseId): JsonResponse
    {
        $this->authorizeEdit($request);

        // The phase must belong to this wedding, or a task could be attached
        // to another couple's checklist.
        $phase = $this->findScoped($request, ChecklistPhase::class, $phaseId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $data['checklist_phase_id'] = $phase->id;
        $data['completed'] = false;

        return $this->ok($this->task(ChecklistTask::create($data)));
    }

    public function updateTask(Request $request, int $taskId): JsonResponse
    {
        $this->authorizeEdit($request);

        $task = $this->taskInWedding($request, $taskId);

        $task->fill($request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'completed' => ['sometimes', 'boolean'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'integer'],
        ]))->save();

        return $this->ok($this->task($task->fresh()));
    }

    public function destroyTask(Request $request, int $taskId): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->taskInWedding($request, $taskId)->delete();

        return $this->ok(['deleted' => true]);
    }

    // ---------------------------------------------------------- vendors

    public function vendors(Request $request): JsonResponse
    {
        $query = Vendor::query();
        $this->scoped($request, $query);

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $vendors = $query->orderBy('name')->get();

        return $this->ok([
            'total' => $vendors->count(),
            'estimated_cost' => round((float) $vendors->sum('price_estimate'), 2),
            'paid' => round((float) $vendors->sum('amount_paid'), 2),
            'by_status' => $vendors->groupBy(fn ($v) => $v->status ?: 'enquiry')
                ->map(fn ($rows, $status) => ['label' => ucfirst((string) $status), 'total' => $rows->count()])
                ->values(),
            'vendors' => $vendors->map(fn ($v) => $this->vendor($v))->values(),
        ]);
    }

    public function storeVendor(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $this->vendorRules($request);
        $data['wedding_id'] = $wedding->id;

        return $this->ok($this->vendor(Vendor::create($data)));
    }

    public function updateVendor(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $vendor = $this->findScoped($request, Vendor::class, $id);
        $vendor->fill($this->vendorRules($request, true))->save();

        return $this->ok($this->vendor($vendor->fresh()));
    }

    public function destroyVendor(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->findScoped($request, Vendor::class, $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    // --------------------------------------------------------- timeline

    public function timeline(Request $request): JsonResponse
    {
        $events = $this->scoped($request, TimelineEvent::query())
            ->orderBy('start_time')->get();

        return $this->ok($events->map(fn ($e) => $this->event($e))->values());
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $this->eventRules($request);
        $data['wedding_id'] = $wedding->id;

        return $this->ok($this->event(TimelineEvent::create($data)));
    }

    public function updateEvent(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $event = $this->findScoped($request, TimelineEvent::class, $id);
        $event->fill($this->eventRules($request, true))->save();

        return $this->ok($this->event($event->fresh()));
    }

    public function destroyEvent(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->findScoped($request, TimelineEvent::class, $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    // ---------------------------------------------------------- seating

    public function seating(Request $request): JsonResponse
    {
        $tables = $this->scoped($request, SeatingTable::query())->orderBy('name')->get();

        $seated = Guest::where('wedding_id', $this->wedding($request)->id)
            ->whereNotNull('seating_table_id')
            ->get()
            ->groupBy('seating_table_id');

        $unseated = Guest::where('wedding_id', $this->wedding($request)->id)
            ->whereNull('seating_table_id')
            ->count();

        return $this->ok([
            'tables' => $tables->count(),
            'capacity' => (int) $tables->sum('capacity'),
            'unseated_guests' => $unseated,
            'seating' => $tables->map(function ($table) use ($seated) {
                $rows = $seated->get($table->id, collect());

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'capacity' => (int) $table->capacity,
                    'shape' => $table->shape,
                    'seated' => $rows->count(),
                    'available' => max(0, (int) $table->capacity - $rows->count()),
                    'is_full' => $rows->count() >= (int) $table->capacity,
                    'guests' => $rows->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->fullName(),
                        'party_count' => (int) $g->party_count,
                    ])->values(),
                ];
            })->values(),
        ]);
    }

    public function storeTable(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'shape' => ['sometimes', 'nullable', Rule::in(['round', 'rectangle', 'square', 'oval'])],
        ]);

        $data['wedding_id'] = $wedding->id;

        return $this->ok(SeatingTable::create($data));
    }

    /**
     * Seat or unseat a guest. Refuses to overfill a table, which is the
     * mistake that actually causes trouble on the day.
     */
    public function assignSeat(Request $request): JsonResponse
    {
        $this->authorizeEdit($request);

        $data = $request->validate([
            'guest_id' => ['required', 'integer'],
            'seating_table_id' => ['nullable', 'integer'],
        ]);

        $guest = $this->findScoped($request, Guest::class, (int) $data['guest_id']);

        if (empty($data['seating_table_id'])) {
            $guest->seating_table_id = null;
            $guest->save();

            return $this->ok(['guest_id' => $guest->id, 'seating_table_id' => null]);
        }

        $table = $this->findScoped($request, SeatingTable::class, (int) $data['seating_table_id']);

        $occupied = Guest::where('seating_table_id', $table->id)
            ->where('id', '!=', $guest->id)->count();

        if ($occupied >= (int) $table->capacity) {
            return $this->fail("{$table->name} is already full.", 422);
        }

        $guest->seating_table_id = $table->id;
        $guest->save();

        return $this->ok([
            'guest_id' => $guest->id,
            'seating_table_id' => $table->id,
            'table_name' => $table->name,
            'available' => max(0, (int) $table->capacity - $occupied - 1),
        ]);
    }

    public function destroyTable(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $table = $this->findScoped($request, SeatingTable::class, $id);

        // Free the guests rather than leaving them pointing at nothing.
        Guest::where('seating_table_id', $table->id)->update(['seating_table_id' => null]);
        $table->delete();

        return $this->ok(['deleted' => true]);
    }

    // ------------------------------------------------------------- crew

    public function crew(Request $request): JsonResponse
    {
        $teams = $this->scoped($request, CrewTeam::query())->orderBy('name')->get();

        $members = CrewMember::whereIn('crew_team_id', $teams->pluck('id'))
            ->orderBy('name')->get()->groupBy('crew_team_id');

        return $this->ok($teams->map(fn ($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'responsibilities' => $team->responsibilities,
            'members' => $members->get($team->id, collect())->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone,
                'role' => $m->role,
            ])->values(),
        ])->values());
    }

    // ---------------------------------------------------------- shaping

    protected function taskInWedding(Request $request, int $taskId): ChecklistTask
    {
        $phaseIds = DB::table('checklist_phases')
            ->where('wedding_id', $this->wedding($request)->id)->pluck('id');

        $task = ChecklistTask::whereIn('checklist_phase_id', $phaseIds)->whereKey($taskId)->first();

        if (! $task) {
            abort(404, 'Task not found.');
        }

        return $task;
    }

    protected function task(ChecklistTask $t): array
    {
        return [
            'id' => $t->id,
            'phase_id' => $t->checklist_phase_id,
            'title' => $t->title,
            'completed' => (bool) $t->completed,
            'due_date' => $t->due_date,
            'overdue' => ! $t->completed && $t->due_date && Carbon::parse($t->due_date)->isPast(),
            'sort_order' => (int) $t->sort_order,
        ];
    }

    protected function vendorRules(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$rule, 'string', 'max:160'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_estimate' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5'],
            'deposit_paid' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }

    protected function vendor(Vendor $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'category' => $v->category,
            'email' => $v->email,
            'phone' => $v->phone,
            'website' => $v->website,
            'price_estimate' => (float) $v->price_estimate,
            'amount_paid' => (float) $v->amount_paid,
            'balance' => round((float) $v->price_estimate - (float) $v->amount_paid, 2),
            'status' => $v->status,
            'rating' => (int) $v->rating,
            'deposit_paid' => (bool) $v->deposit_paid,
            'notes' => $v->notes,
        ];
    }

    protected function eventRules(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$rule, 'string', 'max:200'],
            'start_time' => ['sometimes', 'nullable', 'string', 'max:40'],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1440'],
            'location' => ['sometimes', 'nullable', 'string', 'max:200'],
            'category' => ['sometimes', 'nullable', 'string', 'max:80'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'public' => ['sometimes', 'boolean'],
        ]);
    }

    protected function event(TimelineEvent $e): array
    {
        return [
            'id' => $e->id,
            'title' => $e->title,
            'category' => $e->category,
            'event_date' => $e->event_date,
            'start_time' => $e->start_time,
            'duration_minutes' => (int) $e->duration_minutes,
            'location' => $e->location,
            'color' => $e->color,
            'notes' => $e->notes,
            'public' => (bool) $e->public,
        ];
    }
}
