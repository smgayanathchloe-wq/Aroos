<?php

namespace App\Outreach;

use App\Models\Wedding;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

/**
 * Per-wedding outreach configuration: safe defaults, overlaid with facts the
 * wedding record already holds, overlaid with what the couple saved.
 *
 * Swahili is the default language for every channel. Secrets (API keys,
 * tokens) live separately in the encrypted `outreach_secrets` column and fall
 * back to platform-wide values in config/outreach.php.
 */
class OutreachConfig
{
    public const LLM_PROVIDERS = ['anthropic', 'openai', 'azure_openai', 'self_hosted'];
    public const MESSAGING_PROVIDERS = ['whatsapp_cloud', 'twilio_whatsapp', 'sms'];
    public const LANGUAGES = ['sw', 'en'];

    public function __construct(public readonly array $data, private readonly array $secrets = [])
    {
    }

    public static function for(Wedding $wedding): self
    {
        $fromWedding = ['wedding' => array_filter([
            'couple_names' => $wedding->coupleNames(),
            'wedding_date' => $wedding->wedding_date?->toDateString(),
            'venue_name' => $wedding->venue_name ?? null,
        ])];

        $data = self::merge(self::merge(self::defaults(), $fromWedding), $wedding->outreach_config ?? []);

        return new self($data, $wedding->outreach_secrets ?? []);
    }

