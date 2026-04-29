#!/usr/bin/env bash
# Сборка образа практики модуля 9 (Polkit, модуль ролей и control) на стенде (rsync → SSH → docker build).
# Теги по умолчанию: os-alt-lab-m9:latest и os-alt-lab-m9-systemd:latest.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
DOCKERFILE="${LAB_M9_DOCKERFILE:-docker/lab-m9/Dockerfile}"
REMOTE_DIR="${LAB_M9_REMOTE_DIR:-/tmp/os-alt-lab-m9-build}"
REMOTE_DOCKER="${LAB_M9_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

cd "$LCF"
echo "[deploy-m9] rsync -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az --delete \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='storage/logs/' \
  --exclude='bootstrap/cache/*.php' \
  "$LCF/" "${STAND_SSH}:${REMOTE_DIR}/"

if [[ -n "${LAB_M9_IMAGE:-}" ]]; then
  echo "[deploy-m9] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t ${LAB_M9_IMAGE}"
  ssh "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t '${LAB_M9_IMAGE}' .; ${REMOTE_DOCKER} image ls '${LAB_M9_IMAGE}' | head -5"
  echo "[deploy-m9] готово. practice_lab: env('PRACTICE_LAB_IMAGE_9', '${LAB_M9_IMAGE}')"
else
  echo "[deploy-m9] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-lab-m9:latest -t os-alt-lab-m9-systemd:latest"
  ssh "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-lab-m9:latest -t os-alt-lab-m9-systemd:latest .; ${REMOTE_DOCKER} image ls 'os-alt-lab-m9' | head -8"
  echo "[deploy-m9] готово. В practice_lab:"
  echo "  '9' => env('PRACTICE_LAB_IMAGE_9', 'os-alt-lab-m9:latest')"
fi
echo "  Модуль 9: lab-daemon поднимает контейнер с systemd (как модуль 8). После обновления lab-daemon перезапустите при изменении app.py."
