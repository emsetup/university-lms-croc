#!/bin/bash
set -euo pipefail

# В ALT rpm нет --restore. Для учебной лабы «rpm --restore nano» = переустановка пакета (как в M7).
if [[ "${1:-}" == "--restore" && "${2:-}" == "nano" ]]; then
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install --reinstall -y nano >/dev/null 2>&1 || true
  exit 0
fi

exec /usr/bin/rpm.real "$@"
