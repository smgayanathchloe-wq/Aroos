<?php

namespace App\Outreach;

use App\Jobs\FinalizeCall;
use App\Models\Conversation;
use App\Models\FollowUp;
use App\Models\Pledge;
use App\Outreach\Llm\LlmFactory;
use App\Outreach\Voice\VoiceFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Self-hosted voice agent. Each guest utterance arrives as text from the
 * telephony webhook; the wedding's model (e.g. a self-hosted Swahili model)
 * writes the next line; the call ends with a structured extraction that is
 * registered like any other outcome.
 *
 * State machine: queued -> dialing -> connected -> in_conversation -> extracting
 * -> registered -> done, or terminal no_answer | voicemail | failed | opted_out.
 */
class VoiceAgent
{
    private const MAX_EMPTY_TURNS = 2;

    public function __construct(
        private readonly VoiceFactory $voice,
        private readonly LlmFactory $llm,
        private readonly OutcomeRegistrar $registrar,
        private readonly FollowUpEngine $followUps,
    ) {
    }

    /** Place the call. Caller has already passed ContactPolicy. */
    public function start(Pledge $pledge, string $purpose): Conversation
    {
        $config = $pledge->wedding->outreach();
        $conversation = $pledge->conversations()->create([
            'wedding_id' => $pledge->wedding_id, 'channel' => 'voice', 'direction' => 'outbound',
            'purpose' => $purpose, 'status' => 'queued',
        ]);

        try {
            $callId = $this->voice->make($config)->dial(
                Phone::normalize($pledge->pledger_phone, $config->get('compliance.country_code', '255')),
                (string) $config->get('branding.caller_id'),
                route('webhooks.voice.answer', $conversation),
                route('webhooks.voice.status', $conversation),
                (int) $config->get('ai.max_call_duration_sec', 240) + 30,
            );
            $conversation->update(['status' => 'dialing', 'provider_ref' => $callId]);
        } catch (\Throwable $e) {
            Log::warning('Outreach call failed to start', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
            $conversation->update(['status' => 'failed', 'summary' => mb_substr($e->getMessage(), 0, 500), 'ended_at' => now()]);
        }
        $pledge->forceFill(['last_contacted_at' => now()])->save();

        return $conversation;
    }

    /** The guest (or a machine) picked up. Returns TwiML. */
    public function answered(Conversation $conversation, ?string $answeredBy): string
    {
        $pledge = $conversation->pledge;
        $config = $pledge->wedding->outreach();
        $lang = $config->language($pledge->language);
        $renderer = new PromptRenderer($config);
        $telephony = $this->voice->make($config);

        if ($answeredBy && str_starts_with($answeredBy, 'machine')) {
            $text = $renderer->message('voice_voicemail', $pledge, $lang);
            $conversation->addTurn('assistant', $text, ['voicemail' => true]);
            $conversation->update(['status' => 'voicemail', 'outcome' => 'voicemail', 'started_at' => now(), 'ended_at' => now()]);
            $this->scheduleRetry($pledge, $config, $conversation);

            return $telephony->speakAndHangUp($text);
        }

        $text = $renderer->message('voice_greeting', $pledge, $lang);
        $conversation->update(['status' => 'in_conversation', 'started_at' => now()]);
        $conversation->addTurn('assistant', $text);

        return $telephony->speakAndListen($text, route('webhooks.voice.turn', $conversation));
    }

    /** One guest utterance (already transcribed). Returns TwiML. */
    public function turn(Conversation $conversation, string $speech): string
    {
        $pledge = $conversation->pledge;
        $config = $pledge->wedding->outreach();
        $lang = $config->language($pledge->language);
        $renderer = new PromptRenderer($config);
        $telephony = $this->voice->make($config);
        $listenUrl = route('webhooks.voice.turn', $conversation);

        if ($conversation->isFinished()) {
            return $telephony->speakAndHangUp('');
        }

        $speech = trim($speech);
        if ($speech === '') {
            $empties = $conversation->turns()->where('role', 'system')->where('content', 'no_speech')->count();
            $conversation->addTurn('system', 'no_speech');
            if ($empties >= self::MAX_EMPTY_TURNS) {
                return $this->hangUp($conversation, $renderer->message('voice_goodbye', $pledge, $lang));
            }

            return $telephony->speakAndListen($renderer->message('voice_reprompt', $pledge, $lang), $listenUrl);
        }

        $conversation->addTurn('guest', $speech);

        $elapsed = $conversation->started_at ? $conversation->started_at->diffInSeconds(now()) : 0;
        if ($elapsed > (int) $config->get('ai.max_call_duration_sec', 240)) {
            return $this->hangUp($conversation, $renderer->message('voice_goodbye', $pledge, $lang));
        }

        try {
            $raw = $this->llm->make($config)->complete($renderer->voiceSystemPrompt($pledge, $lang), $this->history($conversation));
            $reply = AgentJson::voiceTurn(AgentJson::decode($raw));
        } catch (\Throwable $e) {
            Log::warning('Outreach voice LLM failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
            $reply = ['say' => '', 'end_call' => true];
        }

        if ($reply['say'] === '') {
            return $this->hangUp($conversation, $renderer->message('voice_goodbye', $pledge, $lang));
        }
        if ($reply['end_call']) {
            return $this->hangUp($conversation, $reply['say']);
        }
        $conversation->addTurn('assistant', $reply['say']);

        return $telephony->speakAndListen($reply['say'], $listenUrl);
    }

    /** Call lifecycle callback from the telephony provider. */
    public function status(Conversation $conversation, string $callStatus): void
    {
        $pledge = $conversation->pledge;
        $config = $pledge->wedding->outreach();

        match ($callStatus) {
            'ringing', 'initiated', 'queued' => $conversation->status === 'queued' ? $conversation->update(['status' => 'dialing']) : null,
            'in-progress' => in_array($conversation->status, ['queued', 'dialing'], true) ? $conversation->update(['status' => 'connected']) : null,
            'no-answer', 'busy' => $this->noAnswer($conversation, $pledge, $config),
            'failed', 'canceled' => $conversation->isFinished() ? null : $conversation->update(['status' => 'failed', 'ended_at' => now()]),
            'completed' => $this->completed($conversation),
            default => null,
        };
    }

    /** Post-call: extract the structured outcome and register it. Idempotent. */
    public function finalize(Conversation $conversation): void
    {
        $updated = Conversation::whereKey($conversation->id)->where('status', 'extracting')->update(['status' => 'registered']);
        if (! $updated) {
            return; // already finalized by another delivery
        }
        $conversation->refresh();
        $pledge = $conversation->pledge;
        $config = $pledge->wedding->outreach();
        $lang = $config->language($pledge->language);
        $renderer = new PromptRenderer($config);

        $transcript = $conversation->turns()->whereIn('role', ['assistant', 'guest'])->orderBy('id')->get()
            ->map(fn ($t) => ($t->role === 'guest' ? 'GUEST: ' : 'ASSISTANT: ').$t->content)->implode("\n");

        try {
            $raw = $this->llm->make($config)->complete(
                $renderer->callExtractionPrompt($pledge, $lang),
                [['role' => 'user', 'content' => "TRANSCRIPT\n".$transcript]],
                'medium',
            );
            $outcome = AgentJson::callOutcome(AgentJson::decode($raw));
        } catch (\Throwable $e) {
            Log::warning('Outreach call extraction failed', ['conversation' => $conversation->id, 'error' => $e->getMessage()]);
            $outcome = AgentJson::callOutcome(['summary' => 'Automatic extraction failed; please listen to or read the transcript.']);
        }

        $result = $this->registrar->register($conversation, $outcome + [
            'review_if_unsure' => true,
            'fallback_text' => $renderer->message('fallback', $pledge, $lang),
            'raw_text' => $transcript,
        ]);

        $conversation->update([
            'status' => $outcome['outcome'] === 'opt_out' ? 'opted_out' : 'done',
            'outcome' => $outcome['outcome'],
            'extracted' => $outcome + ['promise_id' => $result['promise']?->id, 'promise_rejected' => $result['promise_rejected']],
            'summary' => $outcome['summary'],
        ]);
    }

    private function hangUp(Conversation $conversation, string $text): string
    {
        $conversation->addTurn('assistant', $text, ['end_call' => true]);
        $this->beginExtraction($conversation);

        return $this->voice->make($conversation->pledge->wedding->outreach())->speakAndHangUp($text);
    }

    private function completed(Conversation $conversation): void
    {
        if (in_array($conversation->status, ['dialing', 'queued', 'connected'], true)) {
            $this->noAnswer($conversation, $conversation->pledge, $conversation->pledge->wedding->outreach());

            return;
        }
        $this->beginExtraction($conversation);
    }

    private function beginExtraction(Conversation $conversation): void
    {
        $moved = Conversation::whereKey($conversation->id)->whereIn('status', ['connected', 'in_conversation'])
            ->update(['status' => 'extracting', 'ended_at' => now()]);
        if ($moved) {
            FinalizeCall::dispatch($conversation->id)->afterResponse();
        }
    }

    private function noAnswer(Conversation $conversation, Pledge $pledge, OutreachConfig $config): void
    {
        if ($conversation->isFinished()) {
            return;
        }
        $conversation->update(['status' => 'no_answer', 'outcome' => 'no_answer', 'ended_at' => now()]);
        $this->scheduleRetry($pledge, $config, $conversation);
    }

    /** No answer / voicemail: retry next day within calling hours, up to voice.retry_no_answer times. */
    private function scheduleRetry(Pledge $pledge, OutreachConfig $config, Conversation $conversation): void
    {
        $retries = FollowUp::where('pledge_id', $pledge->id)->where('reason', 'no_answer_retry')->whereIn('status', ['done', 'skipped'])->count();
        if ($retries >= (int) $config->get('voice.retry_no_answer', 1)) {
            return;
        }
        $tomorrow = CarbonImmutable::now($config->timezone())->addDay()->startOfDay();
        $this->followUps->schedule($pledge, 'no_answer_retry', (new ContactPolicy())->nextWindow($config, $tomorrow), 'voice', null, $conversation->id);
    }

    private function history(Conversation $conversation): array
    {
        $messages = $conversation->turns()->whereIn('role', ['assistant', 'guest'])->orderBy('id')->get()
            ->map(fn ($t) => ['role' => $t->role === 'guest' ? 'user' : 'assistant', 'content' => $t->content])->all();
        if (($messages[0]['role'] ?? 'user') === 'assistant') {
            array_unshift($messages, ['role' => 'user', 'content' => '(Call connected. You greeted the guest first.)']);
        }

        return $messages;
    }
}
