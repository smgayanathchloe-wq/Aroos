# Moving Aroos (wedflow) to the cloud

Everything needed to stand the web app up on a new host, and how to get
into the admin area once it is running.

## Is it ready?

Yes. Verified by `./database/verify-rebuild.sh`, which builds a
throwaway database purely from migrations and seeders and checks the
result against the live one: **17 checks, all passing.**

| Area | State |
|---|---|
| Migrations | All 9 present. Every table except Laravel's own `migrations` is created by one. A fresh database rebuilds to 38 tables, 377 columns, 92 indexes, 36 foreign keys, identical to live. |
| Seeders | Production-safe. Seeds packages and one bootstrap admin from the environment. |
| Uploads | 4 files referenced by the database, all present. Verified by `database/check-uploads.php`. |
| Exports | All 7 print and import routes render against real data. |
| Dependencies | `composer audit` clean. |
| Background work | None. No queued jobs and no cron, so reminders send inline. |

## Deploying

```bash
# 1. Code and dependencies
git clone <repo> && cd wedflow
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 2. Configure
cp .env.example .env
php artisan key:generate
```

Then edit `.env`. These matter:

```ini
APP_ENV=production
APP_DEBUG=false          # critical: true leaks stack traces to visitors
APP_URL=https://your-domain

DB_CONNECTION=mysql
DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...

# The first admin. Used once, by the seeder, then ignored.
ADMIN_BOOTSTRAP_EMAIL=you@your-domain
ADMIN_BOOTSTRAP_PASSWORD=<a long random password>
```

```bash
# 3. Build the database
php artisan migrate --force --seed

# 4. Files and caches
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Permissions (adjust the user to match your web server)
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## Getting into the admin area

There is no separate admin login. Admin is a **role on a normal user**,
so you sign in at `/login` like anyone else and the admin menu appears.

1. Sign in at `https://your-domain/login` with the
   `ADMIN_BOOTSTRAP_EMAIL` and `ADMIN_BOOTSTRAP_PASSWORD` you set above.
2. **Change that password immediately** from your profile.
3. The admin area is then at:

| Page | Path | What it does |
|---|---|---|
| Analytics | `/admin/analytics` | Weddings, subscriptions, revenue by package |
| Per wedding | `/admin/analytics/wedding/{id}` | Guests, pledges, budget and storage for one wedding |
| Packages | `/admin/packages` | Subscription plans and pricing |
| Requests | `/admin/requests` | Enquiries from the public landing page |
| Settings | `/admin/settings` | SMS gateway and platform settings |

If you ever lose access, promote an existing user from the server:

```bash
php artisan tinker --execute="
  \$u = App\Models\User::where('email','you@your-domain')->firstOrFail();
  \$u->update(['role' => 'super_admin', 'is_admin' => true]);
  echo 'promoted';"
```

### Roles

| Role | Can |
|---|---|
| `super_admin` | Everything, including the admin area and every wedding |
| `planner` | Create and edit the weddings they own or collaborate on |
| `couple` | **View only.** This is enforced in the API, not just hidden in the UI |

## Bringing the real data across

Seeders contain only reference data. Couples, guests, pledges and photos
are real records and are **not** in source control.

### Database

```bash
# On the old server
mysqldump -u USER -p --single-transaction --quick --routines wedflow \
  | gzip > wedflow-data.sql.gz

# On the cloud host
gunzip < wedflow-data.sql.gz | mysql -h HOST -u USER -p wedflow
```

If you restore a full dump you do **not** also need `--seed`; the dump
already contains the reference rows and the users.

Do this during a maintenance window so nothing is written mid-dump.

### Uploaded files

`storage/app/public` holds couples' photos, memories and website
imagery. The `photos`, `memories`, `inspirations`, `weddings` and
`website_settings` tables store paths into it, so skipping this step
leaves broken images across the site.

```bash
# On the old server
tar czf wedflow-uploads.tar.gz storage/app/public

# On the cloud host, from the project root
tar xzf wedflow-uploads.tar.gz
php artisan storage:link
chown -R www-data:www-data storage/app/public
```

Verify every referenced file arrived:

```bash
php artisan tinker --execute="require 'database/check-uploads.php';"
```

It prints `CHECKED=<n> MISSING=0` when all is well, and names the
missing paths otherwise.

## Exports and imports

These all work and need nothing extra on the server; they are rendered
by the app, not by an external binary.

| Feature | Path |
|---|---|
| Guest list | `/weddings/{id}/guests/print` |
| Check-in sheet | `/weddings/{id}/checkin/print` |
| Seating plan | `/weddings/{id}/seating/print` |
| Pledge list | `/weddings/{id}/pledges/print` |
| Pledge report | `/weddings/{id}/pledges/report/print` |
| Pledge import | `/weddings/{id}/pledges/import` |
| Import template | `/weddings/{id}/pledges/import/template` (CSV) |

The print pages open a printable HTML view, so a browser's "Save as PDF"
produces the document. Nothing needs `wkhtmltopdf` or similar installed.

## After deploying

SMS is **off until you configure it.** With no gateway set, messages are
written to `storage/logs/laravel.log` rather than sent, and the mobile
app says so on its messaging screen. Configure Beem, Twilio or Africa's
Talking under **Settings** in the admin area.

Mail defaults to `log` too. Set `MAIL_MAILER` and the SMTP details in
`.env` before relying on email.

## Before every deployment

```bash
./database/verify-rebuild.sh
```

It builds a scratch database from migrations and seeders, compares it
column-by-column with live, signs in over real HTTP, loads every admin
page, confirms admin is closed to anonymous visitors and to non-admin
accounts, renders every export, checks uploads, and confirms re-seeding
does not duplicate anything. It never touches live data.

To include the export checks, give it the live admin password:

```bash
LIVE_ADMIN_PASSWORD='...' ./database/verify-rebuild.sh
```

Currently: **17 checks, all passing.**

## Notes for whoever maintains this

- **Every schema change is a migration.** Never alter a table by hand.
- The demo wedding is in `RoleUsersSeeder` and is deliberately *not* run
  by `db:seed`. It contains known passwords, so only use it in a test
  environment: `php artisan db:seed --class=RoleUsersSeeder`.
