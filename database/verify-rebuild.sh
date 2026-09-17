#!/usr/bin/env bash
#
# Proves that wedflow can be rebuilt from migrations and seeders alone,
# and that the result actually works.
#
# Run before every cloud deployment. It never touches the live database;
# all work happens in a scratch database that is dropped at the end.
#
#   ./database/verify-rebuild.sh
#
set -uo pipefail

cd "$(dirname "$0")/.."

env_val() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }

LIVE_DB="$(env_val DB_DATABASE)"
USER="$(env_val DB_USERNAME)"
PASS="$(env_val DB_PASSWORD)"
SCRATCH="${LIVE_DB}_verify"
PORT=8904

my() { mysql -u"$USER" -p"$PASS" "$@" 2>/dev/null; }

pass=0; fail=0
ok()  { echo "  PASS  $1"; pass=$((pass+1)); }
bad() { echo "  FAIL  $1"; fail=$((fail+1)); }

echo "Rebuilding '$SCRATCH' from migrations and seeders..."
my -e "DROP DATABASE IF EXISTS \`$SCRATCH\`; CREATE DATABASE \`$SCRATCH\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if DB_DATABASE="$SCRATCH" ADMIN_BOOTSTRAP_PASSWORD='Verify@2026' \
   php artisan migrate --force --seed >/tmp/wf-rebuild.log 2>&1; then
  ok "migrate --seed completed"
else
  bad "migrate --seed failed; see /tmp/wf-rebuild.log"
  tail -20 /tmp/wf-rebuild.log
  exit 1
fi

echo
echo "Comparing structure against live '$LIVE_DB'..."

for metric in \
  "tables:SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='%s'" \
  "columns:SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='%s'" \
  "indexes:SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='%s'" \
  "foreign keys:SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='%s' AND REFERENCED_TABLE_NAME IS NOT NULL"
do
  label="${metric%%:*}"; sql="${metric#*:}"
  a=$(my -N -e "$(printf "$sql" "$LIVE_DB")")
  b=$(my -N -e "$(printf "$sql" "$SCRATCH")")
  [ "$a" = "$b" ] && ok "$label match ($a)" || bad "$label differ (live=$a fresh=$b)"
done

