# Aroos Service Provider Marketplace — Assessment & Technical Design

Status: **approved**. Decisions in §10. Steps 0–2 built (see README.md); Steps 3–5 not started.

---

## 1. Architecture assessment (Phase 1)

| Area | What exists | Consequence for the marketplace |
|---|---|---|
| Stack | Laravel 13, PHP 8.4, Blade + Tailwind (CDN config in `layouts/base.blade.php`), MySQL in production, SQLite in tests, `database` queue | Build server-rendered Blade pages in the same style. No SPA. |
| Tenancy | A **Wedding** is the tenant. `EnsureWeddingAccess` + `User::canAccessWedding/canEditWedding`. Mobile API v1 (Sanctum) picks the wedding via `X-Wedding-Id`. | The marketplace is **cross-tenant**: one provider serves many weddings, and one customer may plan several. It needs its own authorization (Laravel Policies). Wedding access is still used when a request is linked to a wedding. |
| Users & roles | One `users.role` string: `super_admin`, `planner`, `couple`. Couples are read-only on weddings. No permission package. | Do **not** add a `provider` role, because one account must be able to be customer and provider at once. A provider is a `ProviderProfile` owned by a user, with team members (same pattern as `Collaborator`). Admin rights reuse `super_admin` and the `super.admin` middleware. |
| Existing "vendors" | `vendors` = a private per-wedding vendor tracker (status researching → booked, price estimate, amount paid). Used by Budget and Report. | Not a marketplace. **Reuse it as the bridge.** A confirmed marketplace booking creates or links a `vendors` row (status `booked`, agreed price), so Budget, Report and the mobile app keep working unchanged. |
| Name collision | `packages` / `Package` = Aroos subscription plans for weddings. | New tables use `service_packages` / `ServicePackage`. |
| Uploads | Photos go to the `public` disk with per-wedding storage quotas (`StoragePlan`). Validation is `image|max:15360`. No private-file serving yet. | Portfolio goes on the `public` disk under `providers/{id}/`, with a per-provider quota hook. Verification documents go on the **`local` (private) disk**, served only through an authorized controller. |
| Notifications | `User` uses `Notifiable`, but no notifications table or notification classes exist. Mail uses admin-configured SMTP (`AdminController::applyMailConfig`). SMS uses `ReminderService::sendSms` (Beem / Twilio / Africa's Talking). | Add Laravel **database notifications** (in-app first), plus mail and SMS channels through the existing infrastructure, all gated by user preferences. WhatsApp to providers comes later: today's WhatsApp config is per wedding, not platform-wide. |
| AI | `app/Outreach/Llm` (just built) gives a pluggable `LlmProvider` (Anthropic / OpenAI / Azure / self-hosted), configured **per wedding**. | Extend `LlmFactory` with a **platform** config (`config/marketplace.php` + `.env`), so marketplace AI does not need a wedding. Every AI feature is optional and the marketplace works without it. |
| Payments | ClickPesa USSD push and webhook, recording into `contributions` (per wedding). | Not used in the first marketplace release. Bookings carry a `payment_status` field (`not_tracked` by default) so payments, deposits and commissions can be added later. |
| Landing page | `welcome.blade.php`, a static marketing page with anchor nav. | Add a nav item **Service Providers** linking to a new public marketing page `/service-providers` (no listings). |
| Localization | `config/app.php` is `en`. Wedding currency is per wedding (default TZS). No regions table. Prompts are already Swahili-ready. | `config/marketplace.php` holds currency (TZS) and a money helper. Seed the Tanzania regions (31) table. District and town are text for now. UI strings use `__()` so Kiswahili can be added. |
| Tests | PHPUnit feature tests with `RefreshDatabase` (95 passing). | Same style: one feature test file per workflow. |

**Risks and conflicts**

- The outreach module is **uncommitted** on `main`. Commit or branch it before marketplace work starts.
- Couples are read-only on weddings, but must be able to request quotes for their own wedding. Marketplace actions are therefore authorized on the user, not on `canEditWedding` (decision D1).
- The Tailwind CDN build has no component library. Shared Blade components (`x-mk.card`, `x-mk.badge`, `x-mk.price`, `x-mk.empty`) keep the UI consistent.

---

## 2. Data model (Phase 2)

All money is `decimal(14,2)` plus a `currency` char(3), defaulting to TZS. Soft deletes are used where history matters (profiles, services, packages, media). Every status change that matters is written to a history table.

```
service_categories        id, parent_id→self (subcategory), name, name_sw, slug UQ, description, image_path,
                          sort, is_active, scheduling_default (per_day|per_slot)
tz_regions                id, name UQ                                  (seeded: 31 regions)

provider_profiles         id, owner_user_id→users, slug UQ, business_name, business_type (individual|company),
                          description, logo_path, cover_path, primary_category_id→service_categories,
                          contact_name, phone, alt_phone, whatsapp, email, website, preferred_contact,
                          address, region_id→tz_regions, district, city, lat, lng, operating_hours JSON,
                          social_links JSON (validated https URLs, allow-listed hosts),
                          phone_visibility / email_visibility (public|after_enquiry|after_booking),
                          status (draft|pending_approval|active|hidden|suspended|rejected),   -- visibility
                          verification_status (none|submitted|under_review|verified|rejected), -- trust, admin-only
                          verified_at, featured_until NULL, is_sponsored bool,               -- future monetisation
                          rating_avg, rating_count (denormalised, recomputed from reviews),
                          timestamps, deleted_at
                          INDEX(status, primary_category_id), INDEX(region_id), FULLTEXT(business_name, description) [MySQL]
provider_category         provider_id, category_id   UQ(provider_id, category_id)
provider_members          id, provider_id, user_id, role (owner|staff), permissions JSON
                          (enquiries, respond, calendar, bookings, services, reports), status, UQ(provider_id,user_id)
provider_verifications    id, provider_id, submitted_by, registration_number, representative_name, notes,
                          status, reviewed_by, reviewed_at, rejection_reason, timestamps
verification_documents    id, verification_id, disk='local', path, original_name, mime, size   -- private disk only

provider_services         id, provider_id, category_id, title, slug, description, coverage_regions JSON,
                          duration_minutes, capacity_note, requirements, inclusions JSON, exclusions JSON,
                          pricing_type (fixed|packages|negotiable|quote_only), base_price NULL, is_negotiable,
                          scheduling (per_day|per_slot), capacity_per_day (default 1), buffer_minutes,
                          capacity_scope (service|provider), status (draft|published|archived), sort, deleted_at
                          INDEX(provider_id, status), INDEX(category_id, status)
service_packages          id, service_id, name, description, price NULL, duration_minutes, features JSON,
                          extras JSON [{name, price}], terms, deposit_percent NULL, cancellation_policy,
                          status (active|inactive|archived), sort, deleted_at
provider_media            id, provider_id, service_id NULL, package_id NULL, album, type (image|video|video_link),
                          path NULL, url NULL (YouTube/Vimeo/TikTok only), caption, sort,
                          moderation (visible|hidden_by_admin), deleted_at
provider_blackouts        id, provider_id, service_id NULL, starts_on, ends_on, note
provider_profile_views    provider_id, date, views          PK(provider_id, date)   -- daily aggregate, no PII

service_enquiries         id, reference UQ, customer_user_id, wedding_id NULL→weddings, provider_id, service_id NULL,
                          package_id NULL, event_name, event_date, event_start, event_end, venue, region_id,
                          guests NULL, budget NULL, requirements, preferred_contact,
                          status (open|info_requested|quoted|negotiating|accepted|declined|expired|cancelled),
                          contact_shared_at NULL, expires_at, last_activity_at, timestamps
                          INDEX(provider_id, status), INDEX(customer_user_id, status)
quotation_versions        id, enquiry_id, version_no, author_user_id, author_side (provider|customer),
                          amount, package_id NULL, proposed_date NULL, items JSON, terms, message, valid_until,
                          status (pending|superseded|accepted|declined|expired|withdrawn), created_at  -- INSERT-ONLY
                          UQ(enquiry_id, version_no)
enquiry_messages          id, enquiry_id, user_id, side, body, kind (message|info_request|meeting_request), created_at

service_bookings          id, reference UQ, enquiry_id UQ, accepted_version_id UQ→quotation_versions,
                          provider_id, service_id, package_id NULL, customer_user_id, wedding_id NULL, vendor_id NULL,
                          event_date, starts_at NULL, ends_at NULL, agreed_amount, currency, terms_snapshot JSON,
                          status (confirmed|completed|cancelled_by_customer|cancelled_by_provider|no_show),
                          payment_status (not_tracked), timestamps
                          INDEX(provider_id, event_date, status), INDEX(service_id, event_date, status)
booking_status_history    id, booking_id, from_status, to_status, user_id, reason, created_at

provider_reviews          id, booking_id UQ, provider_id, customer_user_id, overall, quality, communication,
                          professionalism, value, body, provider_response, responded_at,
                          status (published|reported|hidden), timestamps
favourite_providers       user_id, provider_id, created_at        PK(user_id, provider_id)
marketplace_reports       id, reporter_user_id, reportable_type/id (provider|review|media), reason, status, handled_by
notifications             Laravel standard (database channel)
notification_preferences  user_id, event, channels JSON          UQ(user_id, event)
marketplace_settings      reuse existing `settings` table with `marketplace.*` keys
                          (unverified_visible, enquiry_expiry_days, quote_validity_days, max_media_per_provider…)
```

Not created now (designed for later): `provider_subscriptions`, `booking_payments`, `commission_rules`, `lead_credits`. The columns `featured_until`, `is_sponsored` and `payment_status` are the hooks for them.

---

## 3. Permissions matrix

| Action | Guest | Wedding team (owner, planners, collaborators, couples) | Provider owner | Provider staff | Super admin |
|---|---|---|---|---|---|
| View `/service-providers` marketing page | ✓ | ✓ | ✓ | ✓ | ✓ |
| Browse / search / view **active** providers | — | ✓ | ✓ | ✓ | ✓ (all statuses) |
| Favourite, enquire, negotiate, accept, cancel | — | ✓ for weddings they can access (D1) | ✓ as a wedding member, never to their own business | — | view |
| Enquiries/bookings belong to a wedding | — | whole wedding team sees them | — | — | — |
| Edit profile, services, packages, media | — | — | ✓ | if `services` perm | moderate |
| See enquiries / respond / quote | — | — | ✓ | `enquiries` / `respond` perm | view |
| Calendar, blackouts, bookings | — | — | ✓ | `calendar` / `bookings` perm | view |
| Customer phone/email | — | — | only on enquiries sent to them (D3) | same, with `enquiries` permission | ✓ |
| Submit verification, upload documents | — | — | ✓ | — | review, download |
| Grant verification, approve/suspend, categories, featured | — | — | — | — | ✓ |
| Review | — | only the customer on a `completed` booking, once | respond only | respond if `respond` perm | moderate |

Enforced with Policies (`ProviderProfilePolicy`, `ServiceEnquiryPolicy`, `ServiceBookingPolicy`, `ReviewPolicy`) and scoped queries, never only in the UI. Owners cannot review themselves. Staff permissions are checked per action.

---

## 4. Workflows

### 4.1 Provider onboarding

`draft` → (owner submits for listing) → `pending_approval` → admin approves → `active` (listed). Admin can also reject (`rejected`) or `suspend`. The owner can hide the profile (`hidden`) at any time without losing history.

Verification runs separately: `none → submitted → under_review → verified | rejected`. If `marketplace.unverified_visible` is on, an active but unverified profile is listed with a "Not yet verified" label.

The checklist and completion % are computed from the data: business info, contact, ≥1 published service, ≥1 media item, packages or a pricing type, verification. Nothing is stored separately.

### 4.2 Enquiry → quotation → negotiation

```
customer creates enquiry (package optional)  ── status open, expires_at = now + enquiry_expiry_days
provider: request info | decline(reason) | message | quote v1 (provider)        → info_requested | declined | quoted
customer: counter v2 (customer) | accept v(provider) | decline | cancel         → negotiating | accepted | …
provider: counter v3 / "agree" = new provider version at the customer's amount  → negotiating
```

- Versions are **insert-only**. A new version marks the previous `pending` one `superseded`. Nothing is ever updated except `status`.
- **Only the customer can accept**, and only a provider-authored, non-expired, latest version. This makes "explicit acceptance" a single rule.
- Every version records user, timestamp, previous amount (from the prior version), proposed amount, message and status. That is the negotiation history.
- A scheduled job marks `valid_until` / `expires_at` past due as `expired`.

### 4.3 Booking confirmation (one DB transaction)

1. Lock the `provider_services` row (or every service of the provider when `capacity_scope = provider`) with `SELECT … FOR UPDATE`.
2. Re-read the version: still latest, `pending`, provider-authored, not expired, enquiry still open.
3. Capacity check:
   - `per_day`: confirmed bookings on `event_date` must be fewer than `capacity_per_day`, and the date must not be blacked out.
   - `per_slot`: no confirmed booking overlapping `[start − buffer, end + buffer]`.
4. Insert the booking: reference `AR-BK-YYMM-XXXX`, `accepted_version_id` (UQ prevents a double accept), terms snapshot. Mark the version `accepted` and the enquiry `accepted`. Write history.
5. If linked to a wedding, create or update a `vendors` row (`booked`, `price_estimate = agreed_amount`).
6. After commit: notify both parties.

A conflict returns a clear message and leaves the quote open. Open quotations are shown on the calendar as tentative but never consume capacity. No payment is created.

### 4.4 Booking lifecycle

`confirmed → completed` (provider marks it, or it is set automatically on the day after `event_date`). From `confirmed` it can also become `cancelled_by_customer` / `cancelled_by_provider` (reason required) or `no_show`. Every change is logged. Only `completed` makes a review possible.

---

## 5. Pages (Blade) and API

**Public:** `/service-providers` (marketing page: hero, categories with illustrative images, how it works for couples and for providers, why Aroos, FAQ, CTA). It shows no listings.

**Customer (`/marketplace`, signed in):**
- Home: categories plus the AI assistant box.
- `/marketplace/c/{category}`: results with filters (text, category, region, price range, pricing type, verified, rating, has packages/portfolio, available on date) and sorting.
- `/marketplace/p/{slug}`: profile with tabs (Overview, Portfolio, Services & Packages, Business info, Reviews, Availability).
- `/marketplace/requests`: my enquiries, quotes, bookings and favourites.
- `/marketplace/compare?enquiries[]=`: side-by-side quotes.

**Provider (`/provider`):**
- Onboarding wizard (6 steps plus checklist), then dashboard.
- Sections: Profile, Services, Packages, Portfolio, Enquiries (inbox and thread with the quote composer), Bookings, Calendar (month/week/day plus blackouts), Customers, Reviews, Team, Analytics, Notifications, Settings.

**Admin (`/admin/marketplace`):**
- Approvals and verification queue (document viewer).
- Providers (suspend/feature), categories, media and review moderation, reports, analytics.

**Mobile API (`/api/v1/marketplace/*`):** the same service classes, JSON responses following the `ApiController` conventions. Delivered after the web flows are stable (Phase 4b).

**Services layer** (shared by web and API):
- `ProviderSearch`
- `NegotiationService`
- `BookingService` (the transaction above)
- `AvailabilityService`
- `ProfileCompleteness`
- `MarketplaceNotifier`
- `MarketplaceAssistant` (AI)

---

## 6. Notifications

Laravel Notification classes are sent through `database`, plus `mail` and an `SmsChannel` that wraps `ReminderService::sendSms`, filtered by `notification_preferences`.

Defaults: in-app on; email on for new enquiry, quote, booking and cancellation; SMS on for booking confirmed or cancelled only.

Duplicates are suppressed with an idempotency key (`event + subject id + version`) checked against the notifications table.

Reminders before an event come from a daily scheduled command.

---

## 7. AI

Every AI feature is optional. It uses the existing `LlmProvider` with a platform config, and when the model is down the feature is hidden or degrades to a plain form.

1. **Request assistant / service assistant.** Free text goes in; the model extracts JSON (categories, budget, region, date, guests). That JSON drives **`ProviderSearch` against the database**, and the model only explains the results. It never names providers, prices or availability itself. The customer reviews and edits the drafted enquiry before sending it.
2. **Matching explanation.** A transparent score (category match, region, budget vs starting price, available on date, rating with review count, portfolio present). The "why recommended" bullets come from the score components, not from AI. Sponsored providers are labelled and never mixed into the organic ranking.
3. **Budget assistant.** Ranges are drawn from published package prices and accepted quotations, aggregated with a minimum sample size, and marked "estimate".
4. **Provider assistant.** Drafts descriptions, package text, quote messages, captions and enquiry summaries. The result always lands in an editable field.
5. **Portfolio AI** (categorisation, duplicate detection): deferred, opt-in.

---

## 8. Security and privacy

- Policies on every route model.
- `throttle` on enquiry, message and review creation.
- Form Requests for validation.
- An idempotency token on the enquiry and accept forms.
- Uploads:
  - Images are re-encoded (strips EXIF and GPS), mime-sniffed, and capped by size and count.
  - Video uploads are capped in size; links are preferred (D4).
  - Verification documents are PDF/JPG/PNG only, on the private disk, and downloaded through a policy-checked controller.
- Social and website links must be `https://` with an allow-listed host for social platforms, and are rendered `rel="nofollow noopener noreferrer"`.
- Contact details are revealed according to `*_visibility` and the enquiry stage.
- Admin actions are audit-logged.

---

## 9. Implementation sequence

| Step | Delivers | Notes |
|---|---|---|
| 0 | Commit/branch the outreach work. `config/marketplace.php`, regions seeder, category admin and seeder (19 categories from the brief). | — |
| 1 (Phase 3) | Provider onboarding, profile, services, packages, media, public marketing page, customer discovery, search, filters, profile view, favourites | Usable end to end without enquiries. |
| 2 (Phase 4) | Enquiries, quotation versions, negotiation, booking transaction, booking lifecycle, calendar and blackouts, notifications, vendor bridge | The core value. Concurrency tests. |
| 3 (Phase 5) | Reviews, compare view, provider analytics, team members, featured/sponsored labelling, AI assistants | — |
| 4 (Phase 6) | Admin approvals and verification, moderation, reports, marketplace analytics | Basic approval ships in Step 1 so providers can go live. |
| 5 | Mobile API v1 marketplace endpoints, docs, deployment notes | — |

Each step ships with feature tests and leaves existing wedding planning untouched.

---

## 10. Decisions (agreed 2026-11-16)

- **D1. Who acts for a wedding:** everyone with access to the wedding (owner/planner, collaborators, and couples, even though couples are read-only elsewhere) can send enquiries, negotiate, accept and cancel. Enquiries and bookings belong to the **wedding**, so the whole wedding team sees them.
- **D2. Listing requires verification.** A provider is listed only when it is published **and** verified by an admin. Admins can **ban** a provider (hidden everywhere, cannot publish, history kept).
- **D3. Customer data:** providers never browse customers. A provider sees a wedding's details (contact name, phone, email, event) only for an enquiry that wedding sent to that provider.
- **D4. Video:** Instagram, TikTok and YouTube links, plus direct uploads (mp4/webm/mov, size capped by `marketplace.max_video_mb`, default 100 MB; PHP `upload_max_filesize`/`post_max_size` must allow it).
- **D5. Scope:** Steps 0–2 first, then review.
