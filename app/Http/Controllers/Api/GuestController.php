<?php

namespace App\Http\Controllers\Api;

use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestController extends ApiController
{
    protected const RSVP_STATUSES = ['pending', 'attending', 'declined', 'maybe'];

    public function index(Request $request): JsonResponse
    {
        $query = Guest::query()->with('seatingTable');
        $this->scoped($request, $query);

        if ($status = $request->query('rsvp_status')) {
            $query->where('rsvp_status', $status);
        }

        if ($side = $request->query('side')) {
            $query->where('side', $side);
        }

        if ($request->query('checked_in') === '1') {
            $query->whereNotNull('checked_in_at');
        }

        if ($request->query('unseated') === '1') {
            $query->whereNull('seating_table_id');
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('first_name')->orderBy('last_name')
            ->paginate($this->perPage($request, 50));

        return $this->paginated($paginator, fn ($g) => $this->transform($g));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $guest = $this->findScoped($request, Guest::class, $id);

        return $this->ok($this->transform($guest->load(['seatingTable', 'pledges'])));
    }

    public function store(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $this->validated($request);
        $data['wedding_id'] = $wedding->id;

        $guest = Guest::create($data);

        return $this->ok($this->transform($guest->fresh('seatingTable')));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $guest = $this->findScoped($request, Guest::class, $id);
        $guest->fill($this->validated($request, true))->save();

        return $this->ok($this->transform($guest->fresh('seatingTable')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->findScoped($request, Guest::class, $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    /**
     * Record an RSVP on the guest's behalf, which is what a planner does
     * when someone replies by phone.
     */
    public function rsvp(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $data = $request->validate([
            'rsvp_status' => ['required', Rule::in(self::RSVP_STATUSES)],
            'party_count' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'meal_preference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dietary_notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $guest = $this->findScoped($request, Guest::class, $id);

        $guest->fill($data);
        $guest->rsvp_at = now();
        $guest->save();

        return $this->ok($this->transform($guest->fresh('seatingTable')));
    }

    /**
     * Door check-in on the day. Idempotent, so a double tap cannot
     * double-count a party.
     */
    public function checkIn(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $data = $request->validate([
            'count' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $guest = $this->findScoped($request, Guest::class, $id);

        if ($guest->checked_in_at) {
            return $this->ok($this->transform($guest) + ['already_checked_in' => true]);
        }

        $guest->checked_in_at = now();
        $guest->checked_in_count = $data['count'] ?? max(1, (int) $guest->party_count);
        $guest->save();

        return $this->ok($this->transform($guest->fresh('seatingTable')));
    }

    public function undoCheckIn(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $guest = $this->findScoped($request, Guest::class, $id);
        $guest->checked_in_at = null;
        $guest->checked_in_count = 0;
        $guest->save();

        return $this->ok($this->transform($guest->fresh('seatingTable')));
    }

    /**
     * Headline numbers for the guest screen.
     */
    public function summary(Request $request): JsonResponse
    {
        $guests = $this->scoped($request, Guest::query())->get();

        return $this->ok([
            'total' => $guests->count(),
            'expected_headcount' => (int) $guests->sum(fn ($g) => max(1, (int) $g->party_count)),
            'attending' => $guests->where('rsvp_status', 'attending')->count(),
            'declined' => $guests->where('rsvp_status', 'declined')->count(),
            'pending' => $guests->whereNotIn('rsvp_status', ['attending', 'declined'])->count(),
            'checked_in' => $guests->whereNotNull('checked_in_at')->count(),
            'checked_in_headcount' => (int) $guests->sum('checked_in_count'),
            'by_side' => $guests->groupBy(fn ($g) => $g->side ?: 'unassigned')
                ->map(fn ($rows, $side) => ['label' => ucfirst((string) $side), 'total' => $rows->count()])
                ->values(),
            'by_rsvp' => $guests->groupBy(fn ($g) => $g->rsvp_status ?: 'pending')
                ->map(fn ($rows, $status) => ['label' => ucfirst((string) $status), 'total' => $rows->count()])
                ->values(),
        ]);
    }

    protected function validated(Request $request, bool $partial = false): array
    {
        $rule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'first_name' => [$rule, 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'party_count' => ['sometimes', 'integer', 'min:0', 'max:50'],
            'side' => ['sometimes', 'nullable', 'string', 'max:60'],
            'group_label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'meal_preference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dietary_notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'rsvp_status' => ['sometimes', Rule::in(self::RSVP_STATUSES)],
            'is_special_guest' => ['sometimes', 'boolean'],
            'seating_table_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);
    }

    protected function transform(Guest $guest): array
    {
        return [
            'id' => $guest->id,
            'first_name' => $guest->first_name,
            'last_name' => $guest->last_name,
            'full_name' => $guest->fullName(),
            'email' => $guest->email,
            'phone' => $guest->phone,
            'party_count' => (int) $guest->party_count,
            'side' => $guest->side,
            'group_label' => $guest->group_label,
            'meal_preference' => $guest->meal_preference,
            'dietary_notes' => $guest->dietary_notes,
            'rsvp_status' => $guest->rsvp_status ?: 'pending',
            'rsvp_at' => optional($guest->rsvp_at)->toIso8601String(),
            'is_special_guest' => (bool) $guest->is_special_guest,
            'seating_table_id' => $guest->seating_table_id,
            'seating_table' => $guest->seatingTable?->name,
            'seat_numbers' => $guest->seat_numbers,
            'checked_in' => $guest->checked_in_at !== null,
            'checked_in_at' => optional($guest->checked_in_at)->toIso8601String(),
            'checked_in_count' => (int) $guest->checked_in_count,
            'notes' => $guest->notes,
        ];
    }

    /**
     * Door mode: the whole guest list with check-in counters, mirroring
     * the web's checkin.index screen.
     *
     * Deliberately unpaginated. On the door you want every guest
     * searchable instantly, without fetching more pages as people queue.
     */
    public function checkInBoard(Request $request): JsonResponse
    {
        $query = Guest::query()->with('seatingTable');
        $this->scoped($request, $query);

        $guests = $query->orderBy('first_name')->orderBy('last_name')->get();

        return $this->ok([
            'stats' => [
                'total_parties' => $guests->count(),
                'total_people' => (int) $guests->sum('party_count'),
                'attending' => (int) $guests->where('rsvp_status', 'attending')->sum('party_count'),
                'checked_in_parties' => $guests->whereNotNull('checked_in_at')->count(),
                'checked_in_people' => (int) $guests->sum('checked_in_count'),
            ],
            'guests' => $guests->map(fn ($g) => $this->transform($g))->values()->all(),
        ]);
    }

    /**
     * Resolve the QR code on a printed invitation.
     *
     * The web scans these at w/{slug}/checkin/{token}; this is the same
     * lookup for a phone camera. Read-only: confirming is a separate
     * call so a scan can be reviewed before it counts anybody in.
     */
    public function findByToken(Request $request, string $token): JsonResponse
    {
        $query = Guest::query()->with('seatingTable');
        $this->scoped($request, $query);

        $guest = $query->where('checkin_token', $token)->first()
            ?: (clone $query)->where('invite_token', $token)->first();

        if (! $guest) {
            return $this->fail('That code does not match a guest for this wedding.', 404);
        }

        return $this->ok($this->transform($guest));
    }
}
