<?php

namespace App\Http\Controllers;

use App\Models\Contribution;
use App\Models\Pledge;
use App\Models\Wedding;
use App\Services\ClickPesa;
use App\Services\PaymentRecorder;
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
            // 0 = this person has not pledged yet; the AI outreach invites them to.
            'amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['amount'] = (float) ($data['amount'] ?? 0);
        $data['status'] = Pledge::statusFor($data['amount'], 0);

        $wedding->pledges()->create($data);

        return back()->with('success', $data['amount'] > 0 ? 'Pledge recorded.' : 'Person added; they have not pledged yet.');
    }

    public function update(Request $request, Wedding $wedding, Pledge $pledge)
    {
        abort_unless($pledge->wedding_id === $wedding->id, 404);
        $data = $request->validate([
            'pledger_name' => ['required', 'string', 'max:255'],
            'pledger_email' => ['nullable', 'email', 'max:255'],
            'pledger_phone' => ['nullable', 'string', 'max:30'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:none,pledged,partial,fulfilled,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);
        $pledge->update($data);
        if (($data['status'] ?? null) !== 'cancelled') {
            $pledge->refreshStatus();
        }

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

    public function storeContribution(Request $request, Wedding $wedding, PaymentRecorder $payments)
    {
        $data = $request->validate([
            'pledge_id' => ['nullable', 'integer', 'exists:pledges,id'],
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'contributor_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,mpesa,bank,paypal,stripe,clickpesa,other'],
            'reference' => ['nullable', 'string', 'max:255'],
            'received_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $pledge = isset($data['pledge_id']) ? $wedding->pledges()->find($data['pledge_id']) : null;

        if ($pledge) {
            $contribution = $payments->record($pledge, (float) $data['amount'], $data['method'], 'manual',
                $data['reference'] ?? null, $data['received_on'] ?? null, $data['notes'] ?? null);
            if (($data['contributor_name'] ?? '') !== '' && $contribution) {
                $contribution->update(['contributor_name' => $data['contributor_name']]);
            }
        } else {
            unset($data['pledge_id']);
            $wedding->contributions()->create($data);
        }

        return back()->with('success', 'Contribution recorded.');
    }

    /** Ask the pledger to pay via a ClickPesa mobile-money push (USSD prompt on their phone). */
    public function requestClickPesa(Request $request, Wedding $wedding, Pledge $pledge, ClickPesa $clickPesa)
    {
        abort_unless($pledge->wedding_id === $wedding->id, 404);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:500']]);

        if (! $clickPesa->configured($wedding->outreach())) {
            return back()->withErrors(['clickpesa' => 'ClickPesa is not set up for this wedding.']);
        }

        try {
            $result = $clickPesa->requestPayment($pledge, (float) $data['amount']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['clickpesa' => $e->getMessage()]);
        }

        return back()->with('success', 'Payment prompt sent to '.$pledge->pledger_name.' (ref '.$result['order_reference'].'). It will be recorded automatically once paid.');
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
