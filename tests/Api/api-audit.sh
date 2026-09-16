#!/bin/bash
# Aroos mobile API audit.
#
# Checks the three roles against the live database: wedding tenancy, the
# couple's view-only rule, and the SMS/email delivery path.
set -u
BASE=http://127.0.0.1:8877/api/v1
DB="mysql -h127.0.0.1 -uspacedb -pspacedb wedflow -N -B"
ok=0; bad=0

check () { if [ "$2" == "$3" ]; then echo "PASS  $1"; ok=$((ok+1)); else echo "FAIL  $1: expected $2, got $3"; bad=$((bad+1)); fi; }
note () { echo "  $*"; }

login () { # email password -> token
  curl -s -X POST "$BASE/auth/login" -H "Accept: application/json" \
    -H "Content-Type: application/json" -d "{\"email\":\"$1\",\"password\":\"$2\"}" \
  | python3 -c "import json,sys
try:
  d=json.load(sys.stdin); print(d['data']['token'] if d.get('success') else '')
except Exception: print('')"
}

req () { # token wedding method path [body] -> status
  local tok=$1 wed=$2 method=$3 path=$4 body=${5:-} code
  if [ -n "$body" ]; then
    code=$(curl -s -o /tmp/wf.json -w "%{http_code}" -X "$method" "$BASE$path" \
      -H "Authorization: Bearer $tok" -H "Accept: application/json" \
      -H "X-Wedding-Id: $wed" -H "Content-Type: application/json" -d "$body")
  else
    code=$(curl -s -o /tmp/wf.json -w "%{http_code}" -X "$method" "$BASE$path" \
      -H "Authorization: Bearer $tok" -H "Accept: application/json" -H "X-Wedding-Id: $wed")
  fi
  echo "$code"
}

