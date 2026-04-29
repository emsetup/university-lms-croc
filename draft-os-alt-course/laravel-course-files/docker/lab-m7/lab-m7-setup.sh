#!/bin/bash
# Первый запуск контейнера: три задания (rpm -V, Osec main, Osec prod с ошибкой в EXCLUDE).
# Повторный запуск не выполняется (флаг /var/lib/os-alt-lab-m7/.setup-done).
set -euo pipefail

STAMP=/var/lib/os-alt-lab-m7/.setup-done
if [[ -f "$STAMP" ]]; then
  exit 0
fi

mkdir -p /var/lib/os-alt-lab-m7 /var/lib/osec/lab-main /var/lib/osec/lab-prod \
  /var/log/osec /run/osec-m7 /etc/osec

log() { echo "[lab-m7-setup] $*"; }

log "конфиги Osec для лабы (DIRS/EXCLUDE в формате shell, читает /usr/bin/osec)"
# Вместо /etc/openssh (в минимальном образе часто нет каталога) — /etc/hostname (есть в контейнере).
cat > /etc/osec/osec.conf << 'EOF'
# Учебный конфиг модуля 7 — списки для обёртки osec (см. /usr/bin/osec).
DIRS="/etc/passwd /etc/group /etc/sudoers /bin/su /usr/bin/sudo /etc/hostname"
EXCLUDE="/etc/mtab /etc/resolv.conf /etc/adjtime"
EOF

cat > /etc/osec/osec-prod.conf << 'EOF'
# «Продакшн»-конфиг с намеренной дырой: критичные пути в EXCLUDE.
DIRS="/etc/passwd /etc/group /etc/sudoers /bin/su /usr/bin/sudo /etc/hostname"
EXCLUDE="/etc/mtab /etc/resolv.conf /etc/adjtime /etc/passwd /etc/sudoers /usr/bin/sudo /bin/su"
EOF

log "задание 1: /bin/ls и /usr/bin/chsh"
echo "# lab7-compromised" >> /bin/ls
chmod 777 /usr/bin/chsh

log "задание 2: эталон Osec (osec.conf), затем второй root в passwd"
/usr/bin/osec
echo "backdoor:x:0:0::/root:/bin/bash" >> /etc/passwd

log "задание 3: эталон по osec-prod.conf, затем правка sudoers"
/usr/bin/osec -f /etc/osec/osec-prod.conf
echo "# lab7-backdoor" >> /etc/sudoers

touch "$STAMP"
log "готово."
