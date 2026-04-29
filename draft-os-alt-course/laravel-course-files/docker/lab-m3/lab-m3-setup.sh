#!/bin/bash
# Первый старт контейнера (до PID1=systemd): «поломки» лабы M3 (ЦУС / Alterator).
set -euo pipefail
STAMP=/var/lib/os-alt-lab-m3/.setup-done
if [[ -f "$STAMP" ]]; then
  exit 0
fi

mkdir -p /var/lib/os-alt-lab-m3
log() { echo "[lab-m3-setup] $*"; }

log "задание 1: маскируем ahttpd и alteratord (веб-ЦУС недоступен, пакеты установлены)"
ln -sf /dev/null /etc/systemd/system/ahttpd.service
ln -sf /dev/null /etc/systemd/system/alteratord.service

log "задание 2: удаляем модуль пользователей ЦУС (без сети apt)"
if rpm -q alterator-users >/dev/null 2>&1; then
  rpm -e --nodeps alterator-users || true
fi

log "задание 3: «ломаем» имя узла и DNS"
printf '%s\n' 'lab-m3-wrong.local' >/etc/hostname
# На старте без NM часто читается /etc/hostname; hostnamectl поднимется с systemd.
printf '%s\n' 'nameserver 1.1.1.1' >/etc/resolv.conf

touch "$STAMP"
log "=== Lab M3 setup complete ==="
