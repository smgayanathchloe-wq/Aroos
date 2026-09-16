<?php

namespace App\Http\Controllers\Api;

use App\Models\BudgetCategory;
use App\Models\Contribution;
use App\Models\Pledge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The money side: what the wedding costs, what guests have promised and
 * what has actually arrived.
 */
class FinanceController extends ApiController
{
    // ----------------------------------------------------------- budget

    public function budget(Request $request): JsonResponse
    {
        $wedding = $this->wedding($request);

        $categories = $this->scoped($request, BudgetCategory::query())
            ->orderByDesc('budgeted')->get();

        $budgeted = (float) $categories->sum('budgeted');
        $spent = (float) $categories->sum('spent');
        $total = (float) $wedding->total_budget;

        return $this->ok([
            'total_budget' => $total,
            'allocated' => round($budgeted, 2),
            'unallocated' => round(max(0, $total - $budgeted), 2),
            'spent' => round($spent, 2),
            'remaining' => round(($total > 0 ? $total : $budgeted) - $spent, 2),
            'percent_spent' => $budgeted > 0 ? round($spent / $budgeted * 100, 1) : 0,
            'currency' => $wedding->currency,
            'categories' => $categories->map(fn ($c) => $this->category($c))->values(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:40'],
            'budgeted' => ['sometimes', 'numeric', 'min:0'],
            'spent' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $data['wedding_id'] = $wedding->id;

        return $this->ok($this->category(BudgetCategory::create($data)));
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $category = $this->findScoped($request, BudgetCategory::class, $id);

        $category->fill($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:40'],
            'budgeted' => ['sometimes', 'numeric', 'min:0'],
            'spent' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]))->save();

        return $this->ok($this->category($category->fresh()));
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->findScoped($request, BudgetCategory::class, $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    /** Set the headline budget for the whole wedding. */
    public function updateTotalBudget(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $request->validate([
            'total_budget' => ['required', 'numeric', 'min:0'],
        ]);

        $wedding->total_budget = $data['total_budget'];
        $wedding->save();

        return $this->ok(['total_budget' => (float) $wedding->total_budget]);
    }

    // ---------------------------------------------------------- pledges

    public function pledges(Request $request): JsonResponse
    {
        $query = Pledge::query()->with('guest');
        $this->scoped($request, $query);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('pledger_name', 'like', "%{$search}%")
                    ->orWhere('pledger_phone', 'like', "%{$search}%")
                    ->orWhere('pledger_email', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderByDesc('amount')->paginate($this->perPage($request, 50));

        return $this->paginated($paginator, fn ($p) => $this->pledge($p));
    }

    public function showPledge(Request $request, int $id): JsonResponse
    {
        $pledge = $this->findScoped($request, Pledge::class, $id);

        $payload = $this->pledge($pledge->load('guest'));

        $payload['contributions'] = Contribution::where('pledge_id', $pledge->id)
            ->orderByDesc('received_on')->get()
            ->map(fn ($c) => $this->contribution($c))->values();

        return $this->ok($payload);
    }

    public function storePledge(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $request->validate([
            'pledger_name' => ['required', 'string', 'max:160'],
            'pledger_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'pledger_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['pledged', 'partial', 'paid', 'cancelled'])],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'guest_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $data['wedding_id'] = $wedding->id;
        $data['status'] = $data['status'] ?? 'pledged';

        return $this->ok($this->pledge(Pledge::create($data)->fresh('guest')));
    }

    public function updatePledge(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $pledge = $this->findScoped($request, Pledge::class, $id);

        $pledge->fill($request->validate([
            'pledger_name' => ['sometimes', 'string', 'max:160'],
            'pledger_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'pledger_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['pledged', 'partial', 'paid', 'cancelled'])],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]))->save();

        return $this->ok($this->pledge($pledge->fresh('guest')));
    }

    public function destroyPledge(Request $request, int $id): JsonResponse
    {
        $this->authorizeEdit($request);

        $this->findScoped($request, Pledge::class, $id)->delete();

        return $this->ok(['deleted' => true]);
    }

    /**
     * Record money received against a pledge, and move the pledge's status
     * to match what has now been paid.
     */
    public function storeContribution(Request $request): JsonResponse
    {
        $wedding = $this->authorizeEdit($request);

        $data = $request->validate([
            'pledge_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['sometimes', 'nullable', 'string', 'max:60'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:120'],
            'received_on' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $pledge = $this->findScoped($request, Pledge::class, (int) $data['pledge_id']);

        $contribution = DB::transaction(function () use ($data, $pledge, $wedding) {
            $contribution = Contribution::create([
                'wedding_id' => $wedding->id,
                'pledge_id' => $pledge->id,
                'guest_id' => $pledge->guest_id,
                'contributor_name' => $pledge->pledger_name,
                'amount' => $data['amount'],
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'received_on' => $data['received_on'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            // Recompute from the ledger rather than incrementing, so the
            // status is always consistent with what was actually received.
            $paid = (float) Contribution::where('pledge_id', $pledge->id)->sum('amount');

            $pledge->status = match (true) {
                $paid <= 0 => 'pledged',
                $paid + 0.001 >= (float) $pledge->amount => 'paid',
                default => 'partial',
            };
            $pledge->save();

            return $contribution;
        });

        return $this->ok($this->contribution($contribution->fresh()) + [
            'pledge_status' => $pledge->fresh()->status,
        ]);
    }

    public function contributions(Request $request): JsonResponse
    {
        $query = Contribution::query();
        $this->scoped($request, $query);

        $paginator = $query->orderByDesc('received_on')->paginate($this->perPage($request, 50));

        return $this->paginated($paginator, fn ($c) => $this->contribution($c));
    }

    /**
     * Totals for the pledges screen.
     */
    public function pledgeSummary(Request $request): JsonResponse
    {
        $wedding = $this->wedding($request);

        $pledges = $this->scoped($request, Pledge::query())->get();
        $received = (float) $this->scoped($request, Contribution::query())->sum('amount');
        $promised = (float) $pledges->sum('amount');

        return $this->ok([
            'currency' => $wedding->currency,
            'total_pledged' => round($promised, 2),
            'total_received' => round($received, 2),
            'outstanding' => round(max(0, $promised - $received), 2),
            'fulfilment_rate' => $promised > 0 ? round($received / $promised * 100, 1) : 0,
            'count' => $pledges->count(),
            'by_status' => $pledges->groupBy(fn ($p) => $p->status ?: 'pledged')
                ->map(fn ($rows, $status) => [
                    'label' => ucfirst((string) $status),
                    'count' => $rows->count(),
                    'total' => round((float) $rows->sum('amount'), 2),
                ])->values(),
            'top_pledgers' => $pledges->sortByDesc('amount')->take(5)->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->pledger_name,
                'amount' => (float) $p->amount,
                'status' => $p->status,
            ])->values(),
        ]);
    }

    // ---------------------------------------------------------- shaping

    protected function category(BudgetCategory $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'icon' => $c->icon,
            'budgeted' => (float) $c->budgeted,
            'spent' => (float) $c->spent,
            'remaining' => round((float) $c->budgeted - (float) $c->spent, 2),
            'percent' => $c->budgeted > 0 ? round($c->spent / $c->budgeted * 100, 1) : 0,
            'over_budget' => (float) $c->spent > (float) $c->budgeted,
            'notes' => $c->notes,
        ];
    }

    protected function pledge(Pledge $p): array
    {
        $paid = (float) Contribution::where('pledge_id', $p->id)->sum('amount');

        return [
            'id' => $p->id,
            'pledger_name' => $p->pledger_name,
            'pledger_phone' => $p->pledger_phone,
            'pledger_email' => $p->pledger_email,
            'amount' => (float) $p->amount,
            'paid' => round($paid, 2),
            'outstanding' => round(max(0, (float) $p->amount - $paid), 2),
            'percent_paid' => $p->amount > 0 ? round($paid / $p->amount * 100, 1) : 0,
            'status' => $p->status,
            'due_date' => $p->due_date,
            'guest_id' => $p->guest_id,
            'guest_name' => $p->guest?->fullName(),
            'notes' => $p->notes,
        ];
    }

    protected function contribution(Contribution $c): array
    {
        return [
            'id' => $c->id,
            'pledge_id' => $c->pledge_id,
            'contributor_name' => $c->contributor_name,
            'amount' => (float) $c->amount,
            'method' => $c->method,
            'reference' => $c->reference,
            'received_on' => $c->received_on,
            'notes' => $c->notes,
        ];
    }
}
