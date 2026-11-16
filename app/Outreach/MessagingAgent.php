<?php

namespace App\Outreach;

use App\Models\Conversation;
use App\Models\KnowledgeQuery;
use App\Models\Pledge;
use App\Models\Wedding;
use App\Outreach\Llm\LlmFactory;
use App\Outreach\Messaging\InboundMessage;
use App\Outreach\Messaging\MessagingFactory;
use App\Outreach\Messaging\NumberedOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp / SMS conversations. Opening messages come from the wedding's
 * templates (predictable, approved wording); free-form replies go to the LLM,
 * whose structured output is validated and registered by OutcomeRegistrar.
 */
class MessagingAgent
{
    public function __construct(
        private readonly MessagingFactory $messaging,
        private readonly LlmFactory $llm,
        private readonly OutcomeRegistrar $registrar,
        private readonly FollowUpEngine $followUps,
    ) {
    }

    /** Send the message we initiate. Caller has already passed ContactPolicy. */
    public function start(Pledge $pledge, string $purpose, string $channel = 'whatsapp'): Conversation
    {
        $config = $pledge->wedding->outreach();
        $lang = $config->language($pledge->language);
        $state = $pledge->outreachState();
        $renderer = new PromptRenderer($config);
        $provider = $this->messaging->make($config, $channel);

        $key = match (true) {
            $purpose === 'promise_due' => 'promise_reminder',
            $purpose === 'promise_passed' => 'promise_check',
            $purpose === 'initial' || $state === 'none' || $state === 'paid' => 'intro_'.$state,
            default => 'reminder',
        };
        $promise = $pledge->promises()->where('status', 'open')->latest('id')->first();
        $text = $renderer->message($key, $pledge, $lang, ['promised_date' => $renderer->date($promise?->promised_date?->toDateString(), $lang)]);
        $options = $this->optionsFor($state, $config, $lang);

        $to = Phone::normalize($pledge->pledger_phone, $config->get('compliance.country_code', '255'));
        $messageId = $provider->send($to, $text, $options);

        $conversation = $pledge->conversations()->create([
            'wedding_id' => $pledge->wedding_id,
            'channel' => $provider->channel(),
            'direction' => 'outbound',
            'purpose' => $purpose,
            'status' => 'open',
            'provider_ref' => $messageId,
            'started_at' => now(),
        ]);
        $conversation->addTurn('assistant', $text, ['template' => $key, 'options' => $options]);
        $pledge->forceFill(['last_contacted_at' => now()])->save();

        if ($state !== 'paid') {
            $this->followUps->scheduleNoReply($pledge, $config, $provider->channel());
        }

        return $conversation;
    }

    public function handleInbound(Wedding $wedding, InboundMessage $in): ?Conversation
    {
        $config = $wedding->outreach();
        $pledge = $this->findPledge($wedding, $in->from, $config);
        if (! $pledge) {
            Log::info('Outreach inbound from unknown number', ['wedding' => $wedding->id]);

            return null;
        }
        if (ContactPolicy::isOptedOut($wedding->id, $in->from)) {
            return null; // never contact again, not even to reply
        }

        $conversation = $pledge->conversations()->whereIn('channel', ['whatsapp', 'sms'])->where('status', 'open')->latest('id')->first()
            ?? $pledge->conversations()->create([
                'wedding_id' => $wedding->id, 'channel' => $in->channel, 'direction' => 'inbound',
                'purpose' => 'inbound', 'status' => 'open', 'started_at' => now(),
            ]);
        $conversation->addTurn('guest', $in->text, ['option_id' => $in->optionId, 'message_id' => $in->messageId]);
        $this->followUps->cancel($pledge, ['no_reply']);

        $lang = $config->language($pledge->language);
        $renderer = new PromptRenderer($config);
        $provider = $this->messaging->make($config, $conversation->channel);
        $send = function (string $text, array $payload = []) use ($provider, $in, $conversation) {
            $provider->send($in->from, $text, $payload['options'] ?? []);
            $conversation->addTurn('assistant', $text, $payload ?: null);
        };

        if ($this->isOptOutKeyword($in->text, $config)) {
            $send($renderer->message('opt_out_confirm', $pledge, $lang), ['intent' => 'opt_out']);
            $this->registrar->optOut($pledge, $conversation, 'Keyword: '.mb_substr($in->text, 0, 50));
            $conversation->update(['status' => 'opted_out', 'outcome' => 'opt_out', 'ended_at' => now()]);

            return $conversation;
        }

        $optionId = $in->optionId ?? NumberedOptions::match($in->text, $this->lastOptions($conversation));
        if ($optionId && $this->handleOption($optionId, $pledge, $conversation, $config, $renderer, $lang, $send)) {
            return $conversation;
        }

        $this->reply($pledge, $conversation, $config, $renderer, $lang, $send);

        return $conversation;
    }

