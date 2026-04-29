#!/bin/bash
# Практика модуля 3: ЦУС / Alterator — 3 задания, 100 баллов.
set -uo pipefail

MAX=100
score=0

hint() { echo "HINT: $*"; }
ok() { echo "OK: $*"; }
fail_vis() { echo "FAIL: $*"; }

# Задание 1: веб-ЦУС на 8080 (TLS), службы не в маске.
task1_ok=0
if systemctl is-enabled ahttpd.service 2>/dev/null | grep -q masked; then
  fail_vis "задание 1: ahttpd всё ещё в состоянии masked"
  hint "Снимите маску и запустите: sudo systemctl unmask ahttpd alteratord && sudo systemctl enable --now ahttpd alteratord"
elif systemctl is-enabled alteratord.service 2>/dev/null | grep -q masked; then
  fail_vis "задание 1: alteratord всё ещё в состоянии masked"
  hint "Снимите маску и запустите службы веб-ЦУС (ahttpd, alteratord)."
elif ! systemctl is-active --quiet ahttpd 2>/dev/null; then
  fail_vis "задание 1: ahttpd не active"
  hint "Запустите веб-службу ЦУС: sudo systemctl start ahttpd (и alteratord, если не запущен)."
elif ! systemctl is-active --quiet alteratord 2>/dev/null; then
  fail_vis "задание 1: alteratord не active"
  hint "Запустите демон Alterator: sudo systemctl start alteratord."
elif ! ss -lnt 2>/dev/null | grep -qE ':8080\b'; then
  fail_vis "задание 1: порт 8080 не слушается"
  hint "Смотрите лог: sudo journalctl -u ahttpd -n 30 --no-pager. Если там guile и «failed to install locale» — sudo apt-get install -y glibc-locales && sudo systemctl --no-block restart alteratord ahttpd (в актуальном образе лабы locales уже есть)."
else
  if curl -kfsS --connect-timeout 2 --max-time 8 "https://127.0.0.1:8080/" >/dev/null 2>&1; then
    score=$((score + 34))
    task1_ok=1
    ok "задание 1: веб-ЦУС отвечает на https://127.0.0.1:8080/"
  else
    fail_vis "задание 1: порт открыт, но HTTPS не отвечает ожидаемо"
    hint "Проверьте сертификат/конфиг ahttpd: curl -k https://127.0.0.1:8080/"
  fi
fi

# Задание 2: модуль пользователей ЦУС установлен.
if rpm -q alterator-users >/dev/null 2>&1; then
  score=$((score + 33))
  ok "задание 2: пакет alterator-users установлен"
else
  fail_vis "задание 2: пакет alterator-users не установлен"
  hint "Установите: sudo apt-get install -y alterator-users; затем перезапуск: sudo systemctl --no-block restart alteratord ahttpd (в контейнере обычный restart часто «висит» — дождитесь пару секунд и проверьте: systemctl is-active alteratord ahttpd)"
fi

# Задание 3: имя узла и DNS (как в условии практики).
hn="$(tr -d '\r\n' </etc/hostname 2>/dev/null || true)"
if [[ "$hn" != 'alt-student.local' ]]; then
  fail_vis "задание 3: /etc/hostname = «${hn:-пусто}», ожидается alt-student.local"
  hint "Имя в /etc/hostname (проверка смотрит на файл): echo alt-student.local | sudo tee /etc/hostname && sudo hostname alt-student.local. Если hostnamectl даёт «Device or resource busy» в контейнере — это ожидаемо, tee достаточно."
elif ! grep -qE '^nameserver[[:space:]]+77\.88\.8\.8([[:space:]]|$)' /etc/resolv.conf 2>/dev/null; then
  fail_vis "задание 3: в /etc/resolv.conf нет nameserver 77.88.8.8"
  hint "Добавьте строку nameserver 77.88.8.8 в /etc/resolv.conf (или настройте DNS через etcnet, согласованно с условием)."
else
  score=$((score + 33))
  ok "задание 3: hostname и DNS соответствуют эталону"
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
