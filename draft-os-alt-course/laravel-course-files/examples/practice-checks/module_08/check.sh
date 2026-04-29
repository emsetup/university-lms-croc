#!/bin/bash
# Модуль 8: одна практическая проверка — только конфиги audit (без запуска auditd в Docker).
# На части хостов ядро не даёт включить аудит из контейнера; задание проверяет корректность правок в файлах.
# Формат: TASK1:PASS|FAIL; RESULT:1:0 ; JSON в конце. Код выхода всегда 0.
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

sl=$(grep -E '^space_left[[:space:]]*=' /etc/audit/auditd.conf 2>/dev/null | head -1 | awk -F= '{gsub(/ /,"",$2); print $2}' || echo '?')
sla=$(grep -E '^space_left_action[[:space:]]*=' /etc/audit/auditd.conf 2>/dev/null | head -1 | awk -F= '{gsub(/^ +/,"",$2); print $2}' | tr -d '\r' || echo '?')
ssh_rule=$(grep -E '^-w[[:space:]]+' /etc/audit/rules.d/ssh_watch.rules 2>/dev/null | head -1 | tr -d '\r' || echo '?')
echo "# lab8-check: space_left=${sl} space_left_action=${sla} ssh_rule_snip=${ssh_rule:0:88}"

t=""
# --- auditd.conf: порог и реакция (учебная политика) ---
sln=""
sln=$(grep -E '^space_left[[:space:]]*=' /etc/audit/auditd.conf 2>/dev/null | head -1 | awk -F= '{gsub(/ /,"",$2); print $2}' | tr -d '\r' || true)
if [[ -z "$sln" ]] || ! [[ "$sln" =~ ^[0-9]+$ ]]; then
  t="в /etc/audit/auditd.conf не удалось прочитать числовой space_left"
elif [[ "$sln" -ge 1000 ]]; then
  t="space_left=${sln} должен быть < 1000 (МБ)"
fi
sla_line=$(grep -E '^space_left_action[[:space:]]*=' /etc/audit/auditd.conf 2>/dev/null | head -1 | tr -d '\r' || true)
if echo "$sla_line" | grep -qi 'HALT'; then
  [[ -n "$t" ]] && t="${t}; "
  t="${t}space_left_action не должен быть HALT"
fi

# --- ssh: путь в Альт ---
if ! grep -qE -- '-w[[:space:]]+/etc/openssh/sshd_config[[:space:]]+-p[[:space:]]+wa[[:space:]]+-k[[:space:]]+sshd_config_change' /etc/audit/rules.d/ssh_watch.rules 2>/dev/null; then
  [[ -n "$t" ]] && t="${t}; "
  t="${t}в ssh_watch.rules нужна строка -w /etc/openssh/sshd_config … -k sshd_config_change (как в шаблоне лабы, с путём Альт)"
fi

# --- набор правил: ключ user_accounts (файл от коллег) ---
if ! grep -qE -- '-w[[:space:]]+/etc/passwd[[:space:]]+-p[[:space:]]+wa[[:space:]]+-k[[:space:]]+user_accounts' /etc/audit/rules.d/hardening.rules 2>/dev/null; then
  [[ -n "$t" ]] && t="${t}; "
  t="${t}в hardening.rules должно быть правило -w /etc/passwd … -k user_accounts"
fi

if [[ -z "$t" ]]; then
  emit_task 1 PASS
  echo "OK: задание 1 — конфигурация audit подготовлена (файлы)"
else
  emit_task 1 FAIL "$t"
  echo "FAIL: задание 1 — $t"
  hint "Проверьте /etc/audit/auditd.conf (space_left, space_left_action) и /etc/audit/rules.d/ssh_watch.rules (путь /etc/openssh/sshd_config в Альт)."
fi

score=$((MAX * passed))
total=$((passed + failed))
[[ "$total" -eq 0 ]] && total=1

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
