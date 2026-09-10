#!/usr/bin/env bash
# Deploy portal heartbeat agent to the OS Alt stand.
# Usage:
#   HEARTBEAT_TOKEN=... STAND_SSH=emednikov@172.26.76.216 \
#     bash portal-monitor/scripts/deploy-agent-stand.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAND_SSH="${STAND_SSH:-emednikov@172.26.76.216}"
HEARTBEAT_TOKEN="${HEARTBEAT_TOKEN:?Set HEARTBEAT_TOKEN (same as collector)}"
HEARTBEAT_URL="${HEARTBEAT_URL:-https://update.cherryhaze.ru/portal-monitor/heartbeat}"

echo "[agent] deploy -> ${STAND_SSH}"
scp -q \
  "${ROOT}/agent/send-heartbeat.sh" \
  "${ROOT}/deploy/portal-agent.service" \
  "${ROOT}/deploy/portal-agent.timer" \
  "${STAND_SSH}:/tmp/"

ssh -o BatchMode=yes "$STAND_SSH" \
  HEARTBEAT_TOKEN="$HEARTBEAT_TOKEN" \
  HEARTBEAT_URL="$HEARTBEAT_URL" \
  'bash -s' <<'REMOTE'
set -euo pipefail
sudo install -m 755 /tmp/send-heartbeat.sh /usr/local/bin/portal-send-heartbeat.sh
sudo install -m 644 /tmp/portal-agent.service /etc/systemd/system/portal-agent.service
sudo install -m 644 /tmp/portal-agent.timer /etc/systemd/system/portal-agent.timer

umask 077
TMP=$(mktemp)
cat >"$TMP" <<EOF
HOST_ID=practice-croc
PORTAL_HOST=practice.croc.ru
BASE_URL=https://127.0.0.1
LAB_DAEMON_URL=http://127.0.0.1:8090/health
HTTP_TIMEOUT=10
HEARTBEAT_URL=${HEARTBEAT_URL}
HEARTBEAT_TOKEN=${HEARTBEAT_TOKEN}
EOF
sudo install -m 600 -o root -g root "$TMP" /etc/portal-monitor-agent.env
rm -f "$TMP"

sudo systemctl daemon-reload
sudo systemctl enable --now portal-agent.timer
sudo systemctl start portal-agent.service || true
sleep 1
sudo systemctl --no-pager status portal-agent.timer | head -15
echo "--- last run ---"
sudo journalctl -u portal-agent.service -n 30 --no-pager || true
REMOTE

echo "[agent] done"
