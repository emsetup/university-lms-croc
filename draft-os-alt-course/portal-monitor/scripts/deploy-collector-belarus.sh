#!/usr/bin/env bash
# Deploy heartbeat collector to Host belarus (SSH config).
# Usage:
#   HEARTBEAT_TOKEN=... TELEGRAM_BOT_TOKEN=... TELEGRAM_CHAT_ID=... \
#     bash portal-monitor/scripts/deploy-collector-belarus.sh
#
# Отдельный бот для портала (не Zabbix). Токен и chat_id обязательны.
# Если HEARTBEAT_TOKEN не задан, сохраняется текущий из /etc/portal-monitor.env на belarus.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SSH_HOST="${BELARUS_SSH:-belarus}"
REMOTE_DIR=/opt/portal-heartbeat-collector
STATE_DIR=/var/lib/portal-monitor
ENV_FILE=/etc/portal-monitor.env

TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN:-}"
TELEGRAM_CHAT_ID="${TELEGRAM_CHAT_ID:-}"
if [[ -z "$TELEGRAM_BOT_TOKEN" || -z "$TELEGRAM_CHAT_ID" ]]; then
  echo "TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID are required (dedicated portal bot)." >&2
  exit 1
fi

HEARTBEAT_TOKEN="${HEARTBEAT_TOKEN:-}"
if [[ -z "$HEARTBEAT_TOKEN" ]]; then
  HEARTBEAT_TOKEN="$(ssh -o BatchMode=yes "$SSH_HOST" "grep '^HEARTBEAT_TOKEN=' /etc/portal-monitor.env 2>/dev/null | cut -d= -f2- || true")"
fi
if [[ -z "$HEARTBEAT_TOKEN" ]]; then
  echo "HEARTBEAT_TOKEN is required (same value will be used on the stand agent)." >&2
  exit 1
fi

echo "[collector] sync code -> ${SSH_HOST}:${REMOTE_DIR}"
ssh -o BatchMode=yes "$SSH_HOST" "mkdir -p '${REMOTE_DIR}' '${STATE_DIR}'"
rsync -az --delete \
  --exclude '.venv' \
  --exclude '__pycache__' \
  "${ROOT}/collector/" \
  "${SSH_HOST}:${REMOTE_DIR}/"

scp -q \
  "${ROOT}/deploy/portal-heartbeat-collector.service" \
  "${ROOT}/deploy/nginx-snippet.conf" \
  "${SSH_HOST}:/tmp/"

# Pass secrets via env; remote script writes /etc/portal-monitor.env
ssh -o BatchMode=yes "$SSH_HOST" \
  HEARTBEAT_TOKEN="$HEARTBEAT_TOKEN" \
  TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN:-}" \
  TELEGRAM_CHAT_ID="${TELEGRAM_CHAT_ID:-}" \
  SILENCE_SECONDS="${SILENCE_SECONDS:-180}" \
  'bash -s' <<'REMOTE'
set -euo pipefail
REMOTE_DIR=/opt/portal-heartbeat-collector
ENV_FILE=/etc/portal-monitor.env
STATE_DIR=/var/lib/portal-monitor

cd "$REMOTE_DIR"
if [[ ! -d .venv ]]; then
  python3 -m venv .venv
fi
.venv/bin/pip install -q -r requirements.txt

if [[ -z "${TELEGRAM_BOT_TOKEN:-}" || -z "${TELEGRAM_CHAT_ID:-}" ]]; then
  echo "TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID must be set" >&2
  exit 1
fi

umask 077
cat >"$ENV_FILE" <<EOF
HEARTBEAT_TOKEN=${HEARTBEAT_TOKEN}
TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
TELEGRAM_CHAT_ID=${TELEGRAM_CHAT_ID}
SILENCE_SECONDS=${SILENCE_SECONDS:-180}
WATCH_INTERVAL=30
REMINDER_SECONDS=21600
HOST_LABEL=practice-croc
PORTAL_MONITOR_STATE=${STATE_DIR}/state.json
EOF
chmod 600 "$ENV_FILE"
chown root:root "$ENV_FILE"
mkdir -p "$STATE_DIR"
chmod 755 "$STATE_DIR"

install -m 644 /tmp/portal-heartbeat-collector.service /etc/systemd/system/portal-heartbeat-collector.service
install -m 644 /tmp/nginx-snippet.conf /etc/nginx/snippets/portal-monitor.conf

CONF=/etc/nginx/sites-available/update-cherryhaze.ru
if ! grep -q 'snippets/portal-monitor.conf' "$CONF"; then
  # insert include after starbridge-diagnostics
  if grep -q 'starbridge-diagnostics.conf' "$CONF"; then
    sed -i '/starbridge-diagnostics.conf/a\    include snippets/portal-monitor.conf;' "$CONF"
  else
    sed -i '/server_name update.cherryhaze.ru;/a\    include snippets/portal-monitor.conf;' "$CONF"
  fi
fi

nginx -t
systemctl daemon-reload
systemctl enable --now portal-heartbeat-collector.service
systemctl reload nginx
sleep 2
systemctl --no-pager --full status portal-heartbeat-collector.service | head -20
curl -sS --retry 5 --retry-delay 1 http://127.0.0.1:8099/health
echo
REMOTE

echo "[collector] done. Endpoint: https://update.cherryhaze.ru/portal-monitor/heartbeat"