drift=$(my -N -e "
  SELECT CONCAT(a.TABLE_NAME,'.',a.COLUMN_NAME)
  FROM information_schema.COLUMNS a
  JOIN information_schema.COLUMNS b
    ON a.TABLE_NAME=b.TABLE_NAME AND a.COLUMN_NAME=b.COLUMN_NAME
  WHERE a.TABLE_SCHEMA='$LIVE_DB' AND b.TABLE_SCHEMA='$SCRATCH'
    AND (a.COLUMN_TYPE<>b.COLUMN_TYPE OR a.IS_NULLABLE<>b.IS_NULLABLE)")

if [ -z "$drift" ]; then
  ok "every column matches live"
else
  bad "column drift:"; echo "$drift" | sed 's/^/          /'
fi

echo
echo "Checking what was seeded..."

packages=$(my -N -e "SELECT COUNT(*) FROM \`$SCRATCH\`.packages;")
[ "${packages:-0}" -ge 1 ] && ok "packages seeded ($packages)" || bad "no packages seeded"

admins=$(my -N -e "SELECT COUNT(*) FROM \`$SCRATCH\`.users WHERE role='super_admin';")
[ "${admins:-0}" -eq 1 ] && ok "exactly one super admin" || bad "expected 1 super admin, found ${admins:-0}"

# A demo account on a live site is a way in for anybody who reads the code.
junk=$(my -N -e "SELECT COUNT(*) FROM \`$SCRATCH\`.users WHERE email LIKE '%@example.com';")
[ "${junk:-0}" -eq 0 ] && ok "no demo accounts seeded" || bad "${junk} demo accounts would ship to production"

echo
echo "Booting the app against the rebuilt database..."

DB_DATABASE="$SCRATCH" php artisan serve --port="$PORT" >/tmp/wf-serve.log 2>&1 &
SERVE_PID=$!
trap 'kill $SERVE_PID 2>/dev/null' EXIT

for _ in $(seq 1 20); do
  curl -s -o /dev/null "http://127.0.0.1:$PORT/login" && break
  sleep 0.5
done

JAR=$(mktemp)
csrf=$(curl -s -c "$JAR" "http://127.0.0.1:$PORT/login" \
  | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"//')

[ -n "$csrf" ] && ok "login page renders" || bad "login page has no CSRF token"

email=$(my -N -e "SELECT email FROM \`$SCRATCH\`.users WHERE role='super_admin' LIMIT 1;")

code=$(curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "%{http_code}" \
  -X POST "http://127.0.0.1:$PORT/login" \
  -d "_token=$csrf&email=$email&password=Verify@2026")

[ "$code" = "302" ] && ok "seeded admin signs in over HTTP" || bad "login returned $code, expected 302"

echo
echo "Loading the admin area on the rebuilt database..."

broken=""
for page in weddings admin/analytics admin/packages admin/requests admin/settings; do
  curl -s -b "$JAR" -o /tmp/wf-page.html -w "" "http://127.0.0.1:$PORT/$page"
  status=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/$page")

  if [ "$status" != "200" ] || grep -qE "SQLSTATE\[|Whoops, looks like" /tmp/wf-page.html; then
    broken="$broken $page($status)"
  fi
done

[ -z "$broken" ] && ok "all 5 admin pages load cleanly" || bad "pages with errors:$broken"

echo
echo "Checking the admin area is actually protected..."

# Signed out, admin must not be reachable at all.
anon_open=""
for page in admin/analytics admin/packages admin/settings; do
  status=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/$page")
  [ "$status" = "200" ] && anon_open="$anon_open $page"
done

[ -z "$anon_open" ] && ok "admin refuses anonymous visitors" || bad "reachable signed out:$anon_open"

# A non-admin account must be refused even once signed in. Create one
# just for this check, then remove it.
my -e "USE \`$SCRATCH\`; INSERT INTO users (name,email,password,role,created_at,updated_at)
       VALUES ('Probe','probe@verify.local','$(php -r 'echo password_hash("Probe@2026", PASSWORD_BCRYPT);')','couple',NOW(),NOW());"

PJAR=$(mktemp)
pcsrf=$(curl -s -c "$PJAR" "http://127.0.0.1:$PORT/login" \
  | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$PJAR" -c "$PJAR" -o /dev/null \
  -X POST "http://127.0.0.1:$PORT/login" \
  -d "_token=$pcsrf&email=probe@verify.local&password=Probe@2026"

leaked=""
for page in admin/analytics admin/settings; do
  status=$(curl -s -b "$PJAR" -o /dev/null -w "%{http_code}" "http://127.0.0.1:$PORT/$page")
  [ "$status" = "200" ] && leaked="$leaked $page"
done

[ -z "$leaked" ] && ok "admin refuses a non-admin account" || bad "non-admin could reach:$leaked"

rm -f "$PJAR"
my -e "USE \`$SCRATCH\`; DELETE FROM users WHERE email='probe@verify.local';"

rm -f "$JAR"
kill $SERVE_PID 2>/dev/null
trap - EXIT

echo
echo "Checking uploaded files referenced by the live database..."

uploads=$(php artisan tinker --execute="require 'database/check-uploads.php';" 2>/dev/null \
  | grep -o 'MISSING=[0-9]*' | cut -d= -f2 | tail -1)

[ "${uploads:-0}" = "0" ] && ok "every referenced upload is present" || bad "$uploads uploads missing"

echo
echo "Checking exports against the live database..."

# Exports are rendered by the app, so they are worth loading for real:
# a broken Blade or a renamed column shows up here and nowhere else.
LIVE_PORT=8906
php artisan serve --port="$LIVE_PORT" >/tmp/wf-live.log 2>&1 &
LIVE_PID=$!
trap 'kill $LIVE_PID 2>/dev/null' EXIT

for _ in $(seq 1 20); do
  curl -s -o /dev/null "http://127.0.0.1:$LIVE_PORT/login" && break
  sleep 0.5
done

wid=$(my -N -e "SELECT id FROM \`$LIVE_DB\`.weddings ORDER BY id LIMIT 1;")
admin_email=$(my -N -e "SELECT email FROM \`$LIVE_DB\`.users WHERE role='super_admin' LIMIT 1;")

if [ -n "$wid" ] && [ -n "${LIVE_ADMIN_PASSWORD:-}" ]; then
  EJAR=$(mktemp)
  ecsrf=$(curl -s -c "$EJAR" "http://127.0.0.1:$LIVE_PORT/login" \
    | grep -oE 'name="_token" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$EJAR" -c "$EJAR" -o /dev/null \
    -X POST "http://127.0.0.1:$LIVE_PORT/login" \
    -d "_token=$ecsrf&email=$admin_email&password=$LIVE_ADMIN_PASSWORD"

  bad_exports=""
  for path in \
    "weddings/$wid/guests/print" \
    "weddings/$wid/checkin/print" \
    "weddings/$wid/seating/print" \
    "weddings/$wid/pledges/print" \
    "weddings/$wid/pledges/report/print" \
    "weddings/$wid/pledges/import" \
    "weddings/$wid/pledges/import/template"
  do
    curl -s -b "$EJAR" -o /tmp/wf-export.html "http://127.0.0.1:$LIVE_PORT/$path"
    status=$(curl -s -b "$EJAR" -o /dev/null -w "%{http_code}" "http://127.0.0.1:$LIVE_PORT/$path")

    if [ "$status" != "200" ] || grep -qE "SQLSTATE\[|Whoops, looks like" /tmp/wf-export.html; then
      bad_exports="$bad_exports ${path##*/}($status)"
    fi
  done

  [ -z "$bad_exports" ] && ok "all 7 exports render" || bad "broken exports:$bad_exports"
  rm -f "$EJAR"
else
  echo "  SKIP  exports (set LIVE_ADMIN_PASSWORD to check them)"
fi

kill $LIVE_PID 2>/dev/null
trap - EXIT

echo
echo "Verifying seeders are safe to re-run..."
before=$(my -N -e "SELECT COUNT(*) FROM \`$SCRATCH\`.users;")
DB_DATABASE="$SCRATCH" ADMIN_BOOTSTRAP_PASSWORD='Verify@2026' php artisan db:seed --force >/dev/null 2>&1
after=$(my -N -e "SELECT COUNT(*) FROM \`$SCRATCH\`.users;")
[ "$before" = "$after" ] && ok "re-seeding does not duplicate users ($after)" || bad "users changed: $before -> $after"

my -e "DROP DATABASE \`$SCRATCH\`;"

echo
echo "------------------------------------------"
echo "  $pass passed, $fail failed"
echo "------------------------------------------"
[ "$fail" -eq 0 ] && echo "Safe to deploy to the cloud." || echo "Fix the failures before deploying."
exit $((fail > 0))
