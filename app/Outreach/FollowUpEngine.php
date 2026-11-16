<?php

namespace App\Outreach;

use App\Models\FollowUp;
use App\Models\OutreachJob;
use App\Models\Pledge;
use Carbon\CarbonInterface;

/**
 * Owns follow-up rows: scheduling (one pending row per pledge and reason) and
 * cancellation. A follow-up with a channel is an automatic contact the
 * scheduler sends when due; one without a channel is a task for the committee.
 */
class FollowUpEngine
{
    /** Follow-ups that chase the pledger; a captured promise or payment cancels them. */
    public const CHASING = ['no_reply', 'no_answer_retry', 'callback', 'low_confidence'];

    public function schedule(Pledge $pledge, string $reason, CarbonInterface $dueAt, ?string $channel, ?string $notes = null, ?int $conversationId = null): FollowUp
    {
        return FollowUp::updateOrCreate(
            ['pledge_id' => $pledge->id, 'reason' => $reason, 'status' => 'pending'],
            [
                'wedding_id' => $pledge->wedding_id,
                'due_at' => $dueAt,
                'channel' => $channel,
                'notes' => $notes,
                'conversation_id' => $conversationId,
            ],
        );
    }

    /** Cancel pending follow-ups for the pledge (all of them when $reasons is null). */
    public function cancel(Pledge $pledge, ?array $reasons = null): int
    {
        $query = FollowUp::where('pledge_id', $pledge->id)->where('status', 'pending');
        if ($reasons !== null) {
            $query->whereIn('reason', $reasons);
        }

        return $query->update(['status' => 'cancelled', 'updated_at' => now()]);
    }

    /** Stop every future contact: pending follow-ups and scheduled jobs. */
    public function cancelEverything(Pledge $pledge): void
    {
        $this->cancel($pledge);
        OutreachJob::where('pledge_id', $pledge->id)->where('status', 'pending')
            ->update(['status' => 'cancelled', 'result' => 'cancelled', 'updated_at' => now()]);
    }

    public function remindersSent(Pledge $pledge): int
    {
        return FollowUp::where('pledge_id', $pledge->id)->where('reason', 'no_reply')->where('status', 'done')->count();
    }

    /** After a message we initiated: nudge once more if the guest stays silent. */
    public function scheduleNoReply(Pledge $pledge, OutreachConfig $config, string $channel): void
    {
        if ($this->remindersSent($pledge) >= (int) $config->get('pledge_rules.max_follow_ups', 2)) {
            return;
        }
        $this->schedule($pledge, 'no_reply', now()->addDays((int) $config->get('pledge_rules.escalation_after_days', 3)), $channel);
    }

    /** Payment arrived: a fully paid pledger is never chased again. */
    public function onPaymentRecorded(Pledge $pledge): void
    {
        if ($pledge->outreachState() !== 'paid') {
            return;
        }
        $this->cancel($pledge, [...self::CHASING, 'promise_due', 'promise_passed', 'verify_payment']);
        OutreachJob::where('pledge_id', $pledge->id)->where('status', 'pending')->where('purpose', '!=', 'initial')
            ->update(['status' => 'cancelled', 'result' => 'fully_paid', 'updated_at' => now()]);
        $pledge->promises()->where('status', 'open')->update(['status' => 'fulfilled', 'updated_at' => now()]);
    }
}
