# AI Pledge Outreach — runbook

The assistant (default name **Amani**) contacts pledgers on **WhatsApp, SMS or by phone, in Kiswahili**, reminds them of their pledge, asks when they expect to complete it, answers questions about the wedding from the wedding's knowledge base, and records every answer: promises, new pledges, opt-outs, unanswered questions and follow-ups.

- **Tenant** = a wedding. **Contact** = a row on the Pledges page. Amount **0** means "has not pledged yet". The assistant invites these people to pledge.
- **Payments** are always written to `contributions` through `PaymentRecorder`:
  - **ClickPesa**: the "ClickPesa" button on a pledge row sends a USSD push. The payment is recorded automatically by the webhook, and only when its checksum verifies.
  - **Committee entry**: cash, M-Pesa, bank and so on, entered on the Pledges page or from the mobile app.
  - **"I already paid"** from a guest never changes money. It creates a *Verify claimed payment* task for the committee.

## Add a new wedding (no code changes)

1. **Create the wedding** in the app as usual (couple, date, venue).
2. **Add pledgers** on *Pledges & Gifts*, by hand or by spreadsheet import. Each needs a phone number. Use amount 0 for people who have not pledged yet.
3. **Record consent.** Go to *AI Outreach*, select the pledgers who agreed to be contacted, and click **Mark consent**. Nobody is contacted without it while `compliance.consent_required` is true.
4. **Fill the config.** Go to *AI Outreach → Settings → Configuration* (JSON). Anything you leave out keeps its default or is taken from the wedding record.
   - `branding`: `assistant_name`, `org_name`, `caller_id`, `whatsapp_number`, and `language` (`sw` is the default).
   - `wedding`: `dress_code`, `venue_address`, `google_maps_link`, `gift_policy`, `contact_person_name` and `contact_person_phone`. `extra_faqs` holds the knowledge base.
   - `pledge_rules`, `compliance` (calling hours, opt-out words), `message_templates.sw` and `message_templates.en`.
   - The preview on the right shows the rendered messages and prompts for one of your pledgers.
5. **Add credentials** under *Settings → Credentials*. They are stored encrypted, and an empty field falls back to the platform `.env` value.
6. **Register numbers and webhooks** with the providers. The URLs are listed on the Settings page.
   - **WhatsApp Cloud (Meta):** set `messaging.provider = whatsapp_cloud` and `messaging.phone_number_id`. Webhook URL is `/webhooks/whatsapp`, verify token `WHATSAPP_VERIFY_TOKEN`, subscribe to `messages`. Secrets: `whatsapp_token` and `whatsapp_app_secret`.
   - **Twilio WhatsApp/SMS:** set `messaging.provider = twilio_whatsapp` and `branding.whatsapp_number`. Point the number's incoming-message webhook at `/webhooks/twilio/messaging`. Secrets: `twilio_sid` and `twilio_token`.
   - **Voice (Twilio):** set `branding.caller_id` to a Twilio voice number. Call webhooks are generated per call, so there is nothing to register.
   - **ClickPesa:** register `/webhooks/clickpesa` in the ClickPesa dashboard and **enable checksums**. Secrets: `clickpesa_client_id`, `clickpesa_api_key` and `clickpesa_checksum_key`. Events without a valid checksum are refused. ClickPesa has no sandbox, so every push moves real money.
7. **Switch it on** by setting `"enabled": true` and saving. Then use *WhatsApp now* or *Call now* on one pledger, or select several and **Launch** a campaign.

## Swahili model and voice

- **LLM** (`ai.provider`): `anthropic` (default `claude-opus-5-5`), `openai`, `azure_openai`, or `self_hosted`.
  - For **your own Swahili model**, set `"provider": "self_hosted"`, `"base_url": "https://<your-host>/v1"` and `"model": "<served model name>"`. The endpoint must speak the OpenAI Chat Completions API (vLLM, TGI, Ollama and LiteLLM all do). The optional bearer token is the `self_hosted_api_key` secret.
- **Prompts** require every reply in Kiswahili (or English when `branding.language = en`). The model must return JSON. Anything malformed is rejected in code and goes to a committee task.
- **The voice agent is hosted here.** Twilio dials and transcribes the guest (`voice.stt_language`, default `sw-TZ`). Our app asks the model for the next line and sends it back as TwiML.
  - **Speech output:** `voice.tts = twilio` uses Twilio `<Say>`. For natural Swahili, set `voice.tts = http` and `voice.tts_url` to your own TTS service. The contract is POST `{"text","language"}` → audio bytes (mp3 or wav). Its key is the `tts_api_key` secret.
  - **Check first:** confirm on your Twilio account that `sw-TZ` speech recognition works on your numbers before going live.
- **Public URL:** set `APP_URL` to the public HTTPS URL. Twilio and Meta signatures are computed over it. If the app sits behind a TLS-terminating proxy, configure Laravel's trusted proxies for that proxy, otherwise signature checks fail.

## Running it

- **Scheduler:** run `php artisan schedule:work`, or put `* * * * * php artisan schedule:run` in cron. It runs `outreach:tick` every minute.
- **Queue worker:** run `php artisan queue:work`. Inbound messages and scheduled contacts run on the queue.

### Rules enforced in code (`app/Outreach/ContactPolicy.php`)

- **Opt-out:** opt-out keywords (`STOP`, `ACHA`, `SITAKI`…) or a spoken or written request to stop put the number on a permanent do-not-contact list. Both channels honour it, and it cannot be edited.
- **Consent:** required before the first contact.
- **Calling hours:** applied in the wedding's timezone. A contact due outside them is moved to the next window.
- **Rate limits:** one call per pledger per day, and one WhatsApp/SMS we start per 24h unless the guest replied.
- **Paid pledgers:** a fully paid pledger gets one thank-you and is never asked for money again. A payment cancels every pending chase.
- **Promises:** with a promise date ahead, reminders are held. A reminder goes 2 days before the date and a gentle check-in the day after.
- **Follow-ups:** no reply after `escalation_after_days` sends one nudge, up to `max_follow_ups`. A missed call is retried the next day, up to `voice.retry_no_answer` times.

## Where to look

- **AI Outreach:** pledgers, consent, *Call/WhatsApp now*, and the campaign builder.
- **Follow-ups & questions:** committee tasks, scheduled contacts (cancellable), unanswered guest questions (answer once and it joins the FAQ), and the do-not-contact list.
- **Conversations:** the full transcript, the JSON the AI committed to, and the promise it created.
