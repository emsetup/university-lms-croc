#!/bin/bash
# Модуль 7: rpm -V и Osec. Формат: TASKn:PASS|FAIL:… и RESULT:пройдено:провалено.
# Платформа парсит вывод; код выхода всегда 0.
# docker exec без login-shell даёт урезанный PATH — явно задаём, чтобы osec/rpm/stat были в PATH.
set -uo pipefail
export PATH="/usr/sbin:/sbin:/usr/bin:/bin"

MAX=100
passed=0
failed=0
hint() { echo "HINT: $*"; }

emit_task() {
  local n="$1" st="$2" msg="${3:-}"
  echo "TASK${n}:${st}${msg:+:${msg}}"
  if [[ "$st" == PASS ]]; then
    passed=$((passed + 1))
  else
    failed=$((failed + 1))
  fi
}

export OSEC_LAB_READONLY=1

# Снимок для отладки: видно, в каком состоянии контейнер в момент проверки (тот же, что и веб-терминал).
chsh_mode=$(stat -L -c '%a' /usr/bin/chsh 2>/dev/null || stat -c '%a' /usr/bin/chsh 2>/dev/null || echo '?')
bd_lines=$(grep -c '^backdoor:' /etc/passwd 2>/dev/null || true)
bd_lines=${bd_lines:-0}
ex_snip=$(grep -m1 -E '^EXCLUDE=' /etc/osec/osec-prod.conf 2>/dev/null | cut -c1-100 | tr -d '\r' || true)
echo "# lab7-check: hostname=$(hostname 2>/dev/null) chsh_mode=${chsh_mode} backdoor_lines=${bd_lines} EXCLUDE_snip=${ex_snip:-?}"

# --- Задание 1: /bin/ls и права /usr/bin/chsh ---
t1msg=""
if rpm -V coreutils 2>/dev/null | grep -qE '[[:space:]]/bin/ls$'; then
  t1msg="файл /bin/ls всё ещё отличается от эталона"
fi
perm=$(stat -L -c '%a' /usr/bin/chsh 2>/dev/null || stat -c '%a' /usr/bin/chsh 2>/dev/null || echo '')
chsh_suid_ok=0
[[ "$perm" == *4755* || "$perm" == *4711* ]] && chsh_suid_ok=1
[[ -u /usr/bin/chsh ]] && chsh_suid_ok=1
if [[ "$chsh_suid_ok" -eq 0 ]]; then
  [[ -n "$t1msg" ]] && t1msg="${t1msg}; "
  t1msg="${t1msg}права /usr/bin/chsh не восстановлены (сейчас ${perm:-?}, ожидается suid 4755 или 4711)"
fi
if [[ -z "$t1msg" ]]; then
  emit_task 1 PASS
  echo "OK: задание 1 — /bin/ls и chsh"
else
  emit_task 1 FAIL "$t1msg"
  echo "FAIL: задание 1 — $t1msg"
  chsh_pkg=$(rpm -qf /usr/bin/chsh 2>/dev/null | head -1 || echo '?')
  hint "Задание 1 — только через RPM: sudo rpm --restore coreutils shadow-change (без этой команды chsh остаётся 777, даже если задания 2–3 уже сделаны). Затем stat -L -c '%a' /usr/bin/chsh (4755 или 4711). Если после restore снова появятся расхождения в osec — выполните sudo osec и при необходимости sudo osec -f /etc/osec/osec-prod.conf. Не используйте util-linux для chsh, если rpm -qf указывает shadow-change."
fi

# --- Задание 2: только root с uid 0; osec без расхождений ---
t2msg=""
bad0=$(awk -F: '$3==0 && $1!="root" {print $1}' /etc/passwd 2>/dev/null | paste -sd, -)
if [[ -n "$bad0" ]]; then
  t2msg="в /etc/passwd есть пользователи с uid=0 кроме root (лишние логины: ${bad0})"
fi
chg=0
if ! command -v osec &>/dev/null; then
  [[ -n "$t2msg" ]] && t2msg="${t2msg}; "
  t2msg="${t2msg}команда osec недоступна"
