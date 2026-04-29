#!/bin/bash
# Модуль 9: Polkit — три задания (правила + .policy), формат как в M6–M8.
# Выход всегда 0; баллы в JSON.
set -uo pipefail
export PATH="/usr/sbin:/sbin:/usr/bin:/bin"

# Чтение /etc/polkit-1/rules.d и restart polkit требуют root. Без root при chmod
# каталога 0700 [[ -f ... ]] даёт «нет файла», хотя правила есть и pkcheck под sudo работает.
if [[ "${EUID:-$(id -u)}" -ne 0 ]] && command -v sudo >/dev/null 2>&1; then
  echo "# lab9-check: перезапуск под root (sudo) — так надёжно читать rules.d и перезапускать polkit" >&2
  exec sudo "$0" "$@"
fi
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "# lab9-check: ERROR: нужен root. Запустите: sudo $0" >&2
  echo "===PRACTICE_RESULT_JSON==="
  echo '{"score":0,"max":100,"tasks_passed":0,"tasks_failed":3}'
  exit 0
fi

MAX=100
passed=0
failed=0
hint() { echo "HINT: $*"; }

# Частая ошибка: копирование из Markdown-предпросмотра даёт [action.id](http...) вместо action.id
_rules_markdown_corruption() {
  local f="$1"
  [[ -f "$f" ]] || return 1
  grep -qE '\]\(https?://|\[action\.id\]|\[polkit\.Result|\[subject\.' "$f" 2>/dev/null
}

# polkit 0.120 (образ лабы): pkcheck без --process → «Subject not specified»; флага
# --user у pkcheck в этой версии нет — только sudo -u <login> sh -c '… --process $$'.
# После systemctl restart polkit pkcheck часто «гонится» с D-Bus: без паузы ручная
# проверка через минуту даёт 0, а первая серия pkcheck в скрипте — ещё нет.
_polkit_dbus_ready() {
  local i
  if command -v busctl >/dev/null 2>&1; then
    for i in $(seq 1 80); do
      busctl --system status org.freedesktop.PolicyKit1 &>/dev/null && return 0
      sleep 0.15
    done
  fi
  return 0
}

# От root: в образе M9 в sudoers только student — «sudo -u student» даёт «root is not in sudoers».
# runuser (util-linux) не использует sudoers.
_pkcheck_run_as() {
  local usr="$1"
  shift
  if [[ $(id -u) -eq 0 ]] && command -v runuser >/dev/null 2>&1; then
    runuser -u "$usr" -- "$@"
  else
    sudo -u "$usr" -- "$@"
  fi
}

pkcheck_ok() {
  local usr="$1" aid="$2" attempt
  for attempt in $(seq 1 12); do
    if _pkcheck_run_as "$usr" sh -c 'pkcheck --action-id '"$aid"' --process $$' >/dev/null 2>&1; then
      return 0
    fi
    sleep 1
  done
  return 1
}

emit_task() {
  local n="$1" st="$2" msg="${3:-}"
  echo "TASK${n}:${st}${msg:+:${msg}}"
  if [[ "$st" == PASS ]]; then
    passed=$((passed + 1))
  else
    failed=$((failed + 1))
  fi
}

systemctl restart polkit 2>/dev/null || true
sleep 3
_polkit_dbus_ready
sleep 1

echo "# lab9-check: polkit active=$(systemctl is-active polkit 2>/dev/null || echo '?')"

t1=""
f10="/etc/polkit-1/rules.d/10-network-operators.rules"
if [[ ! -f "$f10" ]]; then
  t1="нет файла $f10"
elif _rules_markdown_corruption "$f10"; then
  t1="в $f10 похоже на Markdown-ссылки [текст](http...) вместо кода polkit — наберите вручную: action.id и return polkit.Result.YES (не копируйте из предпросмотра курса)"
elif grep -qE 'return[[:space:]]+polkit\.Result\.NO' "$f10" 2>/dev/null; then
  t1="в $f10 всё ещё return polkit.Result.NO — замените на YES"
elif ! grep -q 'ru\.altcourse\.lab\.network-manage' "$f10" 2>/dev/null; then
  t1="в $f10 нет action id ru.altcourse.lab.network-manage"
elif ! grep -q 'polkit\.Result\.YES' "$f10" 2>/dev/null; then
  t1="в $f10 должно быть polkit.Result.YES для группы operators"
