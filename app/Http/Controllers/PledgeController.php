<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Pledge;
use App\Models\Wedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PledgeController extends Controller
{
    public function index(Request $request, Wedding $wedding)
    {
        $query = $wedding->pledges()->with(['guest', 'contributions']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $pledges = $query->latest()->get();
        $contributions = $wedding->contributions()->with(['pledge', 'guest'])->latest()->get();

        $stats = [
            'total_pledged' => $wedding->pledges()->sum('amount'),
            'total_contributed' => $wedding->contributions()->sum('amount'),
            'fulfilled' => $wedding->pledges()->where('status', 'fulfilled')->count(),
            'outstanding' => $wedding->pledges()->whereIn('status', ['pledged', 'partial'])->count(),
        ];

        return view('pledges.index', compact('wedding', 'pledges', 'contributions', 'stats'));
    }

    public function store(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'pledger_name' => ['required', 'string', 'max:255'],
            'pledger_email' => ['nullable', 'email', 'max:255'],
            'pledger_phone' => ['nullable', 'string', 'max:30'],
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $wedding->pledges()->create($data);

        return back()->with('success', 'Pledge recorded.');
    }

    public function update(Request $request, Wedding $wedding, Pledge $pledge)
    {
        abort_unless($pledge->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'pledger_name' => ['required', 'string', 'max:255'],
            'pledger_email' => ['nullable', 'email', 'max:255'],
            'pledger_phone' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pledged,partial,fulfilled,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);
        $pledge->update($data);

        return back()->with('success', 'Pledge updated.');
    }

    public function destroy(Wedding $wedding, Pledge $pledge)
    {
        abort_unless($pledge->wedding_id === $wedding->id, 404);
        $pledge->delete();

        return back()->with('success', 'Pledge removed.');
    }

    /**
     * Delete every pledge and contribution for this wedding.
     *
     * This is irreversible and wipes the whole gift ledger, so it requires the
     * user to type the confirmation phrase rather than just clicking a button.
     * Scoped to the given wedding: other weddings are never touched.
     */
    public function destroyAll(Request $request, Wedding $wedding)
    {
        $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if (strtoupper(trim($request->input('confirmation'))) !== 'DELETE') {
            return back()->withErrors([
                'confirmation' => 'Type DELETE to confirm. Nothing was removed.',
            ]);
        }

        $scope = $request->input('scope', 'all');

        $pledgeCount = $wedding->pledges()->count();
        $contributionCount = $wedding->contributions()->count();

        DB::transaction(function () use ($wedding, $scope) {
            // Contributions reference pledges, so they always go first to
            // avoid leaving rows pointing at a deleted pledge.
            $wedding->contributions()->delete();

            if ($scope === 'all') {
                $wedding->pledges()->delete();
            }
        });

        if ($scope === 'contributions') {
            // Pledges survive, but their paid totals just changed, so the
            // cached status on each one is now stale.
            $wedding->pledges()->update(['status' => 'pledged']);

            return back()->with('success', "Removed {$contributionCount} contribution(s). Pledges were kept.");
        }

        return back()->with('success', "Removed {$pledgeCount} pledge(s) and {$contributionCount} contribution(s).");
    }

    public function printList(Wedding $wedding)
    {
        $pledges = $wedding->pledges()->with(['guest', 'contributions'])->orderBy('pledger_name')->get();
        $contributions = $wedding->contributions()->with('pledge')->orderBy('received_on')->get();

        return view('pledges.print', compact('wedding', 'pledges', 'contributions'));
    }

    public function storeContribution(Request $request, Wedding $wedding)
    {
        $data = $request->validate([
            'pledge_id' => ['nullable', 'integer', 'exists:pledges,id'],
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'contributor_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,mpesa,bank,paypal,stripe,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'received_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $contribution = $wedding->contributions()->create($data);

        if ($contribution->pledge_id) {
            $pledge = Pledge::find($contribution->pledge_id);
            if ($pledge && $pledge->wedding_id === $wedding->id) {
                $pledge->refreshStatus();
            }
        }

        return back()->with('success', 'Contribution recorded.');
    }

    public function destroyContribution(Wedding $wedding, Contribution $contribution)
    {
        abort_unless($contribution->wedding_id === $wedding->id, 404);
        $pledgeId = $contribution->pledge_id;
        $contribution->delete();

        if ($pledgeId) {
            Pledge::find($pledgeId)?->refreshStatus();
        }

        return back()->with('success', 'Contribution removed.');
    }
}
