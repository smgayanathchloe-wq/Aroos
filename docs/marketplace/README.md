# Aroos Service Provider Marketplace

What is built (Steps 0–2 of `DESIGN.md`): provider onboarding, profiles, services, packages, portfolio (photos, video uploads, Instagram/TikTok/YouTube links), verification and admin review, the public `/service-providers` page, discovery and search, favourites, requests (enquiries), quotations and negotiation, booking confirmation with capacity checks, the provider calendar, cancellations, and notifications.

Not built yet (Steps 3–5): reviews and ratings, provider team invitations (the permission model exists; only the owner is created today), provider analytics beyond the dashboard counts, AI assistants, the mobile API, and payments/subscriptions/commission.

## Install / deploy

```bash
php artisan migrate                                   # additive: new tables only (+ notifications)
php artisan db:seed --class=MarketplaceSeeder         # 31 Tanzania regions + 19 starter categories (safe to re-run)
php artisan storage:link                              # portfolio images/videos are served from the public disk
```

Then:

- **Background jobs:** keep the scheduler running (`php artisan schedule:work`, or `* * * * * php artisan schedule:run` in cron). It runs `marketplace:housekeeping` hourly, which expires stale requests and offers and marks past bookings completed. Run `php artisan queue:work` so emails and SMS are sent; in-app notifications are written immediately.
- **Video uploads:** raise PHP `upload_max_filesize` and `post_max_size` (for example to 110M). The default on this server is 16M/32M, so 100 MB videos would be rejected before Laravel sees them.
- **Optional `.env`:** `MARKETPLACE_CURRENCY` (TZS), `MARKETPLACE_ENQUIRY_EXPIRY_DAYS` (14), `MARKETPLACE_QUOTE_VALIDITY_DAYS` (7), `MARKETPLACE_MAX_IMAGE_MB` (10), `MARKETPLACE_MAX_VIDEO_MB` (100), `MARKETPLACE_MAX_MEDIA` (200).
- **External services:** none new. Email uses the SMTP configured in Admin → Gateways, and SMS uses the existing SMS gateway.

## Business rules (enforced on the server)

1. **Listed = published + verified.** A provider appears in search and its profile is viewable only when the owner has published it **and** a super admin has verified it. Owners and admins see a preview of an unlisted profile.
2. **Only super admins grant verification** (Admin → Marketplace admin). Documents are stored on the private disk and can be downloaded only by the business owner and super admins. Every download is audit-logged.
3. **Ban.** An admin ban hides the provider everywhere, blocks all its actions (publishing, quoting, accepting) and keeps every request and booking for history. Lifting a ban sets the profile to hidden; the owner must publish again.
4. **Who acts for a wedding.** Anyone with access to the wedding (owner/planner, collaborators, couples) can send requests, negotiate, accept and cancel. Requests and bookings belong to the wedding, so the whole team sees them.
5. **Customer data.** A provider sees a wedding's name, phone, email and event details only on requests that wedding sent to it. Providers cannot browse or search customers.
6. **Provider contact visibility.** Phone/WhatsApp and email are either public or shown only after the viewer's wedding has sent a request (set by the provider).
7. **Prices, packages and media are optional.** A service can be "request a quotation" only. Publishing a profile needs at least one published service.
8. **Requests** need a published service. A package is optional. A double-submitted form creates one request.
9. **Negotiation history is permanent.** Every quotation and counter-offer is a new row. Earlier ones are only marked superseded, declined or expired; editing an amount or deleting a version throws.
10. **Explicit acceptance.** Only the wedding side can accept, only the provider's latest pending, unexpired offer, and only with the confirmation box ticked. Accepting does not take payment.
11. **No double booking.** Confirmation runs in one transaction that locks the provider row and re-checks:
    - blocked dates;
    - per-day capacity (venue 1, caterer N);
    - time-slot overlap including the buffer;
    - optionally capacity shared across all of the provider's services.
    Open quotations are shown on the calendar as tentative but never reserve capacity.
12. **Booking bridge.** A confirmed booking creates a "booked" entry in the wedding's own Vendors list at the agreed price, so Budget and Report include it. A cancellation marks that entry "rejected" and frees the date.
13. **Lifecycle.** A booking moves confirmed → completed (by the provider on or after the event date, or automatically by housekeeping) or confirmed → cancelled by either side (reason required). Every change goes into `booking_status_history`.
14. **Notifications.** In-app notifications cover:
    - new request, quote, counter-offer, message, decline or withdrawal;
    - booking confirmed or cancelled;
    - verification result or ban.

    Email goes out for the important events and SMS only for booking confirmed or cancelled. Each user gets each event once, and nobody is notified about their own action.

## Where things are

| Area | Path |
|---|---|
| Public page | `GET /service-providers` |
| Discovery | `/marketplace`, `/marketplace/providers/{slug}`, `/marketplace/saved` |
| Wedding side | `/weddings/{id}/services` (requests, bookings, compare) |
| Provider area | `/provider` (dashboard, requests, bookings, calendar, services, portfolio, profile, verification) |
| Admin | `/admin/marketplace`, `/admin/marketplace/categories` |
| Rules | `app/Marketplace/*` (`NegotiationService`, `BookingService`, `AvailabilityService`, `ProviderSearch`, `Notifier`) |
| Authorization | `app/Policies/Marketplace/*` |
| Tests | `tests/Feature/Marketplace/*` |
