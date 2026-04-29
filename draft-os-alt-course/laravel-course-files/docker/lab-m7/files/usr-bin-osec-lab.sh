#!/bin/bash
# Учебная обёртка: upstream `osec` требует пути или `-f dirs.list`; в лабе конфиги
# `/etc/osec/osec.conf` и `/etc/osec/osec-prod.conf` задают переменные DIRS= и EXCLUDE=.
# OSEC_LAB_READONLY=1 — добавить `-r` (не обновлять базу), для check.sh.
set -euo pipefail
REAL=/usr/bin/osec.real

lab_write_lists() {
  local conf="$1" suffix="$2"
  # shellcheck disable=1090
  source "$conf"
  : "${DIRS?missing DIRS in $conf}" "${EXCLUDE?missing EXCLUDE in $conf}"
  mkdir -p /run/osec-m7
  local dfile="/run/osec-m7/dirs-${suffix}.list"
  local xfile="/run/osec-m7/exclude-${suffix}.list"
  : >"$dfile"
  : >"$xfile"
  local p
  for p in $DIRS; do printf '%s\n' "$p" >>"$dfile"; done
  for p in $EXCLUDE; do printf '%s\n' "$p" >>"$xfile"; done
}

lab_ro_arg() {
  # Не заканчивать функцию на «ложном» [[ … ]] при set -e — иначе $(lab_ro_arg) рвёт весь скрипт.
  if [[ "${OSEC_LAB_READONLY:-}" == "1" ]]; then
    printf '%s' -r
  fi
  return 0
}

if [[ $# -eq 0 ]]; then
  lab_write_lists /etc/osec/osec.conf main
  ro=$(lab_ro_arg)
  if [[ -n "$ro" ]]; then
    exec "$REAL" -R -r -D /var/lib/osec/lab-main -f /run/osec-m7/dirs-main.list -X /run/osec-m7/exclude-main.list
  fi
  exec "$REAL" -R -D /var/lib/osec/lab-main -f /run/osec-m7/dirs-main.list -X /run/osec-m7/exclude-main.list
fi

if [[ "${1:-}" == "-f" && "${2:-}" == "/etc/osec/osec-prod.conf" ]]; then
  shift 2
  lab_write_lists /etc/osec/osec-prod.conf prod
  ro=$(lab_ro_arg)
  if [[ -n "$ro" ]]; then
    exec "$REAL" -R -r -D /var/lib/osec/lab-prod -f /run/osec-m7/dirs-prod.list -X /run/osec-m7/exclude-prod.list "$@"
  fi
  exec "$REAL" -R -D /var/lib/osec/lab-prod -f /run/osec-m7/dirs-prod.list -X /run/osec-m7/exclude-prod.list "$@"
fi

exec "$REAL" "$@"
