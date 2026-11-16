<?php

namespace App\Outreach;

use App\Models\Conversation;
use App\Models\ConversationTurn;
use App\Models\OptOut;
use App\Models\Pledge;
use Carbon\CarbonImmutable;

/**
 * The single gate every outbound contact passes. Compliance lives here, in
 * code, not in prompts: opt-out, consent, calling hours, rate limits, and
 * "never ask a fully paid pledger for money".
 *
 * Replies inside a conversation the guest is actively having do not pass
 * through here; only contacts we initiate do.
 */
class ContactPolicy
{
    /** Purposes that ask about money (as opposed to a pure thank-you). */
    private const PLEDGE_CHASING = ['initial', 'reminder', 'no_reply', 'no_answer_retry', 'callback', 'promise_due', 'promise_passed'];

    /** Purposes held back while the pledger has a promise date still ahead. */
    private const HELD_BY_PROMISE = ['initial', 'reminder', 'no_reply', 'no_answer_retry'];

    /**
     * @return array{reason: string, retry_at: ?CarbonImmutable}|null  null = allowed
     */
    public function check(Pledge $pledge, string $channel, string $purpose, ?CarbonImmutable $now = null): ?array
    {
        $config = $pledge->wedding->outreach();
        $tz = $config->timezone();
        $now = ($now ?? CarbonImmutable::now())->setTimezone($tz);
        $deny = fn (string $reason, ?CarbonImmutable $retry = null) => ['reason' => $reason, 'retry_at' => $retry];

        if (! $config->get('enabled')) {
            return $deny('outreach_disabled');
        }

        $phone = Phone::normalize($pledge->pledger_phone, $config->get('compliance.country_code', '255'));
        if (! $phone) {
            return $deny('no_phone');
        }
        if (self::isOptedOut($pledge->wedding_id, $phone)) {
            return $deny('opted_out');
        }
        if ($config->get('compliance.consent_required') && ! $pledge->outreach_consent) {
            return $deny('no_consent');
        }
        if ($pledge->status === 'cancelled') {
            return $deny('pledge_cancelled');
        }

        $weddingDate = $config->get('wedding.wedding_date');
        if ($weddingDate && CarbonImmutable::parse($weddingDate, $tz)->endOfDay()->lt($now)) {
            return $deny('wedding_passed');
        }

        $state = $pledge->outreachState();
        if ($state === 'paid') {
            // Fully paid: never ask for money again. Only the one thank-you contact.
            if ($purpose !== 'initial' || ($channel === 'voice' && ! $config->get('voice.call_paid_guests'))) {
                return $deny('fully_paid');
            }
        } elseif ($state === 'none' && ! $config->get('pledge_rules.ask_if_no_pledge')) {
            return $deny('no_pledge');
        }

        if (in_array($purpose, self::HELD_BY_PROMISE, true) && $this->hasPendingPromise($pledge, $now)) {
            return $deny('promise_pending');
        }

        $window = $this->nextWindow($config, $now);
        if (! $window->equalTo($now)) {
            return $deny('outside_hours', $window);
        }

        if ($retry = $this->rateLimited($pledge, $channel, $now, $config)) {
            return $deny('rate_limited', $retry);
        }

        return null;
    }

    public static function isOptedOut(int $weddingId, string $normalizedPhone): bool
    {
        return OptOut::where('wedding_id', $weddingId)->where('phone', $normalizedPhone)->exists();
    }

    public function isPledgeChasing(string $purpose): bool
    {
        return in_array($purpose, self::PLEDGE_CHASING, true);
    }

    /** $now if inside calling hours, else the start of the next allowed window. */
    public function nextWindow(OutreachConfig $config, CarbonImmutable $now): CarbonImmutable
    {
        [$sh, $sm] = array_map('intval', explode(':', $config->get('compliance.calling_hours_start', '09:00')));
        [$eh, $em] = array_map('intval', explode(':', $config->get('compliance.calling_hours_end', '20:00')));
        $start = $now->setTime($sh, $sm);
        $end = $now->setTime($eh, $em);

        if ($now->lt($start)) {
            return $start;
        }
        if ($now->gte($end)) {
            return $start->addDay();
        }

        return $now;
    }

    private function hasPendingPromise(Pledge $pledge, CarbonImmutable $now): bool
    {
        return $pledge->promises()->where('status', 'open')->whereDate('promised_date', '>=', $now->toDateString())->exists();
    }

    private function rateLimited(Pledge $pledge, string $channel, CarbonImmutable $now, OutreachConfig $config): ?CarbonImmutable
    {
        if ($channel === 'voice') {
            $calledToday = Conversation::where('pledge_id', $pledge->id)->where('channel', 'voice')
                ->where('created_at', '>=', $now->startOfDay()->utc())->exists();

            return $calledToday ? $this->nextWindow($config, $now->addDay()->startOfDay()) : null;
        }

        // Messaging: at most one message we initiate per 24h, unless the guest
        // has replied since our last one.
        $lastOut = ConversationTurn::query()
            ->whereHas('conversation', fn ($q) => $q->where('pledge_id', $pledge->id)->whereIn('channel', ['whatsapp', 'sms']))
            ->where('role', 'assistant')->latest('id')->first();
        if (! $lastOut || $lastOut->created_at->lt($now->subDay())) {
            return null;
        }
        $repliedSince = ConversationTurn::where('conversation_id', $lastOut->conversation_id)
            ->where('role', 'guest')->where('id', '>', $lastOut->id)->exists();

        return $repliedSince ? null : CarbonImmutable::instance($lastOut->created_at)->addDay()->setTimezone($now->getTimezone());
    }
}
