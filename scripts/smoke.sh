#!/usr/bin/env bash
# End-to-end smoke test: sends sample messages to the API and verifies in Mailpit
# that each one produced an email to the expected department with the right Reply-To.
#
# Usage: scripts/smoke.sh [API_URL] [MAILPIT_URL]
set -euo pipefail

API_URL="${1:-http://localhost:8080}"
MAILPIT_URL="${2:-http://localhost:8025}"
SENDER="smoke.test@example.com"

# message|department a human would expect (reported as a hint, not asserted)
CASES=(
  "Nie działa mi komputer, nie włącza się od rana.|help-desk@example.com"
  "Zapomniałem hasła do systemu i nie mogę się zalogować.|help-desk@example.com"
  "Chciałbym zgłosić urlop na jutro.|kadry@example.com"
  "Potrzebuję zaświadczenia o zatrudnieniu do banku.|kadry@example.com"
  "Nie działa Wi-Fi na całym drugim piętrze.|it@example.com"
  "Dostałem podejrzany mail z linkiem, chyba phishing.|it@example.com"
  "Chciałbym polecić znajomego do rekrutacji na stanowisko testera.|human-resources@example.com"
  "Mam konflikt z przełożonym i chciałbym porozmawiać o tym z kimś.|human-resources@example.com"
  "Gdzie mogę zaparkować rower?|other@example.com"
  "Hello, is this the right place to ask about the company Christmas party?|other@example.com"
)

json() { python3 -c "import json,sys; d=json.load(sys.stdin); print(eval(sys.argv[1]))" "$1"; }

pass=0; fail=0; fallback=0; total_ms=0

for case in "${CASES[@]}"; do
  message="${case%%|*}"
  expected="${case##*|}"

  before=$(curl -fsS "$MAILPIT_URL/api/v1/messages?limit=1" | json 'd["total"]')
  payload=$(python3 -c 'import json,sys; print(json.dumps({"email": sys.argv[1], "message": sys.argv[2]}))' "$SENDER" "$message")

  start=$(date +%s%3N)
  response=$(curl -sS -X POST "$API_URL/api/v1/messages" -H 'Content-Type: application/json' -H 'Accept: application/json' -d "$payload")
  elapsed=$(( $(date +%s%3N) - start ))
  total_ms=$(( total_ms + elapsed ))

  department=$(printf '%s' "$response" | json 'd.get("data",{}).get("department","")' 2>/dev/null || true)
  routed_by=$(printf '%s' "$response" | json 'd.get("data",{}).get("routed_by","")' 2>/dev/null || true)

  if [ -z "$department" ]; then
    echo "FAIL  ${elapsed}ms  no department in response: $response"
    fail=$((fail+1)); continue
  fi

  latest=$(curl -fsS "$MAILPIT_URL/api/v1/messages?limit=1")
  mail_to=$(printf '%s' "$latest" | json 'd["messages"][0]["To"][0]["Address"]')
  mail_reply_to=$(printf '%s' "$latest" | json '(d["messages"][0].get("ReplyTo") or [{"Address": ""}])[0]["Address"]')
  after=$(printf '%s' "$latest" | json 'd["total"]')

  status="ok"
  [ "$after" -gt "$before" ] || status="no new mail in Mailpit"
  [ "$mail_to" = "$department" ] || status="mail went to $mail_to, API said $department"
  [ "$mail_reply_to" = "$SENDER" ] || status="Reply-To is '$mail_reply_to'"
  [ "$routed_by" = "agent" ] || fallback=$((fallback+1))

  hint=""
  [ "$department" = "$expected" ] || hint="  (expected $expected)"

  if [ "$status" = "ok" ]; then
    pass=$((pass+1)); echo "PASS  ${elapsed}ms  $routed_by  $department$hint  <- $message"
  else
    fail=$((fail+1)); echo "FAIL  ${elapsed}ms  $status  <- $message"
  fi
done

echo
echo "passed=$pass failed=$fail fallbacks=$fallback avg=$(( total_ms / ${#CASES[@]} ))ms"
[ "$fail" -eq 0 ]
