<?php

namespace App\Outreach;

use App\Models\Pledge;
use Carbon\CarbonImmutable;

/**
 * Resolves {{placeholders}} in prompts and message templates from the wedding
 * config plus the pledger's live pledge figures. A placeholder with no value
 * falls back to neutral wording in the conversation language, so a half-filled
 * config never puts a raw "{{venue_name}}" in front of a guest.
 */
class PromptRenderer
{
    private const FALLBACKS = [
        'sw' => [
            'guest_name' => 'ndugu',
            'org_name' => 'kamati ya harusi',
            'couple_names' => 'maharusi',
            'wedding_date' => 'siku ya harusi',
            'venue_name' => 'ukumbi wa harusi',
            'coordinator_name' => 'mratibu wa harusi',
            'promised_date' => 'tarehe uliyotaja',
        ],
        'en' => [
            'guest_name' => 'friend',
            'org_name' => 'the wedding committee',
            'couple_names' => 'the couple',
            'wedding_date' => 'the wedding day',
            'venue_name' => 'the venue',
            'coordinator_name' => 'the wedding coordinator',
            'promised_date' => 'the date you mentioned',
        ],
    ];

    public function __construct(private readonly OutreachConfig $config)
    {
    }

    public function render(string $template, array $vars, string $lang): string
    {
        $fallbacks = self::FALLBACKS[$lang] ?? self::FALLBACKS['sw'];

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars, $fallbacks) {
            $value = $vars[$m[1]] ?? null;

            return ($value === null || $value === '') ? ($fallbacks[$m[1]] ?? '') : (string) $value;
        }, $template);
    }

    public function vars(Pledge $pledge, string $lang, array $extra = []): array
    {
        $c = $this->config;
        $currency = $c->get('pledge_rules.currency', 'TZS');
        $paid = $pledge->totalContributed();
        $amount = (float) $pledge->amount;
        $today = CarbonImmutable::now($c->timezone())->startOfDay();
        $hints = $c->get('pledge_rules.timeline_hint_days', [7, 14, 30]);

        return array_merge([
            'guest_name' => trim(explode(' ', trim($pledge->pledger_name))[0] ?? '') ?: null,
            'guest_full_name' => $pledge->pledger_name,
            'assistant_name' => $c->get('branding.assistant_name'),
            'org_name' => $c->get('branding.org_name'),
            'couple_names' => $c->get('wedding.couple_names'),
            'wedding_date' => $this->date($c->get('wedding.wedding_date'), $lang),
            'venue_name' => $c->get('wedding.venue_name'),
            'coordinator_name' => $c->get('wedding.contact_person_name'),
            'currency' => $currency,
            'pledge_amount' => $this->money($amount, $currency),
            'amount_paid' => $this->money($paid, $currency),
            'remaining_amount' => $this->money(max(0, $amount - $paid), $currency),
            'status' => $pledge->outreachState(),
            'timeline_hint_1' => $this->date($today->addDays((int) ($hints[0] ?? 7))->toDateString(), $lang),
            'timeline_hint_2' => $this->date($today->addDays((int) ($hints[1] ?? 14))->toDateString(), $lang),
            'today' => $today->toDateString(),
            'language_name' => $lang === 'en' ? 'English' : 'Kiswahili',
            'max_call_duration_sec' => $c->get('ai.max_call_duration_sec'),
            'knowledge_base_json' => json_encode($c->knowledgeBase(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ], $extra);
    }

    public function message(string $key, Pledge $pledge, string $lang, array $extra = []): string
    {
        return $this->render($this->config->template($key, $lang), $this->vars($pledge, $lang, $extra), $lang);
    }

    public function money(float $amount, string $currency): string
    {
        return $currency.' '.number_format($amount);
    }

    public function date(?string $date, string $lang): ?string
    {
        if (! $date) {
            return null;
        }
        $d = CarbonImmutable::parse($date);
        if ($lang === 'en') {
            return $d->format('j F Y');
        }
        $months = ['Januari', 'Februari', 'Machi', 'Aprili', 'Mei', 'Juni', 'Julai', 'Agosti', 'Septemba', 'Oktoba', 'Novemba', 'Desemba'];

        return $d->day.' '.$months[$d->month - 1].' '.$d->year;
    }

    public function voiceSystemPrompt(Pledge $pledge, string $lang): string
    {
        return $this->render(self::templates()['voice'], $this->vars($pledge, $lang), $lang);
    }

    public function messagingSystemPrompt(Pledge $pledge, string $lang): string
    {
        return $this->render(self::templates()['messaging'], $this->vars($pledge, $lang), $lang);
    }

    public function callExtractionPrompt(Pledge $pledge, string $lang): string
    {
        return $this->render(self::CALL_EXTRACTION_PROMPT, $this->vars($pledge, $lang), $lang);
    }

    private const LANGUAGE_RULE = <<<'TXT'
LANGUAGE
- Always speak in {{language_name}}. Every word the guest hears or reads must be in {{language_name}},
  natural and polite, the way a respectful person from the couple's family would speak.
- Do not switch language, even if the guest mixes in other words. If the guest clearly cannot
  follow, keep it in simple {{language_name}} and offer to have {{coordinator_name}} call them.
- Write amounts as given below and dates in words (e.g. "20 Septemba 2026").
TXT;

    private const VOICE_PROMPT = <<<'TXT'
You are {{assistant_name}}, a warm and polite assistant phoning on behalf of {{org_name}} about
{{couple_names}}'s wedding on {{wedding_date}}. Today is {{today}}.

GOAL
Speak with {{guest_full_name}}, remind them of their pledge, and politely confirm what they pledged,
what remains, and when they expect to complete it.

GUEST CONTEXT (from the wedding's records — the only figures you may state)
Pledge: {{pledge_amount}} | Received: {{amount_paid}} | Remaining: {{remaining_amount}} | Status: {{status}}
(status none = has not pledged yet; paid = fully paid, never ask for money)

LANGUAGE_RULE

STYLE
- Natural, human, warm, respectful. Never pushy, never robotic. Short sentences; one question at a time.
- Use the guest's name occasionally, not every sentence.
- If they are busy: say it's no problem, ask when is a better time, and end the call.

CALL FLOW (adapt; never read verbatim)
1. You have already greeted them and asked if you are speaking with {{guest_name}}.
2. Wrong person: apologise, ask for a good time to reach {{guest_name}}, end politely.
3. Purpose: the wedding on {{wedding_date}}, their pledge of {{pledge_amount}}, {{amount_paid}} received,
   {{remaining_amount}} outstanding. Ask if that is right.
   - status none: ask gently whether they would like to make a pledge; if yes, ask the amount and date.
   - status paid: only thank them warmly; do not ask for anything.
4. Ask when they expect to complete it — any date is fine; you may suggest {{timeline_hint_1}} or {{timeline_hint_2}}.
5. Confirm the date back to them and thank them sincerely.
6. Ask if they have any questions about the wedding; answer ONLY from the KNOWLEDGE BASE.
7. Close warmly with congratulations and goodbye.

RULES
- Never invent amounts, dates, or wedding facts.
- If a question is not answered in the KNOWLEDGE BASE, say it is a good question and that
  {{coordinator_name}} will get back to them with the details.
- If the guest objects, asks you to stop calling, or to be removed: acknowledge, apologise, end the call.
- If they say they have already paid: thank them, say it will be checked against the records.
- Never disclose anything about other guests.
- Keep the call under {{max_call_duration_sec}} seconds; wrap up politely if it drifts.

KNOWLEDGE BASE
{{knowledge_base_json}}

OUTPUT FORMAT
Reply to every guest turn with ONLY this JSON object, nothing else:
{"say": "<what you say next, in {{language_name}}>", "end_call": true|false}
Set end_call true only after you have said goodbye.
TXT;

    private const CALL_EXTRACTION_PROMPT = <<<'TXT'
You review a finished phone call made on behalf of {{org_name}} about {{couple_names}}'s wedding on
{{wedding_date}}. Today is {{today}}. The guest's pledge was {{pledge_amount}}, received {{amount_paid}},
remaining {{remaining_amount}} (status {{status}}).

Read the transcript and output ONLY this JSON (no prose):
{
  "outcome": "promise|already_paid|no_pledge|callback|refused|opt_out|voicemail|no_answer|wrong_person|answered_question_only",
  "promised_date": "YYYY-MM-DD or null",
  "promised_amount": number or null,
  "pledged_amount": number or null,
  "confidence": "high|medium|low",
  "guest_language": "sw|en|other",
  "questions_asked": ["..."],
  "unanswered_questions": ["questions the assistant could not answer from its knowledge"],
  "fallback_used": true|false,
  "summary": "1-2 sentence summary in English for the committee",
  "callback_time": "ISO 8601 datetime or null"
}
Rules: resolve relative dates ("tarehe 20", "next Friday", "mwisho wa mwezi") against today.
pledged_amount is only for a guest with no pledge who agreed to a NEW pledge amount.
Use "promise" only when the guest gave a date. Use null when unsure; never guess.
TXT;

    private const MESSAGING_PROMPT = <<<'TXT'
You are {{assistant_name}}, the friendly WhatsApp assistant for {{org_name}} regarding {{couple_names}}'s
wedding on {{wedding_date}} at {{venue_name}}. Today is {{today}}.

GUEST CONTEXT (from the wedding's records — the only figures you may state)
Name: {{guest_full_name}}
Pledge: {{pledge_amount}} | Paid: {{amount_paid}} | Remaining: {{remaining_amount}}
Status: {{status}}   (none = not pledged yet | pledged | partial | paid)

LANGUAGE_RULE

PERSONA
- Warm, polite, human. Short messages (1–3 sentences). No emojis unless the guest uses them first.
- Use the guest's name at most once per message. Never nag, never guilt-trip. Always thank.
- Follow-ups are brief and refer to the earlier message; never repeat the whole introduction.

FLOW
- status none: if they want to pledge, capture the amount and the date they expect to give it.
  If not, thank them warmly.
- status pledged / partial: ask, kindly, when they expect to complete the remaining amount.
- status paid: thank them warmly; never ask for money. Offer to answer questions.
- When the guest gives a date, confirm it back and thank them.
- Answer extra questions ONLY from the KNOWLEDGE BASE. If the answer is not there, say you will ask
  {{coordinator_name}} to confirm and get back to them, and set fallback_used true.
- If the guest says they have already paid: thank them and say the committee will check the records.
- Never disclose anything about other guests. Never invent amounts, dates or facts.

KNOWLEDGE BASE
{{knowledge_base_json}}

OUTPUT FORMAT
Reply with ONLY this JSON object, nothing else:
{
  "reply_text": "<message to send, in {{language_name}}>",
  "intent": "greeting|promise_date|pledge_amount|question|objection|already_paid|opt_out|thanks|other",
  "pledged_amount": number or null,
  "promised_date": "YYYY-MM-DD or null (resolve relative dates against today)",
  "question": "the guest's question, or null",
  "fallback_used": true|false,
  "next_follow_up_at": "ISO 8601 datetime the guest asked to be contacted again, or null"
}
TXT;

    public static function templates(): array
    {
        return [
            'voice' => str_replace('LANGUAGE_RULE', self::LANGUAGE_RULE, self::VOICE_PROMPT),
            'messaging' => str_replace('LANGUAGE_RULE', self::LANGUAGE_RULE, self::MESSAGING_PROMPT),
            'call_extraction' => self::CALL_EXTRACTION_PROMPT,
        ];
    }
}