field () { python3 -c "import json
d=json.load(open('/tmp/wf.json'))
for k in '$1'.split('.'): d=d[k]
print(d)" 2>/dev/null; }

count () { curl -s "$BASE/$3" -H "Authorization: Bearer $1" -H "Accept: application/json" \
  -H "X-Wedding-Id: $2" | python3 -c "import json,sys
try:
  d=json.load(sys.stdin); print(len(d['data']) if isinstance(d.get('data'),list) else 'n/a')
except Exception: print('ERR')"; }

echo "=== sign in as each role ==="
ADMIN=$(login admin@wed.letsventure.co.tz 'Admin@2026')
PLANNER=$(login planner@wed.letsventure.co.tz 'Planner@2026')
COUPLE=$(login couple@wed.letsventure.co.tz 'Couple@2026')

for pair in "super_admin:$ADMIN" "planner:$PLANNER" "couple:$COUPLE"; do
  name=${pair%%:*}; tok=${pair#*:}
  if [ -n "$tok" ]; then echo "  $name: ok"; else echo "  $name: LOGIN FAILED"; bad=$((bad+1)); fi
done

echo
echo "=== bad credentials are refused ==="
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/auth/login" -H "Accept: application/json" \
  -H "Content-Type: application/json" -d '{"email":"admin@wed.letsventure.co.tz","password":"wrong"}')
check "wrong password rejected" 422 "$code"

code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/dashboard" -H "Accept: application/json")
check "anonymous access rejected" 401 "$code"

echo
echo "=== which weddings does each role see? ==="
W_ALL=$($DB -e "select count(*) from weddings" 2>/dev/null)
W_PLANNER=$($DB -e "select id from weddings where user_id=3 limit 1" 2>/dev/null)
W_OTHER=$($DB -e "select id from weddings where user_id<>3 limit 1" 2>/dev/null)
note "weddings in db: $W_ALL | planner owns: $W_PLANNER | other: $W_OTHER"

req "$ADMIN" "$W_PLANNER" GET /auth/me >/dev/null
check "super admin sees every wedding" "$W_ALL" "$(python3 -c "import json;print(len(json.load(open('/tmp/wf.json'))['data']['weddings']))" 2>/dev/null)"

req "$PLANNER" "$W_PLANNER" GET /auth/me >/dev/null
note "planner weddings: $(python3 -c "import json;print([w['id'] for w in json.load(open('/tmp/wf.json'))['data']['weddings']])" 2>/dev/null)"

echo
echo "=== tenancy: a wedding you do not own is refused ==="
check "planner blocked from another wedding" 403 "$(req "$PLANNER" "$W_OTHER" GET /dashboard)"
check "planner allowed on their own" 200 "$(req "$PLANNER" "$W_PLANNER" GET /dashboard)"
check "unknown wedding id is 404" 404 "$(req "$ADMIN" 999999 GET /dashboard)"

echo
echo "=== dashboard figures match the database ==="
G=$($DB -e "select count(*) from guests where wedding_id=$W_PLANNER" 2>/dev/null)
P=$($DB -e "select count(*) from pledges where wedding_id=$W_PLANNER" 2>/dev/null)
req "$PLANNER" "$W_PLANNER" GET /dashboard >/dev/null
check "guest total matches sql" "$G" "$(field data.guests.total)"
check "pledge count matches sql" "$P" "$(field data.pledges.count)"
note "countdown: $(field data.countdown.label)"

echo
echo "=== listings are wedding scoped ==="
check "guests list scoped" "$G" "$(count "$PLANNER" "$W_PLANNER" guests)"
LEAK=$($DB -e "select count(*) from guests where wedding_id<>$W_PLANNER" 2>/dev/null)
note "guests belonging to other weddings: $LEAK (must not appear above)"

echo
echo "=== couples are view-only (matches EnsureWeddingAccess) ==="
W_COUPLE=$(curl -s "$BASE/auth/me" -H "Authorization: Bearer $COUPLE" -H "Accept: application/json" \
  | python3 -c "import json,sys
d=json.load(sys.stdin)['data']['weddings']
print(d[0]['id'] if d else '')" 2>/dev/null)

if [ -n "$W_COUPLE" ]; then
  check "couple can read the dashboard" 200 "$(req "$COUPLE" "$W_COUPLE" GET /dashboard)"
  check "couple cannot add a guest" 403 "$(req "$COUPLE" "$W_COUPLE" POST /guests '{"first_name":"Blocked"}')"
  check "couple cannot add a vendor" 403 "$(req "$COUPLE" "$W_COUPLE" POST /vendors '{"name":"Blocked"}')"
  check "couple cannot send a reminder" 403 "$(req "$COUPLE" "$W_COUPLE" POST /reminders/quick-send '{"channel":"sms","name":"x","phone":"+255700000000","body":"hi"}')"
  req "$COUPLE" "$W_COUPLE" GET /auth/me >/dev/null
  check "couple reports can_edit=false" "False" "$(field data.user.can_edit)"
else
  note "couple has no wedding linked; skipping view-only checks"
fi

echo
echo "=== an editor can write (super admin; the seeded planner is role=couple) ==="
code=$(req "$ADMIN" "$W_PLANNER" POST /guests '{"first_name":"Audit","last_name":"Guest","party_count":2,"rsvp_status":"pending"}')
check "editor can add a guest" 200 "$code"
GID=$(field data.id)

if [ -n "${GID:-}" ]; then
  check "rsvp recorded" 200 "$(req "$ADMIN" "$W_PLANNER" POST "/guests/$GID/rsvp" '{"rsvp_status":"attending","party_count":2}')"
  check "rsvp status persisted" "attending" "$(field data.rsvp_status)"
  check "check-in works" 200 "$(req "$ADMIN" "$W_PLANNER" POST "/guests/$GID/checkin" '{"count":2}')"
  check "guest marked checked in" "True" "$(field data.checked_in)"
  check "second check-in is idempotent" 200 "$(req "$ADMIN" "$W_PLANNER" POST "/guests/$GID/checkin" '{}')"
  check "idempotent flag returned" "True" "$(field data.already_checked_in)"
  req "$ADMIN" "$W_PLANNER" DELETE "/guests/$GID" >/dev/null
fi

echo
check "seeded planner account is view-only (role=couple)" 403 "$(req "$PLANNER" "$W_PLANNER" POST /vendors '{"name":"Blocked"}')"

echo "=== messaging: channels, preview and real delivery ==="
req "$ADMIN" "$W_PLANNER" GET /reminders/channels >/dev/null
note "sms driver: $(field data.sms.driver) configured: $(field data.sms.configured)"
note "mail driver: $(field data.email.driver)"

code=$(req "$ADMIN" "$W_PLANNER" POST /reminders '{"name":"Audit reminder","channel":"sms","audience":"guests","body":"Hello {name}, join {couple} on {date}."}')
check "reminder created" 200 "$code"
RID=$(field data.id)

if [ -n "${RID:-}" ]; then
  check "preview resolves recipients" 200 "$(req "$ADMIN" "$W_PLANNER" GET "/reminders/$RID/preview")"
  note "recipients: $(field data.total_recipients) sms-reachable: $(field data.reachable.sms)"
  SAMPLE=$(python3 -c "import json
d=json.load(open('/tmp/wf.json'))['data']['samples']
print(d[0]['body'] if d else '')" 2>/dev/null)
  note "sample body: $SAMPLE"
  # Placeholders must be interpolated, not left raw.
  case "$SAMPLE" in *"{name}"*) check "placeholders interpolated" y n ;; *) check "placeholders interpolated" y y ;; esac

  check "reminder sent" 200 "$(req "$ADMIN" "$W_PLANNER" POST "/reminders/$RID/send" '{}')"
  note "sent: $(field data.sent) failed: $(field data.failed)"

  LOGGED=$($DB -e "select count(*) from reminder_logs where reminder_id=$RID" 2>/dev/null)
  check "delivery was logged" "yes" "$([ "$LOGGED" -gt 0 ] && echo yes || echo no)"

  check "a sent reminder cannot be edited" 422 "$(req "$ADMIN" "$W_PLANNER" PUT "/reminders/$RID" '{"body":"changed"}')"
  check "a sent reminder cannot be resent" 422 "$(req "$ADMIN" "$W_PLANNER" POST "/reminders/$RID/send" '{}')"

  $DB -e "delete from reminder_logs where reminder_id=$RID; delete from reminders where id=$RID;" 2>/dev/null
fi

echo
echo "=== direct message (the common case from a phone) ==="
code=$(req "$ADMIN" "$W_PLANNER" POST /reminders/quick-send '{"channel":"email","name":"Audit Person","email":"audit@example.com","subject":"Hello","body":"Test message from the audit."}')
check "quick send accepted" 200 "$code"
note "sent: $(field data.sent) failed: $(field data.failed)"
$DB -e "delete from reminder_logs where reminder_id in (select id from reminders where name like 'Direct message to Audit%'); delete from reminders where name like 'Direct message to Audit%';" 2>/dev/null

echo
echo "=== validation ==="
check "guest needs a first name" 422 "$(req "$ADMIN" "$W_PLANNER" POST /guests '{"last_name":"NoFirst"}')"
check "sms quick-send needs a phone" 422 "$(req "$ADMIN" "$W_PLANNER" POST /reminders/quick-send '{"channel":"sms","name":"x","body":"hi"}')"
check "reminder needs a valid channel" 422 "$(req "$ADMIN" "$W_PLANNER" POST /reminders '{"name":"x","channel":"pigeon","audience":"guests","body":"hi"}')"

echo
echo "=== $ok passed, $bad failed ==="
[ "$bad" -eq 0 ]
