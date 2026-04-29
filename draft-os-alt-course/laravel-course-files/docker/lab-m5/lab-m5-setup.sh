#!/bin/bash
# Первый старт контейнера (до PID1=systemd): «поломки» и заготовки под практику модуля 5 (etcnet).
set -euo pipefail
STAMP=/var/lib/os-alt-lab-m5/.setup-done
if [[ -f "$STAMP" ]]; then
  exit 0
fi

mkdir -p /var/lib/os-alt-lab-m5
log() { echo "[lab-m5-setup] $*"; }

# Второй интерфейс — dummy eth1 (в Docker обычно один «настоящий» eth0).
if ! ip link show eth1 &>/dev/null; then
  log "создаём eth1 (type dummy)"
  ip link add eth1 type dummy
fi
ip link set eth1 up || true

# --- Задание 1: адрес eth0 виден, маршрут по умолчанию «ломаный» (не 10.0.0.1) ---
mkdir -p /etc/net/ifaces/eth0
cat > /etc/net/ifaces/eth0/ipv4address <<'EOF'
10.0.0.10/24
EOF
cat > /etc/net/ifaces/eth0/ipv4route <<'EOF'
default via 10.0.0.254
EOF
cat > /etc/net/ifaces/eth0/resolv.conf <<'EOF'
nameserver 77.88.8.8
EOF
cat > /etc/net/ifaces/eth0/options <<'EOF'
TYPE=eth
BOOTPROTO=static
CONFIG_IPV4=yes
DISABLED=no
NM_CONTROLLED=yes
EOF

# --- Задание 2: каталог eth1 есть, параметры почти пустые ---
mkdir -p /etc/net/ifaces/eth1
cat > /etc/net/ifaces/eth1/options <<'EOF'
TYPE=eth
BOOTPROTO=static
CONFIG_IPV4=yes
DISABLED=no
NM_CONTROLLED=no
EOF
: > /etc/net/ifaces/eth1/ipv4address
: > /etc/net/ifaces/eth1/ipv4route
: > /etc/net/ifaces/eth1/resolv.conf

# --- Задание 3: имя gateway не резолвится (сначала dns, потом files) ---
# В Docker overlay «sed -i» по /etc/nsswitch.conf и /etc/hosts часто даёт EBUSY — пишем через tmp.
if [[ -f /etc/nsswitch.conf ]] && [[ ! -f /etc/nsswitch.conf.lab-m5-orig ]]; then
  cp -a /etc/nsswitch.conf /etc/nsswitch.conf.lab-m5-orig
fi
# Пока стоит dns перед files, статическая запись в /etc/hosts для «gateway» не используется.
t_nss="$(mktemp)"
awk 'BEGIN{h=0} /^hosts:/ { print "hosts: dns files"; h=1; next } { print } END{ if (h==0) print "hosts: dns files" }' /etc/nsswitch.conf >"$t_nss"
cat "$t_nss" >/etc/nsswitch.conf
rm -f "$t_nss"

t_hosts="$(mktemp)"
awk '!/^[[:space:]]*172\.17\.0\.1[[:space:]]+gateway([[:space:]]|$)/ && !/^[[:space:]]*gateway([[:space:]]|$)/' /etc/hosts >"$t_hosts"
cat "$t_hosts" >/etc/hosts
rm -f "$t_hosts"

touch "$STAMP"
log "=== Lab M5 setup complete ==="
