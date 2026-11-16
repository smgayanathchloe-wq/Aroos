# Deploying AI Pledge Outreach + Service Provider Marketplace

One checklist for both modules. Details live in `docs/ai-pledge-outreach.md` and `docs/marketplace/README.md`.

## 1. Code and database

```bash
composer install --no-dev --optimize-autoloader      # adds anthropic-ai/sdk
php artisan migrate --force                          # additive only; see rollback below
php artisan db:seed --class=MarketplaceSeeder --force  # 31 regions + 19 categories (safe to re-run)
php artisan storage:link                             # portfolio media on the public disk
php artisan optimize                                 # config/route/view caches (verified to work)
```

**Back up the database before migrating.** The migrations only add tables and columns. The one data change sets pledges with amount 0 from `pledged` to `none`; `migrate:rollback` reverses it.

## 2. Processes

| Process | Command | Why |
|---|---|---|
| Scheduler | `* * * * * php artisan schedule:run` | `outreach:tick` (every minute), `marketplace:housekeeping` (hourly) |
| Queue worker | `php artisan queue:work --tries=1` | outreach contacts, inbound WhatsApp, call wrap-up, marketplace email/SMS |

## 3. Web server / PHP

- `APP_URL` must be the public **https** URL. Twilio, Meta and ClickPesa signatures are computed over it. Behind a TLS-terminating proxy, configure Laravel trusted proxies for that proxy only.
- For 100 MB marketplace videos, set `upload_max_filesize=110M` and `post_max_size=120M`; the web server body limit (for example nginx `client_max_body_size`) must also allow it.

## 4. Environment

Everything is optional except what the feature you switch on needs.

| Feature | Variables |
|---|---|
| Outreach AI (per wedding, or platform default) | `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` / `AZURE_OPENAI_API_KEY` / `OUTREACH_LLM_API_KEY` (self-hosted Swahili model) |
| WhatsApp Cloud | `WHATSAPP_API_TOKEN`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN` |
| Twilio WhatsApp/SMS/voice | `TWILIO_SID`, `TWILIO_AUTH_TOKEN` |
| Self-hosted Swahili TTS | `OUTREACH_TTS_API_KEY` (URL is set per wedding) |
| ClickPesa | `CLICKPESA_CLIENT_ID`, `CLICKPESA_API_KEY`, `CLICKPESA_CHECKSUM_KEY` (enable checksums in the ClickPesa dashboard; no sandbox, so real money) |
| Marketplace AI (off by default) | `MARKETPLACE_AI_ENABLED=true`, `MARKETPLACE_AI_PROVIDER`, `MARKETPLACE_AI_MODEL`, `MARKETPLACE_AI_BASE_URL` |
| Marketplace limits | `MARKETPLACE_MAX_VIDEO_MB`, `MARKETPLACE_MAX_IMAGE_MB`, `MARKETPLACE_ENQUIRY_EXPIRY_DAYS`, `MARKETPLACE_QUOTE_VALIDITY_DAYS` |

Email uses the SMTP set in Admin → Gateways; SMS uses the existing gateway (Beem / Twilio / Africa's Talking).

## 5. Webhooks to register with providers

| Provider | URL |
|---|---|
| Meta WhatsApp Cloud | `https://<APP_URL>/webhooks/whatsapp` (GET verify + POST), subscribe to `messages` |
| Twilio messaging number | `https://<APP_URL>/webhooks/twilio/messaging` |
| ClickPesa | `https://<APP_URL>/webhooks/clickpesa` |

Twilio voice webhooks are generated per call.

## 6. After deploy: smoke test

1. Visit `/service-providers` while signed out: the page loads and lists no providers.
2. Admin → Marketplace admin: the queue loads. Admin → Marketplace admin → Categories shows 19 categories.
3. Register a test business. Admin verifies it. Search finds it.
4. On one wedding, Settings → AI Outreach: save the config and check the preview renders in Kiswahili.
5. `php artisan schedule:list` shows `outreach:tick` and `marketplace:housekeeping`.

## Not included

- Marketplace payments, subscriptions and commission.
- 360dialog.
- WhatsApp interactive buttons on Twilio (they are sent as numbered text).
