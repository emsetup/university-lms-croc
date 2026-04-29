#!/usr/bin/env bash
# Сборка os-alt-lab-m6 и загрузка образа на стенд по SSH (docker load).
# Текст практикума модуля 6 в LMS — отдельно: см. README в docker/lab-m6.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
IMAGE="${LAB_M6_IMAGE:-os-alt-lab-m6:latest}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=root@stand.example" >&2
  exit 1
fi

if ! command -v docker &>/dev/null; then
  echo "Нужен docker в PATH (или Docker Desktop + интеграция с WSL)." >&2
  exit 1
fi

cd "$LCF"
echo "[deploy-m6] build $IMAGE from $LCF"
docker build -f docker/lab-m6/Dockerfile -t "$IMAGE" .

echo "[deploy-m6] docker save | ssh $STAND_SSH docker load"
docker save "$IMAGE" | ssh "$STAND_SSH" docker load

echo "[deploy-m6] на стенде:"
ssh "$STAND_SSH" "docker image ls '$IMAGE' | head -5"

echo "[deploy-m6] готово. Перезапустите lab-daemon / контейнер демона, если он кеширует список образов."
echo "[deploy-m6] текст модуля 6: синхронизируйте config/course.php с репозиторием (блок модуля 6 из scripts/fixtures/course-recovered-from-stand-tgz.php)."
