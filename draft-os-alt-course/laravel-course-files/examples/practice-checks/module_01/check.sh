#!/bin/bash
# Практика модуля 1: 4 файла (~/1.txt ... ~/4.txt). 4 критерия по 25 баллов.
set -uo pipefail

STUDENT_HOME="${STUDENT_HOME:-/home/student}"
F1="${STUDENT_HOME}/1.txt"
F2="${STUDENT_HOME}/2.txt"
F3="${STUDENT_HOME}/3.txt"
F4="${STUDENT_HOME}/4.txt"
MAX=100
score=0

hint() { echo "HINT: $*"; }
ok() { echo "OK: $*"; }
fail_vis() { echo "FAIL: $*"; }

if [[ ! -f "$F1" ]]; then
  fail_vis "нет файла ${F1}"
  hint "Создайте файл 1.txt в домашнем каталоге пользователя student и запишите ответ к заданию 1."
elif [[ ! -r "$F1" ]]; then
  fail_vis "файл ${F1} не читается"
else
  if grep -qi 'ALT' "$F1"; then
    score=$((score + 25))
    ok "задание 1: в 1.txt есть маркер ALT"
  else
    fail_vis "задание 1: в 1.txt не найдено «ALT» (без учёта регистра)"
    hint "Запишите в 1.txt корректные данные о названии/версии из системного файла выпуска."
  fi
fi

if [[ ! -f "$F2" ]]; then
  fail_vis "нет файла ${F2}"
  hint "Создайте файл 2.txt и запишите ответ к заданию 2."
elif [[ ! -r "$F2" ]]; then
  fail_vis "файл ${F2} не читается"
else
  if grep -qiE 'alt-server|alt-workstation|alt-education|altsp' "$F2"; then
    score=$((score + 25))
    ok "задание 2: в 2.txt указан класс продукта (alt-server / alt-workstation / alt-education / altsp)"
  else
    fail_vis "задание 2: в 2.txt нет ни одной из подстрок alt-server, alt-workstation, alt-education, altsp"
    hint "Добавьте в 2.txt маркеры типа продукта по пакетам."
  fi
fi

if [[ ! -f "$F3" ]]; then
  fail_vis "нет файла ${F3}"
  hint "Создайте файл 3.txt и запишите ответ к заданию 3."
elif [[ ! -r "$F3" ]]; then
  fail_vis "файл ${F3} не читается"
else
  if grep -qiE 'multi-user\.target|graphical\.target' "$F3"; then
    score=$((score + 25))
    ok "задание 3: в 3.txt указан target (multi-user или graphical)"
  else
    fail_vis "задание 3: в 3.txt должна быть подстрока multi-user.target или graphical.target"
    hint "Добавьте в 3.txt режим загрузки и вывод по роли системы."
  fi
fi

if [[ ! -f "$F4" ]]; then
  fail_vis "нет файла ${F4}"
  hint "Создайте файл 4.txt и запишите ответ к заданию 4."
elif [[ ! -r "$F4" ]]; then
  fail_vis "файл ${F4} не читается"
else
  if grep -qiE 'x86_64|aarch64|e2k|ppc64le|ppc64' "$F4"; then
    score=$((score + 25))
    ok "задание 4: в 4.txt указана архитектура (x86_64, aarch64, e2k, ppc64…)"
  else
    fail_vis "задание 4: в 4.txt нет распознаваемой архитектуры (x86_64, aarch64, e2k, ppc64…)"
    hint "Добавьте в 4.txt строку с архитектурой и ядром."
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
