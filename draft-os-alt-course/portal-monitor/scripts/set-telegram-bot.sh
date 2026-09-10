#!/usr/bin/env bash
# Обновить только Telegram-бота коллектора и отправить тестовое сообщение.
# Usage:
#   TELEGRAM_BOT_TOKEN=... TELEGRAM_CHAT_ID=... \
#     bash portal-monitor/scripts/set-telegram-bot.sh
set -euo pipefail

SSH_HOST="${BELARUS_SSH:-belarus}"
TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN:?Set TELEGRAM_BOT_TOKEN}"
TELEGRAM_CHAT_ID="${TELEGRAM_CHAT_ID:?Set TELEGRAM_CHAT_ID}"

ssh -o BatchMode=yes "$SSH_HOST" \
  TELEGRAM_BOT_TOKEN="$TELEGRAM_BOT_TOKEN" \
  TELEGRAM_CHAT_ID="$TELEGRAM_CHAT_ID" \
  'bash -s' <<'REMOTE'
set -euo pipefail
ENV_FILE=/etc/portal-monitor.env
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE — deploy collector first" >&2
  exit 1
fi

tmp=$(mktemp)
awk -v bot="$TELEGRAM_BOT_TOKEN" -v chat="$TELEGRAM_CHAT_ID" '
  BEGIN { b=0; c=0 }
  /^TELEGRAM_BOT_TOKEN=/ { print "TELEGRAM_BOT_TOKEN=" bot; b=1; next }
  /^TELEGRAM_CHAT_ID=/ { print "TELEGRAM_CHAT_ID=" chat; c=1; next }
  { print }
  END {
    if (!b) print "TELEGRAM_BOT_TOKEN=" bot
    if (!c) print "TELEGRAM_CHAT_ID=" chat
  }
' "$ENV_FILE" >"$tmp"
install -m 600 -o root -g root "$tmp" "$ENV_FILE"
rm -f "$tmp"

systemctl restart portal-heartbeat-collector.service
sleep 2
curl -sS --retry 5 --retry-delay 1 http://127.0.0.1:8099/health
echo

# Test message via Bot API
resp=$(curl -sS -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
  -H 'Content-Type: application/json' \
  -d "{\"chat_id\":\"${TELEGRAM_CHAT_ID}\",\"text\":\"✅ Portal monitor: Telegram bot configured\\nHost: practice-croc\\nCollector: update.cherryhaze.ru\"}")
echo "$resp" | python3 -c 'import sys,json; d=json.load(sys.stdin); print("telegram_ok" if d.get("ok") else d)'
REMOTE

echo "[telegram] updated on ${SSH_HOST}"