    /** Recursive merge where lists (faqs, keywords, hints) are replaced, not appended. */
    public static function merge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($value) && ! array_is_list($base[$key])) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /** The parts of $edited that differ from $base (lists compared whole). */
    public static function diff(array $base, array $edited): array
    {
        $out = [];
        foreach ($edited as $key => $value) {
            if (is_array($value) && ! array_is_list($value) && isset($base[$key]) && is_array($base[$key]) && ! array_is_list($base[$key])) {
                $sub = self::diff($base[$key], $value);
                if ($sub !== []) {
                    $out[$key] = $sub;
                }
            } elseif (! array_key_exists($key, $base) || $base[$key] !== $value) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    public function secret(string $key): ?string
    {
        return ($this->secrets[$key] ?? null) ?: config("outreach.secrets.$key");
    }

    public function language(?string $override = null): string
    {
        $lang = $override ?: $this->get('branding.language', 'sw');

        return in_array($lang, self::LANGUAGES, true) ? $lang : 'sw';
    }

    public function timezone(): string
    {
        return $this->get('wedding.timezone') ?: 'Africa/Dar_es_Salaam';
    }

    public function template(string $key, string $lang): string
    {
        return $this->get("message_templates.$lang.$key")
            ?? $this->get("message_templates.sw.$key")
            ?? self::defaults()['message_templates']['sw'][$key]
            ?? '';
    }

    /** Wedding facts + FAQs the agents are allowed to answer from. */
    public function knowledgeBase(): array
    {
        $w = $this->get('wedding', []);
        $facts = Arr::only($w, [
            'couple_names', 'wedding_date', 'venue_name', 'venue_address', 'google_maps_link',
            'dress_code', 'rsvp_deadline', 'gift_policy', 'registry_link', 'contact_person_name', 'contact_person_phone',
        ]);

        return [
            'facts' => array_filter($facts, fn ($v) => $v !== null && $v !== ''),
            'faqs' => array_values(array_filter($w['extra_faqs'] ?? [], fn ($f) => ! empty($f['q']) && ! empty($f['a']))),
        ];
    }

    /** @return array<string, array<int, string>> validation errors keyed by field */
    public static function validate(array $config): array
    {
        $v = Validator::make($config, [
            'enabled' => ['sometimes', 'boolean'],
            'wedding.wedding_date' => ['nullable', 'date_format:Y-m-d'],
            'wedding.rsvp_deadline' => ['nullable', 'date_format:Y-m-d'],
            'wedding.timezone' => ['nullable', 'timezone:all'],
            'wedding.google_maps_link' => ['nullable', 'url'],
            'wedding.registry_link' => ['nullable', 'url'],
            'wedding.extra_faqs' => ['sometimes', 'array'],
            'wedding.extra_faqs.*.q' => ['required', 'string', 'max:300'],
            'wedding.extra_faqs.*.a' => ['required', 'string', 'max:1000'],
            'branding.assistant_name' => ['sometimes', 'string', 'max:40'],
            'branding.language' => ['sometimes', 'in:'.implode(',', self::LANGUAGES)],
            'ai.provider' => ['sometimes', 'in:'.implode(',', self::LLM_PROVIDERS)],
            'ai.model' => ['sometimes', 'string', 'max:120'],
            'ai.base_url' => ['nullable', 'url'],
            'ai.temperature' => ['sometimes', 'numeric', 'between:0,1'],
            'ai.max_call_duration_sec' => ['sometimes', 'integer', 'between:60,900'],
            'voice.stt_language' => ['sometimes', 'string', 'max:10'],
            'voice.tts' => ['sometimes', 'in:twilio,http'],
            'voice.tts_url' => ['nullable', 'url', 'required_if:voice.tts,http'],
            'voice.retry_no_answer' => ['sometimes', 'integer', 'between:0,3'],
            'messaging.provider' => ['sometimes', 'in:'.implode(',', self::MESSAGING_PROVIDERS)],
            'pledge_rules.currency' => ['sometimes', 'string', 'max:8'],
            'pledge_rules.ask_if_no_pledge' => ['sometimes', 'boolean'],
            'pledge_rules.min_days_before_wedding_for_promise' => ['sometimes', 'integer', 'between:0,60'],
            'pledge_rules.timeline_hint_days' => ['sometimes', 'array', 'min:1'],
            'pledge_rules.timeline_hint_days.*' => ['integer', 'between:1,365'],
            'pledge_rules.escalation_after_days' => ['sometimes', 'integer', 'between:1,30'],
            'pledge_rules.max_follow_ups' => ['sometimes', 'integer', 'between:0,10'],
            'compliance.calling_hours_start' => ['sometimes', 'date_format:H:i'],
            'compliance.calling_hours_end' => ['sometimes', 'date_format:H:i'],
            'compliance.opt_out_keywords' => ['sometimes', 'array', 'min:1'],
            'compliance.opt_out_keywords.*' => ['string', 'max:30'],
            'compliance.consent_required' => ['sometimes', 'boolean'],
            'compliance.country_code' => ['sometimes', 'regex:/^\d{1,4}$/'],
            'message_templates' => ['sometimes', 'array'],
            'message_templates.*' => ['array'],
            'message_templates.*.*' => ['string', 'max:1000'],
        ]);

        $errors = $v->errors()->toArray();

        $start = Arr::get($config, 'compliance.calling_hours_start');
        $end = Arr::get($config, 'compliance.calling_hours_end');
        if ($start && $end && ! isset($errors['compliance.calling_hours_start']) && ! isset($errors['compliance.calling_hours_end']) && $start >= $end) {
            $errors['compliance.calling_hours_end'][] = 'Calling hours must end after they start.';
        }

        return $errors;
    }

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'wedding' => [
                'couple_names' => null,
                'wedding_date' => null,
                'timezone' => 'Africa/Dar_es_Salaam',
                'venue_name' => null,
                'venue_address' => null,
                'google_maps_link' => null,
                'dress_code' => null,
                'rsvp_deadline' => null,
                'gift_policy' => null,
                'registry_link' => null,
                'contact_person_name' => null,
                'contact_person_phone' => null,
                'extra_faqs' => [],
            ],
            'branding' => [
                'assistant_name' => 'Amani',
                'caller_id' => null,
                'org_name' => null,
                'language' => 'sw',
                'whatsapp_number' => null,
            ],
            'ai' => [
                'provider' => 'anthropic',
                'model' => 'claude-opus-5-5',
                'base_url' => null,          // openai / self_hosted (OpenAI-compatible) / azure endpoint
                'azure_api_version' => '2024-10-21',
                'temperature' => 0.4,        // ignored by models that reject sampling params
                'max_call_duration_sec' => 240,
            ],
            'voice' => [
                'provider' => 'twilio',
                'stt_language' => 'sw-TZ',   // speech-to-text language for guest replies
                'tts' => 'twilio',           // twilio <Say> or http (self-hosted Swahili TTS)
                'tts_url' => null,
                'twilio_voice' => null,      // optional Twilio voice name for <Say>
                'retry_no_answer' => 1,
                'call_paid_guests' => false, // true = thank-you call to fully paid pledgers
            ],
            'messaging' => [
                'provider' => 'whatsapp_cloud',
                'phone_number_id' => null,   // WhatsApp Cloud API phone number id
                'interactive' => true,
            ],
            'pledge_rules' => [
                'currency' => 'TZS',
                'ask_if_no_pledge' => true,
                'min_days_before_wedding_for_promise' => 3,
                'timeline_hint_days' => [7, 14, 30],
                'escalation_after_days' => 3,
                'max_follow_ups' => 2,
            ],
            'compliance' => [
                'calling_hours_start' => '09:00',
                'calling_hours_end' => '20:00',
                'opt_out_keywords' => ['STOP', 'UNSUBSCRIBE', 'ACHA', 'SITAKI'],
                'consent_required' => true,
                'country_code' => '255',
            ],
            'message_templates' => [
                'sw' => [
                    'intro_no_pledge' => 'Habari {{guest_name}}, mimi ni {{assistant_name}} kutoka {{org_name}}. Tunakukaribisha kwenye harusi ya {{couple_names}} tarehe {{wedding_date}}. Je, ungependa kutoa ahadi ya mchango?',
                    'intro_pledged' => 'Habari {{guest_name}}, mimi ni {{assistant_name}} kutoka {{org_name}}. Asante sana kwa ahadi yako ya {{pledge_amount}} kwa harusi ya {{couple_names}}. Unatarajia kukamilisha lini?',
                    'intro_partial' => 'Habari {{guest_name}}, mimi ni {{assistant_name}} kutoka {{org_name}}. Asante sana kwa {{amount_paid}} ulizotuma kwa harusi ya {{couple_names}}. Kiasi kilichobaki ni {{remaining_amount}}. Unatarajia kukamilisha lini? Hakuna haraka, ni kwa ajili ya mipango tu.',
                    'intro_paid' => 'Habari {{guest_name}}, mimi ni {{assistant_name}} kutoka {{org_name}}. Tumepokea mchango wako wote wa {{amount_paid}}. Asante sana kwa upendo wako kwa {{couple_names}}! Kama una swali lolote kuhusu harusi, niulize tu.',
                    'reminder' => 'Habari {{guest_name}}, ni {{assistant_name}} tena kutoka {{org_name}}. Nakukumbusha tu kwa upole kuhusu kiasi kilichobaki cha {{remaining_amount}}. Unatarajia kukamilisha lini?',
                    'promise_reminder' => 'Habari {{guest_name}}, nakukumbusha kwa upole kwamba ulipanga kukamilisha {{remaining_amount}} tarehe {{promised_date}}. Asante sana!',
                    'promise_check' => 'Habari {{guest_name}}, natumaini uko salama. Tarehe {{promised_date}} imepita — je, bado unapanga kukamilisha {{remaining_amount}}? Tarehe mpya yoyote ni sawa.',
                    'thank_you' => 'Asante sana {{guest_name}} — nimeandika {{promised_date}}. Tunashukuru sana!',
                    'thank_you_pledge' => 'Asante sana {{guest_name}} kwa ahadi yako ya {{pledge_amount}}. Mungu akubariki!',
                    'thank_you_decline' => 'Hakuna shida kabisa {{guest_name}}. Asante kwa muda wako, na karibu sana kwenye harusi.',
                    'opt_out_confirm' => 'Sawa {{guest_name}}, hatutakutumia ujumbe tena. Samahani kwa usumbufu.',
                    'fallback' => 'Swali zuri! Nitamwomba {{coordinator_name}} athibitishe na akurudie.',
                    'ask_later' => 'Sawa kabisa, nitakutafuta baadaye. Asante!',
                    'ask_date' => 'Sawa! Tafadhali niandikie tarehe unayotarajia, kwa mfano "tarehe 20".',
                    'ask_amount' => 'Asante sana! Ungependa kuahidi kiasi gani?',
                    'btn_pledge_yes' => 'Nataka kuahidi',
                    'btn_pledge_later' => 'Niulize baadaye',
                    'btn_pledge_no' => 'Siwezi',
                    'btn_this_week' => 'Wiki hii',
                    'btn_two_weeks' => 'Ndani ya wiki 2',
                    'btn_before_wedding' => 'Kabla ya harusi',
                    'btn_pick_date' => 'Chagua tarehe',
                    'voice_greeting' => 'Habari, mimi ni {{assistant_name}} kutoka {{org_name}}. Je, naongea na {{guest_name}}?',
                    'voice_voicemail' => 'Habari {{guest_name}}, mimi ni {{assistant_name}} kutoka {{org_name}} kuhusu harusi ya {{couple_names}}. Tutakupigia tena. Asante.',
                    'voice_goodbye' => 'Asante sana {{guest_name}}. Hongera tena kutoka kwetu sote. Siku njema.',
                    'voice_reprompt' => 'Samahani, sikusikia vizuri. Unaweza kurudia?',
                ],
                'en' => [
                    'intro_no_pledge' => 'Hi {{guest_name}}, this is {{assistant_name}} from {{org_name}}. You are warmly invited to {{couple_names}}\'s wedding on {{wedding_date}}. Would you like to make a pledge?',
                    'intro_pledged' => 'Hi {{guest_name}}, this is {{assistant_name}} from {{org_name}}. Thank you so much for pledging {{pledge_amount}} toward {{couple_names}}\'s wedding. When do you expect to complete it?',
                    'intro_partial' => 'Hi {{guest_name}}, this is {{assistant_name}} from {{org_name}}. Thank you so much for the {{amount_paid}} you\'ve sent toward {{couple_names}}\'s wedding. Just checking in on the remaining {{remaining_amount}} — when do you think you\'ll be able to complete it? No rush.',
                    'intro_paid' => 'Hi {{guest_name}}, this is {{assistant_name}} from {{org_name}}. We\'ve received your full contribution of {{amount_paid}}. Thank you so much! If you have any questions about the wedding, just ask.',
                    'reminder' => 'Hi {{guest_name}}, {{assistant_name}} again from {{org_name}}. A gentle reminder about the remaining {{remaining_amount}}. When do you expect to complete it?',
                    'promise_reminder' => 'Hi {{guest_name}}, a gentle reminder that you planned to complete {{remaining_amount}} by {{promised_date}}. Thank you!',
                    'promise_check' => 'Hi {{guest_name}}, hope you\'re well. {{promised_date}} has passed — are you still planning to complete the {{remaining_amount}}? Any new date is fine.',
                    'thank_you' => 'Thank you so much, {{guest_name}} — I\'ve noted {{promised_date}}. Really appreciate it!',
                    'thank_you_pledge' => 'Thank you so much, {{guest_name}}, for your pledge of {{pledge_amount}}!',
                    'thank_you_decline' => 'No problem at all, {{guest_name}}. Thank you for your time.',
                    'opt_out_confirm' => 'Understood, {{guest_name}}. We won\'t message you again. Sorry for the bother.',
                    'fallback' => 'Good question! Let me ask {{coordinator_name}} to confirm and get back to you.',
                    'ask_later' => 'Of course, I\'ll check back later. Thank you!',
                    'ask_date' => 'Great! Please type the date you expect, e.g. "the 20th".',
                    'ask_amount' => 'Thank you! How much would you like to pledge?',
                    'btn_pledge_yes' => 'I\'d like to pledge',
                    'btn_pledge_later' => 'Ask me later',
                    'btn_pledge_no' => 'I can\'t',
                    'btn_this_week' => 'This week',
                    'btn_two_weeks' => 'In 2 weeks',
                    'btn_before_wedding' => 'Before wedding',
                    'btn_pick_date' => 'Pick a date',
                    'voice_greeting' => 'Hello, this is {{assistant_name}} from {{org_name}}. Am I speaking with {{guest_name}}?',
                    'voice_voicemail' => 'Hello {{guest_name}}, this is {{assistant_name}} from {{org_name}} about {{couple_names}}\'s wedding. We\'ll call again. Thank you.',
                    'voice_goodbye' => 'Thank you, {{guest_name}}. Congratulations again from all of us. Have a lovely day.',
                    'voice_reprompt' => 'Sorry, I didn\'t catch that. Could you say it again?',
                ],
            ],
        ];
    }
}
