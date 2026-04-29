#!/bin/bash
# Практика модуля 2: репозитории и пакеты. 7 критериев, max 100.
set -uo pipefail

STUDENT_HOME="${STUDENT_HOME:-/home/student}"
F1="${STUDENT_HOME}/lab-m2-task1.txt"
F5="${STUDENT_HOME}/lab-m2-task5.txt"
MAX=100
score=0

hint() { echo "HINT: $*"; }
ok() { echo "OK: $*"; }
fail_vis() { echo "FAIL: $*"; }

# Кнопка «Проверить»: lab-daemon запускает docker exec без -u → root; sudo без TTY часто падает.
# От student вручную — без root, нужен sudo.
apt_get_update_quiet() {
  if [[ "$(id -u)" -eq 0 ]]; then
    apt-get update -qq >/dev/null 2>&1
  else
    sudo apt-get update -qq >/dev/null 2>&1
  fi
}

# 1) task1 file exists and has package owner info.
if [[ ! -s "$F1" ]]; then
  fail_vis "задание 1: нет файла ${F1} или он пустой"
  hint "Создайте файл lab-m2-task1.txt с выводом rpm -qf и rpm -qi."
else
  if grep -qiE 'passwd|shadow' "$F1"; then
    score=$((score + 15))
    ok "задание 1: lab-m2-task1.txt содержит имя пакета"
  else
    fail_vis "задание 1: в ${F1} не найдено упоминание passwd/shadow"
  fi
fi

# 2) sources typo fixed.
if grep -Rqs 'htp://' /etc/apt/sources.list.d/; then
  fail_vis "задание 2: в sources.list.d все еще есть htp://"
  hint "Исправьте опечатку htp:// -> http:// в .list файле."
else
  score=$((score + 20))
  ok "задание 2: опечатка в репозитории устранена"
fi

# 3) apt update works.
if apt_get_update_quiet; then
  score=$((score + 20))
  ok "задание 2: apt-get update выполняется успешно"
else
  fail_vis "задание 2: apt-get update завершился с ошибкой"
  hint "От student выполните: sudo apt-get update (репозитории без опечаток)."
fi

# 4) nano package integrity restored.
rpm_v_nano="$(rpm -V nano 2>/dev/null || true)"
if [[ -z "$rpm_v_nano" ]]; then
  score=$((score + 15))
  ok "задание 3: rpm -V nano чистый"
else
  fail_vis "задание 3: nano все еще изменен по rpm -V"
  hint "Выполните sudo apt-get install --reinstall -y nano и проверьте снова rpm -V nano."
fi

# 5) htop installed and runnable.
if rpm -q htop >/dev/null 2>&1 && htop --version >/dev/null 2>&1; then
  score=$((score + 15))
  ok "задание 4: htop установлен и запускается"
else
  fail_vis "задание 4: htop не установлен или не запускается"
fi

# 6) task5 file with package+version.
if [[ ! -s "$F5" ]]; then
  fail_vis "задание 5: нет файла ${F5} или он пустой"
  hint "Сохраните Package/Version для git в lab-m2-task5.txt."
else
  if grep -qi '^Package:[[:space:]]*git' "$F5"; then
    score=$((score + 8))
    ok "задание 5: в task5 есть Package: git"
  else
    fail_vis "задание 5: в task5 нет строки Package: git"
  fi
  if grep -qi '^Version:' "$F5"; then
    score=$((score + 7))
    ok "задание 5: в task5 есть строка Version"
  else
    fail_vis "задание 5: в task5 нет строки Version"
  fi
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
