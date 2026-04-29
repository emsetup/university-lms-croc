#!/bin/bash
# Обёртка: в учебном образе «Альт» p10 у rpm часто нет --restore; для лабы — reinstall через apt-get.
set -euo pipefail
REAL=/usr/bin/rpm.real
if [[ "${1:-}" == "--restore" ]]; then
  shift
  export DEBIAN_FRONTEND=noninteractive
  # После apt-get clean в образе часто нет partial — без них apt-get падает.
  mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial
  apt-get -y update
  apt-get install -y --reinstall "$@"
  rc=$?
  # apt reinstall не всегда сбрасывает локально изменённые права (в лабе chsh был 777).
  if [[ "$rc" -eq 0 ]]; then
    for pkg in "$@"; do
      if [[ "$pkg" == "shadow-change" && -e /usr/bin/chsh ]]; then
        chmod 4755 /usr/bin/chsh 2>/dev/null || chmod 4711 /usr/bin/chsh 2>/dev/null || true
      fi
    done
  fi
  exit "$rc"
fi
exec "$REAL" "$@"
