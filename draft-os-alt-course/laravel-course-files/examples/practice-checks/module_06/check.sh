#!/bin/bash
# Проверка лабораторной М6 (PAM / passwdqc / faillock). 5 блоков по 20 баллов.
set -uo pipefail

MAX=100
score=0
hint() { echo "HINT: $*"; }
ok() { echo "OK: $*"; }
fail_vis() { echo "FAIL: $*"; }

PASSWDQC=/etc/passwdqc.conf
PAM_CORE=/etc/pam.d/passwd
GOOD_MIN='min=disabled,24,11,8,7'
GOOD_ENF='enforce=everyone'
GOOD_PWQC='config=/etc/passwdqc.conf'

# --- 1. путь pam_passwdqc → существующий /etc/passwdqc.conf (20) ---
if [[ -r "$PAM_CORE" ]] && grep 'pam_passwdqc' "$PAM_CORE" | grep -q "$GOOD_PWQC" \
  && ! grep 'pam_passwdqc' "$PAM_CORE" | grep -qE 'labbroken|_backup'; then
  score=$((score + 20))
  ok "задание 1: pam_passwdqc указывает на /etc/passwdqc.conf"
else
  fail_vis "задание 1: в $PAM_CORE исправьте строку pam_passwdqc (config=) на живой /etc/passwdqc.conf"
  hint "journalctl -t passwd -n 30 или journalctl _COMM=passwd -n 30 — ищите ошибки pam_passwdqc / «No such file» по конфигу."
fi

# --- 2. enforce=everyone (20) ---
if [[ -r "$PASSWDQC" ]] && grep '^enforce=' "$PASSWDQC" | grep -q 'everyone'; then
  score=$((score + 20))
  ok "задание 2: enforce=everyone"
else
  fail_vis "задание 2: в $PASSWDQC нужно enforce=everyone (политика для всех, включая root)"
  hint "Проверьте: grep '^enforce=' $PASSWDQC"
fi

# --- 3. min=disabled,24,11,8,7 + pwqcheck (20) ---
if [[ -r "$PASSWDQC" ]] && grep -E "^${GOOD_MIN}([[:space:]]|$)" "$PASSWDQC" >/dev/null; then
  part=12
  if command -v pwqcheck &>/dev/null; then
    if echo 'simple' | pwqcheck -1 2>&1 | grep -qiE 'BAD|weak|illegal|short|rejected|not enough'; then
      part=$((part + 4))
    else
      hint "Простой пароль «simple» должен отклоняться pwqcheck после правки min."
    fi
    if echo 'C0mplexP@ss!' | pwqcheck -1 2>&1 | grep -qiE 'OK|allowed|good'; then
      part=$((part + 4))
    else
      hint "Сложный пароль C0mplexP@ss! должен проходить pwqcheck -1."
    fi
  else
    hint "Команда pwqcheck не найдена — начислены только баллы за строку min= в конфиге."
  fi
  score=$((score + part))
  if [[ "$part" -eq 20 ]]; then
    ok "задание 3: min и проверка pwqcheck"
  else
    fail_vis "задание 3: строка min= верна, но проверка pwqcheck неполная (${part}/20)"
  fi
else
  fail_vis "задание 3: ожидается строка ${GOOD_MIN} в $PASSWDQC"
  hint "Восстановите min из методички: min=disabled,24,11,8,7"
fi

# --- 4. симлинк system-auth → system-auth-local (20) ---
link=$(readlink /etc/pam.d/system-auth 2>/dev/null || true)
if [[ "$link" == "system-auth-local" ]]; then
  score=$((score + 20))
  ok "задание 4: system-auth -> system-auth-local"
else
  fail_vis "задание 4: /etc/pam.d/system-auth должен указывать на system-auth-local (сейчас: ${link:-нет})"
  hint "ln -sfn system-auth-local /etc/pam.d/system-auth"
fi

# --- 5. faillock для lockuser сброшен (20); lockuser — только это задание ---
# Tally не в /run (tmpfs при старте контейнера) — см. PAM dir=/var/lib/os-alt-lab-m6/faillock
FAILOCK_DIR=/var/lib/os-alt-lab-m6/faillock
FAILOCK_BIN="$(command -v faillock 2>/dev/null || true)"
[[ -z "$FAILOCK_BIN" && -x /usr/sbin/faillock ]] && FAILOCK_BIN=/usr/sbin/faillock
[[ -z "$FAILOCK_BIN" && -x /sbin/faillock ]] && FAILOCK_BIN=/sbin/faillock
if [[ -n "$FAILOCK_BIN" ]]; then
  fl=$("$FAILOCK_BIN" --dir "$FAILOCK_DIR" --user lockuser 2>/dev/null || true)
  tally_lines=$(echo "$fl" | grep -c 'TALLY' || true)
  tally_file="$FAILOCK_DIR/lockuser"
  file_ok=1
  if [[ -f "$tally_file" ]] && [[ -s "$tally_file" ]]; then
    file_ok=0
  fi
  if [[ "${tally_lines:-0}" -eq 0 ]] && [[ "$file_ok" -eq 1 ]]; then
    score=$((score + 20))
    ok "задание 5: faillock для lockuser сброшен"
  else
    fail_vis "задание 5: сбросьте блокировку (faillock --dir $FAILOCK_DIR --user lockuser --reset) или дождитесь unlock_time"
    hint "faillock --dir $FAILOCK_DIR --user lockuser --reset   (утилита util-linux; счётчики не в /run)"
  fi
else
  fail_vis "задание 5: нет faillock (ожидается пакет util-linux: /usr/sbin/faillock или /sbin/faillock)"
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
