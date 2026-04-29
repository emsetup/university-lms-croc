#!/usr/bin/env bash
# Публикация на стенде всего по модулю 9 (Polkit, модуль ролей и control): образ Docker M9,
# материалы Laravel (сниппеты), пересборка и перезапуск lab-daemon (systemd в контейнере).
#
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/deploy-module-9-stand-all.sh
set -euo pipefail
STAND_SSH="${STAND_SSH:?Задайте STAND_SSH=user@host}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

echo "=== [1/3] Docker os-alt-lab-m9 ==="
bash scripts/deploy-lab-m9-stand.sh

echo "=== [2/3] Laravel: snippets, practice_lab.php, practice.blade.php ==="
bash scripts/deploy-laravel-stand.sh

echo "=== [3/3] lab-daemon (образ + контейнер os-alt-lab-daemon-run) ==="
bash scripts/start-lab-daemon-stand.sh

echo ""
echo "Готово. На стенде проверьте в .env Laravel:"
echo "  PRACTICE_LAB_IMAGE_9=os-alt-lab-m9:latest   (или ваш тег)"
echo "  PRACTICE_LAB_ENABLED=true и секрет демона — как раньше."
echo "В config/course.php модуль 9: theory @snippet:module_09_theory.md, practice и theory_quiz из config/snippets/ (см. fixtures)."
