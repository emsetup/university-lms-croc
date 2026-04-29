#!/usr/bin/env bash
# Сборка os-alt-lab-m7 на стенде: rsync laravel-course-files → SSH → docker build.
# Альтернатива (локальный docker): см. docker/lab-m7/README.md и pipe docker save|ssh load.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
IMAGE="${LAB_M7_IMAGE:-os-alt-lab-m7:latest}"
REMOTE_DIR="${LAB_M7_REMOTE_DIR:-/tmp/os-alt-lab-m7-build}"
# На стенде часто нужен sudo (нет группы docker): export LAB_M7_REMOTE_DOCKER='sudo docker'
REMOTE_DOCKER="${LAB_M7_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

cd "$LCF"
echo "[deploy-m7] rsync -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az --delete \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='storage/logs/' \
  --exclude='bootstrap/cache/*.php' \
  "$LCF/" "${STAND_SSH}:${REMOTE_DIR}/"

echo "[deploy-m7] remote: ${REMOTE_DOCKER} build -t $IMAGE"
ssh "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f docker/lab-m7/Dockerfile -t '${IMAGE}' .; ${REMOTE_DOCKER} image ls '${IMAGE}' | head -5"

echo "[deploy-m7] готово. В .env и practice_lab.php добавьте модуль 7, если ещё нет:"
echo "  PRACTICE_LAB_IMAGE_7 или (как на части стендов) PRACTICE_LAB_IMAGE_M7=${IMAGE}"
echo "  ключ '7' => env(..., '${IMAGE}') и php artisan config:clear"
