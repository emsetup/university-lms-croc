#!/usr/bin/env bash
# Local checks for practice.croc.ru + push heartbeat to belarus collector.
# Config: /etc/portal-monitor-agent.env (HEARTBEAT_URL, HEARTBEAT_TOKEN, …)
set -euo pipefail

ENV_FILE="${PORTAL_MONITOR_ENV:-/etc/portal-monitor-agent.env}"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  set -a
  # shellcheck source=/dev/null
  source "$ENV_FILE"
  set +a
fi

HOST_ID="${HOST_ID:-practice-croc}"
PORTAL_HOST="${PORTAL_HOST:-practice.croc.ru}"
BASE_URL="${BASE_URL:-https://127.0.0.1}"
LAB_DAEMON_URL="${LAB_DAEMON_URL:-http://127.0.0.1:8090/health}"
HTTP_TIMEOUT="${HTTP_TIMEOUT:-10}"
HEARTBEAT_URL="${HEARTBEAT_URL:?Set HEARTBEAT_URL}"
HEARTBEAT_TOKEN="${HEARTBEAT_TOKEN:?Set HEARTBEAT_TOKEN}"

json_escape() {
  local s=${1-}
  s=${s//\\/\\\\}
  s=${s//\"/\\\"}
  s=${s//$'\n'/\\n}
  s=${s//$'\r'/}
  printf '%s' "$s"
}

check_http() {
  # name url expected_ok_codes (comma-separated)
  local name="$1" url="$2" ok_codes="${3:-200,301,302}"
  local start end ms code bodyfile
  bodyfile=$(mktemp)
  start=$(date +%s%N)
  code=$(curl -sk \
    --connect-timeout "$HTTP_TIMEOUT" \
    --max-time "$HTTP_TIMEOUT" \
    -o "$bodyfile" -w '%{http_code}' \
    -H "Host: ${PORTAL_HOST}" \
    "$url" || echo "000")
  end=$(date +%s%N)
  ms=$(( (end - start) / 1000000 ))
  local ok=false
  local IFS=,
  local c
  for c in $ok_codes; do
    if [[ "$code" == "$c" ]]; then
      ok=true
      break
    fi
  done
  # Reject 5xx even if listed somehow
  if [[ "$code" =~ ^5 ]]; then
    ok=false
  fi
  local size
  size=$(wc -c <"$bodyfile" | tr -d ' ')
  rm -f "$bodyfile"
  if [[ "$ok" == true && "$size" -eq 0 && "$code" == "200" ]]; then
    ok=false
  fi
  printf '{"status":%s,"ms":%s,"ok":%s,"bytes":%s}' "$code" "$ms" "$ok" "$size"
  [[ "$ok" == true ]]
}

check_lab_daemon() {
  local code
  code=$(curl -sS \
    --connect-timeout 3 \
    --max-time 5 \
    -o /dev/null -w '%{http_code}' \
    "$LAB_DAEMON_URL" 2>/dev/null || echo "000")
  if [[ "$code" == "200" ]]; then
    printf '{"ok":true,"status":%s,"mode":"http"}' "$code"
    return 0
  fi
  # Fallback: port listening
  if ss -ltn 2>/dev/null | grep -qE ':8090\s'; then
    printf '{"ok":true,"status":%s,"mode":"listen"}' "$code"
    return 0
  fi
  printf '{"ok":false,"status":%s,"mode":"down"}' "$code"
  return 1
}

unit_active() {
  local pattern="$1"
  local u
  u=$(systemctl list-units --type=service --state=running --no-legend --no-pager 2>/dev/null \
    | awk '{print $1}' | grep -E "$pattern" | head -1 || true)
  if [[ -n "$u" ]]; then
    printf '%s' "active"
    return 0
  fi
  printf '%s' "inactive"
  return 1
}

overall_ok=true
home_json="null"
login_json="null"
lab_json="null"
nginx_st="unknown"
php_st="unknown"

if home_json=$(check_http "home" "${BASE_URL}/" "200,301,302"); then
  :
else
  overall_ok=false
  home_json=${home_json:-'{"status":0,"ms":0,"ok":false,"bytes":0}'}
fi

if login_json=$(check_http "login" "${BASE_URL}/login" "200,301,302"); then
  :
else
  overall_ok=false
  login_json=${login_json:-'{"status":0,"ms":0,"ok":false,"bytes":0}'}
fi

if lab_json=$(check_lab_daemon); then
  :
else
  overall_ok=false
  lab_json=${lab_json:-'{"ok":false,"status":0,"mode":"down"}'}
fi

if nginx_st=$(unit_active '^nginx(\.service)?$'); then
  :
else
  overall_ok=false
  nginx_st=${nginx_st:-inactive}
fi

if php_st=$(unit_active '^php[0-9.]*-fpm(\.service)?$'); then
  :
else
  # php-fpm may be named differently; try socket
  if [[ -S /run/php/php-fpm.sock ]] || ls /run/php/php*-fpm.sock >/dev/null 2>&1; then
    php_st="active"
  else
    overall_ok=false
    php_st=${php_st:-inactive}
  fi
fi

ts=$(date -u +%Y-%m-%dT%H:%M:%SZ)
ok_json=true
[[ "$overall_ok" == true ]] || ok_json=false

payload=$(cat <<EOF
{"host_id":"$(json_escape "$HOST_ID")","ts":"$ts","ok":$ok_json,"checks":{"home":$home_json,"login":$login_json,"lab_daemon":$lab_json,"nginx":"$(json_escape "$nginx_st")","php_fpm":"$(json_escape "$php_st")"}}
EOF
)

curl -sS --connect-timeout 10 --max-time 20 \
  -X POST "$HEARTBEAT_URL" \
  -H "Authorization: Bearer ${HEARTBEAT_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "$payload" >/dev/null

exit 0
