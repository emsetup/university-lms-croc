#!/usr/bin/env bash
# Запуск lab-daemon без docker compose (например на ALT без плагина compose).
# Из корня проекта, после сборки образа: docker build -f lab-daemon/Dockerfile -t os-alt-lab-daemon:latest lab-daemon/
# Секрет должен совпадать с PRACTICE_LAB_DAEMON_SECRET в .env Laravel.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
IMAGE="${LAB_DAEMON_IMAGE:-os-alt-lab-daemon:latest}"
NAME="${LAB_DAEMON_CONTAINER:-os-alt-lab-daemon-run}"
HOST="${LAB_PUBLIC_HOST:-127.0.0.1}"
SECRET="${LAB_DAEMON_SECRET:?Задайте LAB_DAEMON_SECRET}"

sudo docker rm -f "$NAME" 2>/dev/null || true
exec sudo docker run -d --name "$NAME" --restart unless-stopped \
  --network host \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -e "LAB_DAEMON_SECRET=$SECRET" \
  -e "LAB_TTL_MINUTES=${LAB_TTL_MINUTES:-480}" \
  -e "LAB_ENABLE_TTY=${LAB_ENABLE_TTY:-1}" \
  -e "LAB_PUBLIC_HOST=$HOST" \
  -e "LAB_TTY_PORT_MIN=${LAB_TTY_PORT_MIN:-40000}" \
  -e "LAB_TTY_PORT_MAX=${LAB_TTY_PORT_MAX:-41000}" \
  "$IMAGE"