elif ! grep -q 'operators' "$f10" 2>/dev/null; then
  t1="в $f10 должна проверяться группа operators"
else
  if ! pkcheck_ok student ru.altcourse.lab.network-manage; then
    t1="pkcheck от student для ru.altcourse.lab.network-manage не авторизует (в $f10 должны быть литералы action.id и return polkit.Result.YES без «умных» кавычек и без […](http…); journalctl -u polkit на ошибки JS; проверка: sudo -u student sh -c 'pkcheck --action-id ru.altcourse.lab.network-manage --process \$\$')"
  fi
fi

t2=""
f20="/etc/polkit-1/rules.d/20-auditors-update.rules"
if [[ ! -f "$f20" ]]; then
  t2="нет файла $f20 (в образе лабы он не создаётся — только задание 1; создайте: sudo nano $f20 и polkit.addRule для auditors + ru.altcourse.lab.system-update)"
elif _rules_markdown_corruption "$f20"; then
  t2="в $f20 обнаружен Markdown вместо JavaScript (например [action.id](http...) вместо action.id) — polkit не применит правило; исправьте в nano и перезапустите polkit"
elif ! grep -qF 'ru.altcourse.lab.system-update' "$f20" 2>/dev/null; then
  t2="в $f20 нет строки с ru.altcourse.lab.system-update"
elif ! grep -q 'auditors' "$f20" 2>/dev/null; then
  t2="в $f20 нет проверки группы auditors"
elif ! grep -q 'polkit\.Result\.YES' "$f20" 2>/dev/null; then
  t2="в $f20 должно быть polkit.Result.YES"
else
  if ! pkcheck_ok auditor ru.altcourse.lab.system-update; then
    t2="pkcheck от auditor для ru.altcourse.lab.system-update не авторизует"
  fi
fi

t3=""
pol="/usr/share/polkit-1/actions/ru.altcourse.lab.policy"
if [[ ! -f "$pol" ]]; then
  t3="нет файла $pol"
else
  blk=$(sed -n '/<action id="ru.altcourse.lab.service-restart"/,/<\/action>/p' "$pol" 2>/dev/null || true)
  if ! echo "$blk" | grep -q '<allow_active>auth_admin</allow_active>'; then
    t3="в действии ru.altcourse.lab.service-restart нужно <allow_active>auth_admin</allow_active> (сейчас не auth_admin)"
  fi
fi

if [[ -z "$t1" ]]; then
  emit_task 1 PASS
  echo "OK: задание 1 — правило network-manage для operators"
else
  emit_task 1 FAIL "$t1"
  echo "FAIL: задание 1 — $t1"
  if [[ "$t1" == *Markdown* ]]; then
    hint "nano $f10; удалите […](http…); должно быть: if (action.id === \"ru.altcourse.lab.network-manage\" && subject.isInGroup(\"operators\")) { return polkit.Result.YES; }"
  else
    hint "cat $f10; исправьте return на polkit.Result.YES; sudo systemctl restart polkit"
  fi
fi

if [[ -z "$t2" ]]; then
  emit_task 2 PASS
  echo "OK: задание 2 — правило для auditors (system-update)"
else
  emit_task 2 FAIL "$t2"
  echo "FAIL: задание 2 — $t2"
  if [[ "$t2" == *Markdown* ]]; then
    hint "nano $f20; строка if должна быть: action.id === \"ru.altcourse.lab.system-update\" — без квадратных скобок и (http…); затем systemctl restart polkit"
  elif [[ "$t2" == *pkcheck* ]]; then
    hint "cat $f20; проверьте JS (auditors, YES, точное имя действия); journalctl -u polkit -n 20; sudo systemctl restart polkit"
  else
    hint "Создайте $f20 с polkit.addRule для ru.altcourse.lab.system-update и группы auditors; sudo systemctl restart polkit"
  fi
fi

if [[ -z "$t3" ]]; then
  emit_task 3 PASS
  echo "OK: задание 3 — allow_active auth_admin для service-restart"
else
  emit_task 3 FAIL "$t3"
  echo "FAIL: задание 3 — $t3"
  hint "grep -n service-restart $pol; верните <allow_active>auth_admin</allow_active>; sudo systemctl restart polkit"
fi

score=$((100 * passed / 3))
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
