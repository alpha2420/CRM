#!/usr/bin/env bash
#
# Check a live installation from any computer:
#   bash deploy/smoke-test.sh https://crm.example.com
#
# It looks at what visitors and attackers see: the app answers, HTTPS is
# enforced, security headers are sent, and secret files are not served.
#
set -uo pipefail

URL="${1:-}"
[[ -n "$URL" ]] || { echo "Usage: bash deploy/smoke-test.sh https://crm.example.com"; exit 1; }
URL="${URL%/}"
FAILED=0

pass() { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; FAILED=1; }
status() { curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$1"; }

echo "Checking $URL"

[[ "$(status "$URL/up")" == 200 ]] && pass "Health check (/up) is OK" || fail "Health check (/up) failed: run 'php artisan crm:health' on the server"
[[ "$(status "$URL/login")" == 200 ]] && pass "Login page loads" || fail "Login page does not load"

HEADERS="$(curl -s -D - -o /dev/null --max-time 15 "$URL/login" | tr -d '\r' | tr '[:upper:]' '[:lower:]')"
grep -q '^content-security-policy:' <<<"$HEADERS" && pass "Content Security Policy is sent" || fail "No Content Security Policy header"
grep -q '^x-content-type-options: nosniff' <<<"$HEADERS" && pass "MIME sniffing is blocked" || fail "No X-Content-Type-Options header"
grep -Eq "^x-frame-options:|frame-ancestors" <<<"$HEADERS" && pass "Pages cannot be framed by other sites" || fail "No framing protection"
grep -q '^x-powered-by:' <<<"$HEADERS" && fail "Server reveals its PHP version (X-Powered-By)" || pass "Server does not reveal its PHP version"

if [[ "$URL" == https://* ]]; then
    grep -q '^strict-transport-security:' <<<"$HEADERS" && pass "HSTS is on" || fail "No HSTS header"
    HTTP_URL="http://${URL#https://}"
    LOCATION="$(curl -s -o /dev/null -w '%{redirect_url}' --max-time 15 "$HTTP_URL/login")"
    [[ "$LOCATION" == https://* ]] && pass "Plain http redirects to https" || fail "Plain http does not redirect to https"
    COOKIE="$(grep '^set-cookie:' <<<"$HEADERS" | head -1)"
    [[ -z "$COOKIE" || "$COOKIE" == *secure* ]] && pass "Cookies are https-only" || fail "Session cookie is missing the Secure flag (SESSION_SECURE_COOKIE=true)"
else
    echo "  ! Not https: fine for a test, not for real customers"
fi

for secret in .env .git/config composer.json storage/logs/laravel.log; do
    code="$(status "$URL/$secret")"
    [[ "$code" == 200 ]] && fail "/$secret is downloadable!" || pass "/$secret is not served ($code)"
done

echo
if [[ $FAILED -eq 0 ]]; then echo "All checks passed."; else echo "Some checks failed: see docs/DEPLOYMENT.md → If something goes wrong."; fi
exit $FAILED
