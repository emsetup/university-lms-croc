#!/usr/bin/env bash
# Проверка, что расширенная теория модуля 1 на месте (локально или на стенде после выкладки).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
F="$ROOT/config/snippets/module_01_theory.md"
if [[ ! -r "$F" ]]; then
  echo "FAIL: нет файла $F"
  exit 1
fi
BYTES=$(wc -c <"$F")
echo "Файл: $F"
echo "Размер: $BYTES байт (ожидается порядка 15000+ для расширенной версии)"
if grep -q 'Для кого этот модуль' "$F" && grep -q 'Проект "Сизиф"' "$F"; then
  echo "Маркеры текста: OK"
else
  echo "Маркеры текста: FAIL (похоже на старый или обрезанный файл)"
  exit 1
fi