else
  chg=$(osec 2>&1 | grep -ciE 'changed|new|removed' || true)
  if [[ "${chg:-0}" -gt 0 ]]; then
    [[ -n "$t2msg" ]] && t2msg="${t2msg}; "
    t2msg="${t2msg}osec всё ещё видит расхождения с эталонной базой"
  fi
fi
if [[ -z "$t2msg" ]]; then
  emit_task 2 PASS
  echo "OK: задание 2 — passwd и osec"
else
  emit_task 2 FAIL "$t2msg"
  echo "FAIL: задание 2 — $t2msg"
  hint "osec (отчёт в выводе команды); sudo awk -F: '\$3==0' /etc/passwd; sudo sed -i '/^backdoor:/d' /etc/passwd; sudo osec (обновить эталон без OSEC_LAB_READONLY)"
fi

# --- Задание 3: критические пути не в EXCLUDE; osec-prod видит sudoers ---
t3msg=""
excl=0
# Нельзя использовать «EXCLUDE.*sudo» — совпадёт с /etc/sudoers; проверяем полные пути.
if [[ -f /etc/osec/osec-prod.conf ]]; then
  excl=$(grep -E '^EXCLUDE=' /etc/osec/osec-prod.conf 2>/dev/null | grep -cE '(/etc/passwd([[:space:]\"#]|$)|/etc/sudoers([[:space:]\"#]|$)|/usr/bin/sudo([[:space:]\"#]|$)|/bin/su([[:space:]\"#]|$))' || true)
fi
if [[ "${excl:-0}" -gt 0 ]]; then
  t3msg="критические файлы всё ещё находятся в EXCLUDE"
fi

probe_ok=0
if [[ -f /etc/osec/osec-prod.conf ]] && [[ "${excl:-0}" -eq 0 ]] && command -v osec &>/dev/null; then
  if grep -qE '^# lab7checkprobe$' /etc/sudoers 2>/dev/null; then
    sed -i '/^# lab7checkprobe$/d' /etc/sudoers
  fi
  echo '# lab7checkprobe' >> /etc/sudoers
  c=$(osec -f /etc/osec/osec-prod.conf 2>&1 | grep -ci sudoers || true)
  sed -i '/^# lab7checkprobe$/d' /etc/sudoers
  if [[ "${c:-0}" -gt 0 ]]; then
    probe_ok=1
  fi
fi

if [[ "${excl:-0}" -gt 0 ]]; then
  emit_task 3 FAIL "$t3msg"
  echo "FAIL: задание 3 — $t3msg"
  hint "grep EXCLUDE /etc/osec/osec-prod.conf; замените строку EXCLUDE= целиком, например: sudo perl -i -pe 's|^EXCLUDE=.*|EXCLUDE=\"/etc/mtab /etc/resolv.conf /etc/adjtime\"|' /etc/osec/osec-prod.conf; sudo osec -f /etc/osec/osec-prod.conf"
elif [[ "$probe_ok" -eq 1 ]]; then
  emit_task 3 PASS
  echo "OK: задание 3 — osec-prod видит sudoers"
else
  t3b="osec не обнаруживает изменение в /etc/sudoers — конфиг не исправлен или база не пересобрана"
  emit_task 3 FAIL "$t3b"
  echo "FAIL: задание 3 — $t3b"
  hint "grep 'EXCLUDE' /etc/osec/osec-prod.conf; nano /etc/osec/osec-prod.conf; osec -f /etc/osec/osec-prod.conf"
fi

score=$((MAX * passed / 3))
total=$((passed + failed))
[[ "$total" -eq 0 ]] && total=3

echo ""
echo "RESULT:${passed}:${failed}"
if [[ "$failed" -eq 0 ]]; then
  echo "OUTCOME:PASS"
else
  echo "OUTCOME:PARTIAL"
fi

echo "===PRACTICE_RESULT_JSON==="
echo "{\"score\":${score},\"max\":${MAX},\"tasks_passed\":${passed},\"tasks_failed\":${failed}}"

exit 0