    private function reply(Pledge $pledge, Conversation $conversation, OutreachConfig $config, PromptRenderer $renderer, string $lang, callable $send): void
    {
        $fallbackText = $renderer->message('fallback', $pledge, $lang);

        try {
            $raw = $this->llm->make($config)->complete($renderer->messagingSystemPrompt($pledge, $lang), $this->history($conversation));
            $r = AgentJson::messagingReply(AgentJson::decode($raw));
        } catch (\Throwable $e) {
            Log::warning('Outreach messaging LLM failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
            $send($fallbackText, ['error' => true]);
            $this->followUps->schedule($pledge, 'low_confidence', now(), null, 'The assistant could not answer the latest message; please reply personally.', $conversation->id);

            return;
        }

        if ($r['intent'] === 'opt_out') {
            $send($renderer->message('opt_out_confirm', $pledge, $lang), $r);
            $this->registrar->optOut($pledge, $conversation, 'Guest asked to stop');
            $conversation->update(['status' => 'opted_out', 'outcome' => 'opt_out', 'extracted' => $r, 'ended_at' => now()]);

            return;
        }

        $result = $this->registrar->register($conversation, [
            'outcome' => match (true) {
                $r['intent'] === 'already_paid' => 'already_paid',
                $r['next_follow_up_at'] !== null && $r['promised_date'] === null => 'callback',
                default => null,
            },
            'promised_date' => $r['promised_date'],
            'promised_amount' => null,
            'pledged_amount' => $r['pledged_amount'],
            'confidence' => 'high', // the guest typed it; the date is confirmed back to them
            'unanswered_questions' => $r['fallback_used'] && $r['question'] ? [$r['question']] : [],
            'fallback_text' => $r['reply_text'],
            'callback_time' => $r['next_follow_up_at'],
            'summary' => null,
            'raw_text' => $conversation->turns()->where('role', 'guest')->latest('id')->value('content'),
        ]);

        if ($r['question'] && ! $r['fallback_used']) {
            KnowledgeQuery::create([
                'wedding_id' => $pledge->wedding_id, 'pledge_id' => $pledge->id, 'conversation_id' => $conversation->id,
                'question' => $r['question'], 'answer_given' => $r['reply_text'], 'fell_back' => false,
            ]);
        }

        $text = $r['reply_text'];
        if ($result['promise_rejected'] === 'date_in_past') {
            $text = $renderer->message('ask_date', $pledge, $lang);
        }
        if ($text !== '') {
            $send($text, $r + ['promise_id' => $result['promise']?->id]);
        }
        $conversation->update(['extracted' => $r + ['promise_id' => $result['promise']?->id], 'outcome' => $result['promise'] ? 'promise' : $conversation->outcome]);
    }

    /** Buttons are handled without the LLM: the meaning of a tap is exact. */
    private function handleOption(string $id, Pledge $pledge, Conversation $conversation, OutreachConfig $config, PromptRenderer $renderer, string $lang, callable $send): bool
    {
        $tz = $config->timezone();
        $today = CarbonImmutable::now($tz)->startOfDay();

        if ($id === 'pledge:no') {
            $this->registrar->register($conversation, ['outcome' => 'refused', 'confidence' => 'high', 'summary' => 'Declined to pledge.'] + $this->blank());
            $send($renderer->message('thank_you_decline', $pledge, $lang), ['option' => $id]);
            $conversation->update(['status' => 'closed', 'outcome' => 'refused', 'ended_at' => now()]);

            return true;
        }
        if ($id === 'pledge:later') {
            $this->followUps->schedule($pledge, 'callback', now()->addDays((int) $config->get('pledge_rules.escalation_after_days', 3)), $conversation->channel, 'Asked to be contacted later.', $conversation->id);
            $send($renderer->message('ask_later', $pledge, $lang), ['option' => $id]);
            $conversation->update(['outcome' => 'callback']);

            return true;
        }
        if ($id === 'pledge:yes') {
            $send($renderer->message('ask_amount', $pledge, $lang), ['option' => $id]);

            return true;
        }
        if ($id === 'date:pick') {
            $send($renderer->message('ask_date', $pledge, $lang), ['option' => $id]);

            return true;
        }

        $weddingDate = $config->get('wedding.wedding_date');
        $date = match ($id) {
            'date:week' => $today->endOfWeek()->startOfDay(),
            'date:two_weeks' => $today->addDays(14),
            'date:before_wedding' => $weddingDate
                ? CarbonImmutable::parse($weddingDate, $tz)->subDays(max(1, (int) $config->get('pledge_rules.min_days_before_wedding_for_promise', 3)))
                : $today->addDays(30),
            default => null,
        };
        if (! $date) {
            return false;
        }
        if ($date->lt($today)) {
            $date = $today;
        }

        $result = $this->registrar->register($conversation, [
            'promised_date' => $date->toDateString(), 'confidence' => 'high', 'raw_text' => $id,
        ] + $this->blank());
        $send($renderer->message('thank_you', $pledge, $lang, ['promised_date' => $renderer->date($date->toDateString(), $lang)]), ['option' => $id, 'promise_id' => $result['promise']?->id]);
        $conversation->update(['outcome' => 'promise', 'extracted' => ['promised_date' => $date->toDateString(), 'option' => $id]]);

        return true;
    }

    private function blank(): array
    {
        return ['outcome' => null, 'promised_date' => null, 'promised_amount' => null, 'pledged_amount' => null,
            'confidence' => 'high', 'unanswered_questions' => [], 'callback_time' => null, 'summary' => null];
    }

    private function optionsFor(string $state, OutreachConfig $config, string $lang): array
    {
        $t = fn ($k) => $config->template($k, $lang);

        return match ($state) {
            'none' => [
                ['id' => 'pledge:yes', 'title' => $t('btn_pledge_yes')],
                ['id' => 'pledge:later', 'title' => $t('btn_pledge_later')],
                ['id' => 'pledge:no', 'title' => $t('btn_pledge_no')],
            ],
            'pledged', 'partial' => [
                ['id' => 'date:week', 'title' => $t('btn_this_week')],
                ['id' => 'date:two_weeks', 'title' => $t('btn_two_weeks')],
                ['id' => 'date:before_wedding', 'title' => $t('btn_before_wedding')],
                ['id' => 'date:pick', 'title' => $t('btn_pick_date')],
            ],
            default => [],
        };
    }

    private function lastOptions(Conversation $conversation): array
    {
        $turn = $conversation->turns()->where('role', 'assistant')->latest('id')->first();

        return $turn?->payload['options'] ?? [];
    }

    /** Conversation as LLM messages; the model must see a user turn first. */
    private function history(Conversation $conversation): array
    {
        $messages = $conversation->turns()->whereIn('role', ['assistant', 'guest'])->orderBy('id')->get()
            ->map(fn ($t) => ['role' => $t->role === 'guest' ? 'user' : 'assistant', 'content' => $t->content])->all();
        if (($messages[0]['role'] ?? 'user') === 'assistant') {
            array_unshift($messages, ['role' => 'user', 'content' => '(The assistant opened this conversation.)']);
        }

        return $messages;
    }

    private function isOptOutKeyword(string $text, OutreachConfig $config): bool
    {
        $clean = mb_strtoupper(trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $text)));
        foreach ($config->get('compliance.opt_out_keywords', []) as $keyword) {
            if ($clean === mb_strtoupper(trim($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function findPledge(Wedding $wedding, string $from, OutreachConfig $config): ?Pledge
    {
        $cc = $config->get('compliance.country_code', '255');

        return $wedding->pledges()->whereNotNull('pledger_phone')->where('status', '!=', 'cancelled')->latest('id')->get()
            ->first(fn (Pledge $p) => Phone::normalize($p->pledger_phone, $cc) === $from);
    }
}
