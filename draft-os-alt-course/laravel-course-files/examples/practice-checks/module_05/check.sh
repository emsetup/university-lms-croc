#!/bin/bash
# Практика модуля 5: etcnet / маршруты / DNS / nsswitch — 4 задания, 80 баллов.
set -uo pipefail
export PATH="/usr/sbin:/sbin:/usr/bin:/bin"

MAX=80
score=0

hint() { echo "HINT: $*"; }
ok() { echo "OK: $*"; }
fail_vis() { echo "FAIL: $*"; }

# --- Задание 1: eth0 — адрес 10.0.0.10/24, шлюз по умолчанию 10.0.0.1 (etcnet) ---
if ! grep -qE '^10\.0\.0\.10/24[[:space:]]*$' /etc/net/ifaces/eth0/ipv4address 2>/dev/null; then
  fail_vis "задание 1: в /etc/net/ifaces/eth0/ipv4address нет 10.0.0.10/24"
  hint "Правьте ipv4address/ipv4route в /etc/net/ifaces/eth0/, затем sudo ifdown eth0 && sudo ifup eth0 (или systemctl restart network)."
elif grep -qE 'default[[:space:]]+via[[:space:]]+10\.0\.0\.254' /etc/net/ifaces/eth0/ipv4route 2>/dev/null; then
  fail_vis "задание 1: в ipv4route всё ещё указан шлюз 10.0.0.254"
  hint "Замените маршрут по умолчанию на: default via 10.0.0.1"
elif ! grep -qE '^default[[:space:]]+via[[:space:]]+10\.0\.0\.1([[:space:]]|$)' /etc/net/ifaces/eth0/ipv4route 2>/dev/null; then
  fail_vis "задание 1: в /etc/net/ifaces/eth0/ipv4route нет default via 10.0.0.1"
  hint "Добавьте строку: default via 10.0.0.1"
else
  score=$((score + 20))
  ok "задание 1: eth0 — адрес и маршрут по умолчанию через etcnet"
fi

# --- Задание 2: eth1 — статика 192.168.1.20/24, шлюз 192.168.1.1, DNS 77.88.8.8 ---
if ! grep -qE '^192\.168\.1\.20/24[[:space:]]*$' /etc/net/ifaces/eth1/ipv4address 2>/dev/null; then
  fail_vis "задание 2: eth1 ipv4address не 192.168.1.20/24"
  hint "Заполните /etc/net/ifaces/eth1/ipv4address и остальные файлы, затем sudo ifup eth1."
elif ! grep -qE '^default[[:space:]]+via[[:space:]]+192\.168\.1\.1([[:space:]]|$)' /etc/net/ifaces/eth1/ipv4route 2>/dev/null; then
  fail_vis "задание 2: нет default via 192.168.1.1 для eth1"
  hint "Файл /etc/net/ifaces/eth1/ipv4route: default via 192.168.1.1"
elif ! grep -qE '^nameserver[[:space:]]+77\.88\.8\.8([[:space:]]|$)' /etc/net/ifaces/eth1/resolv.conf 2>/dev/null; then
  fail_vis "задание 2: в resolv.conf eth1 нет nameserver 77.88.8.8"
  hint "Добавьте в /etc/net/ifaces/eth1/resolv.conf строку nameserver 77.88.8.8"
elif ! ip -4 addr show dev eth1 2>/dev/null | grep -qE 'inet[[:space:]]+192\.168\.1\.20/'; then
  fail_vis "задание 2: на интерфейсе eth1 нет адреса 192.168.1.20 (примените ifup eth1)"
  hint "sudo ifup eth1"
else
  score=$((score + 20))
  ok "задание 2: eth1 настроен и поднят"
fi

# --- Задание 3: короткое имя gateway → 172.17.0.1 (не только DNS) ---
if ! grep -qE '^hosts:.*files.*dns' /etc/nsswitch.conf 2>/dev/null; then
  fail_vis "задание 3: в nsswitch.conf hosts должен сначала использовать files (типично: hosts: files dns …)"
  hint "Верните приоритет files для hosts перед dns (см. теорию модуля 5)."
elif ! getent hosts gateway 2>/dev/null | grep -qE '172\.17\.0\.1'; then
  fail_vis "задание 3: getent hosts gateway не указывает на 172.17.0.1"
  hint "После правки nsswitch добавьте в /etc/hosts строку: 172.17.0.1 gateway"
else
  score=$((score + 20))
  ok "задание 3: разрешение имени gateway согласовано с docker bridge"
fi

# --- Задание 4: eth0 — чистый etcnet без NM_CONTROLLED=yes ---
if ! grep -qE '^NM_CONTROLLED=no[[:space:]]*$' /etc/net/ifaces/eth0/options 2>/dev/null; then
  fail_vis "задание 4: в eth0/options нет NM_CONTROLLED=no"
  hint "Уберите управление NetworkManager с eth0: NM_CONTROLLED=no, CONFIG_IPV4=yes, BOOTPROTO=static, DISABLED=no."
elif ! grep -qE '^CONFIG_IPV4=yes[[:space:]]*$' /etc/net/ifaces/eth0/options 2>/dev/null; then
  fail_vis "задание 4: CONFIG_IPV4=yes отсутствует в eth0/options"
  hint "Для IPv4 в etcnet нужен CONFIG_IPV4=yes."
elif grep -qE '^NM_CONTROLLED=yes' /etc/net/ifaces/eth0/options 2>/dev/null; then
  fail_vis "задание 4: eth0 всё ещё с NM_CONTROLLED=yes"
  hint "Замените на NM_CONTROLLED=no и перезапустите сеть / ifup eth0."
else
  score=$((score + 20))
  ok "задание 4: eth0 в режиме etcnet без NM"
fi

echo ""
echo "ИТОГО: ${score} из ${MAX} баллов"
if [[ "$score" -ge "$MAX" ]]; then
  echo "RESULT: PASS"
else
  echo "RESULT: PARTIAL"
fi

echo "===PRACTICE_RESULT_JSON==="
echo "{\"score\":${score},\"max\":${MAX}}"

[[ "$score" -ge "$MAX" ]]
exit $?
