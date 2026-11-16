<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Pledge;
use App\Outreach\FollowUpEngine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The one way money is recorded against a pledge, whatever the source:
 * committee entry (cash, M-Pesa, bank...), the mobile API, or a ClickPesa
 * webhook. Recomputes the pledge status from the ledger and stops any outreach
 * the payment makes unnecessary.
 */
class PaymentRecorder
{
    public function __construct(private readonly FollowUpEngine $followUps)
    {
    }

    /**
     * @param  string|null  $externalRef  provider transaction id; makes the call idempotent per source
     * @return Contribution|null null when this external payment was already recorded
     */
    public function record(Pledge $pledge, float $amount, string $method, string $source = 'manual', ?string $reference = null,
        ?string $receivedOn = null, ?string $notes = null, ?string $externalRef = null): ?Contribution
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive.');
        }

        try {
            $contribution = DB::transaction(function () use ($pledge, $amount, $method, $source, $reference, $receivedOn, $notes, $externalRef) {
                $contribution = Contribution::create([
                    'wedding_id' => $pledge->wedding_id,
                    'pledge_id' => $pledge->id,
                    'guest_id' => $pledge->guest_id,
                    'contributor_name' => $pledge->pledger_name,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'received_on' => $receivedOn ?? now()->toDateString(),
                    'notes' => $notes,
                    'source' => $source,
                    'external_ref' => $externalRef,
                ]);
                $pledge->refreshStatus();

                return $contribution;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->followUps->onPaymentRecorded($pledge->fresh());

        return $contribution;
    }
}
