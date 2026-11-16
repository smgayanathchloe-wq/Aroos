<?php

namespace App\Outreach;

use App\Models\Conversation;
use App\Models\KnowledgeQuery;
use App\Models\OptOut;
use App\Models\Pledge;
use App\Models\PledgePromise;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Writes what a conversation established back into the app: promises, new
 * pledges, opt-outs, unanswered questions, and the follow-ups that result.
 * Both channels feed it the same normalised shape.
 *
 * The AI never changes money already on record. A new pledge (amount was 0)
 * is accepted; a claimed payment or a revised amount becomes a task for the
 * committee to verify.
 */
class OutcomeRegistrar
{
    public function __construct(private readonly FollowUpEngine $followUps)
    {
    }

    public function optOut(Pledge $pledge, ?Conversation $conversation, string $reason): void
    {
        $config = $pledge->wedding->outreach();
        $phone = Phone::normalize($pledge->pledger_phone, $config->get('compliance.country_code', '255'));

        DB::transaction(function () use ($pledge, $conversation, $reason, $phone) {
            if ($phone) {
                OptOut::firstOrCreate(
                    ['wedding_id' => $pledge->wedding_id, 'phone' => $phone],
                    ['channel' => $conversation?->channel, 'reason' => mb_substr($reason, 0, 250), 'conversation_id' => $conversation?->id],
                );
            }
            $this->followUps->cancelEverything($pledge);
            $pledge->promises()->where('status', 'open')->update(['status' => 'superseded']);
        });
    }

    /**
     * @param  array{outcome: ?string, promised_date: ?string, promised_amount: ?float, pledged_amount: ?float,
     *               confidence: string, unanswered_questions: array, questions_asked?: array, callback_time: ?string,
     *               summary: ?string, raw_text?: ?string, review_if_unsure?: bool}  $r
     * @return array{promise: ?PledgePromise, promise_rejected: ?string}
     */
    public function register(Conversation $conversation, array $r): array
    {
        $pledge = $conversation->pledge;
        $config = $pledge->wedding->outreach();
        $tz = $config->timezone();
        $today = CarbonImmutable::now($tz)->startOfDay();
        $result = ['promise' => null, 'promise_rejected' => null];

        if (($r['outcome'] ?? null) === 'opt_out') {
            $this->optOut($pledge, $conversation, $r['summary'] ?? 'Guest asked not to be contacted');

            return $result;
        }

        DB::transaction(function () use ($conversation, $pledge, $config, $tz, $today, $r, &$result) {
            // A pledger with no pledge agreed to one: record it.
            if (($r['pledged_amount'] ?? null) && (float) $pledge->amount <= 0) {
                $pledge->amount = $r['pledged_amount'];
                $pledge->notes = trim(($pledge->notes ? $pledge->notes."\n" : '').'Pledge captured by AI '.$conversation->channel.' on '.$today->toDateString().'.');
                $pledge->save();
                $pledge->refreshStatus();
            } elseif (($r['pledged_amount'] ?? null) && abs((float) $r['pledged_amount'] - (float) $pledge->amount) > 0.01) {
                $this->followUps->schedule($pledge, 'review_amount', now(), null,
                    'Guest mentioned a pledge of '.$r['pledged_amount'].' (on record: '.$pledge->amount.'). Confirm before changing.', $conversation->id);
            }

            if ($date = $r['promised_date'] ?? null) {
                $promised = CarbonImmutable::parse($date, $tz);
                if ($promised->lt($today)) {
                    $result['promise_rejected'] = 'date_in_past';
                } else {
                    $confidence = $r['confidence'] ?? 'medium';
                    $weddingDate = $config->get('wedding.wedding_date');
                    $cutoff = $weddingDate ? CarbonImmutable::parse($weddingDate, $tz)->subDays((int) $config->get('pledge_rules.min_days_before_wedding_for_promise', 3)) : null;
                    if ($cutoff && $promised->gt($cutoff)) {
                        $confidence = 'low'; // later than the committee needs; flag for a human
                    }

                    $pledge->promises()->where('status', 'open')->update(['status' => 'superseded']);
                    $result['promise'] = $pledge->promises()->create([
                        'wedding_id' => $pledge->wedding_id,
                        'conversation_id' => $conversation->id,
                        'amount' => $r['promised_amount'] ?? null,
                        'promised_date' => $promised->toDateString(),
                        'confidence' => $confidence,
                        'raw_text' => $r['raw_text'] ?? null,
                    ]);

                    $this->followUps->cancel($pledge, FollowUpEngine::CHASING);
                    $remindAt = $promised->subDays(2)->setTime(10, 0);
                    if ($remindAt->gt(CarbonImmutable::now($tz))) {
                        $this->followUps->schedule($pledge, 'promise_due', $remindAt, $conversation->channel);
                    }
                    $this->followUps->schedule($pledge, 'promise_passed', $promised->addDay()->setTime(10, 0), $conversation->channel);

                    if ($confidence !== 'high' && ($r['review_if_unsure'] ?? false)) {
                        $this->followUps->schedule($pledge, 'low_confidence', now(), null, 'Check the captured promise date ('.$promised->toDateString().').', $conversation->id);
                    }
                }
            }

            $outcome = $r['outcome'] ?? null;

            if ($outcome === 'already_paid') {
                $this->followUps->cancel($pledge, FollowUpEngine::CHASING);
                $this->followUps->schedule($pledge, 'verify_payment', now(), null,
                    'Guest says they have already paid. Check the records and record the payment if found.', $conversation->id);
            }

            if ($outcome === 'callback') {
                $at = ($r['callback_time'] ?? null) ? CarbonImmutable::parse($r['callback_time']) : CarbonImmutable::now($tz)->addDay()->setTime(10, 0);
                $this->followUps->schedule($pledge, 'callback', $at, $conversation->channel, $r['summary'] ?? null, $conversation->id);
            }

            if (in_array($outcome, ['refused', 'no_pledge'], true)) {
                $this->followUps->cancel($pledge, FollowUpEngine::CHASING);
            }

            // Voice: anything not clearly captured goes to a human, not another call.
            if (($r['review_if_unsure'] ?? false) && ! $result['promise'] && ! in_array($outcome, ['already_paid', 'refused', 'no_pledge', 'callback', 'voicemail', 'no_answer', 'wrong_person'], true)
                && $pledge->outreachState() !== 'paid') {
                $this->followUps->schedule($pledge, 'low_confidence', now(), null, $r['summary'] ?? 'No promise captured on the call.', $conversation->id);
            }

            foreach ($r['unanswered_questions'] ?? [] as $question) {
                KnowledgeQuery::create([
                    'wedding_id' => $pledge->wedding_id,
                    'pledge_id' => $pledge->id,
                    'conversation_id' => $conversation->id,
                    'question' => $question,
                    'answer_given' => $r['fallback_text'] ?? null,
                    'fell_back' => true,
                ]);
                $this->followUps->schedule($pledge, 'coordinator_question', now(), null, $question, $conversation->id);
            }

            $pledge->forceFill([
                'last_contacted_at' => now(),
                'last_ai_summary' => $r['summary'] ?? $pledge->last_ai_summary,
            ])->save();
        });

        return $result;
    }
}
