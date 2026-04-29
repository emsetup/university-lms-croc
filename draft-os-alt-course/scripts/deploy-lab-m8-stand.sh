#!/usr/bin/env bash
# Сборка образа практики модуля 8 на стенде (rsync → SSH → docker build).
# Один Dockerfile (docker/lab-m8): PID1=systemd, auditd через systemctl.
# По умолчанию два тега: os-alt-lab-m8:latest и os-alt-lab-m8-systemd:latest.
# Один тег: LAB_M8_IMAGE=myregistry/m8:1 bash scripts/deploy-lab-m8-stand.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
DOCKERFILE="${LAB_M8_DOCKERFILE:-docker/lab-m8/Dockerfile}"
REMOTE_DIR="${LAB_M8_REMOTE_DIR:-/tmp/os-alt-lab-m8-build}"
REMOTE_DOCKER="${LAB_M8_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

cd "$LCF"
echo "[deploy-m8] rsync -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az --delete \
  --exclude='.git/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='storage/logs/' \
  --exclude='bootstrap/cache/*.php' \
  "$LCF/" "${STAND_SSH}:${REMOTE_DIR}/"

if [[ -n "${LAB_M8_IMAGE:-}" ]]; then
  echo "[deploy-m8] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t ${LAB_M8_IMAGE}"
  ssh "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t '${LAB_M8_IMAGE}' .; ${REMOTE_DOCKER} image ls '${LAB_M8_IMAGE}' | head -5"
  echo "[deploy-m8] готово. practice_lab модуль 8: env('PRACTICE_LAB_IMAGE_M8', '${LAB_M8_IMAGE}')"
else
  echo "[deploy-m8] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-lab-m8:latest -t os-alt-lab-m8-systemd:latest"
  ssh "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-lab-m8:latest -t os-alt-lab-m8-systemd:latest .; ${REMOTE_DOCKER} image ls 'os-alt-lab-m8' | head -8"
  echo "[deploy-m8] готово. Оба тега собраны; в practice_lab подойдёт любой:"
  echo "  '8' => env('PRACTICE_LAB_IMAGE_M8', 'os-alt-lab-m8:latest')"
fi
echo "  Перезапустите lab-daemon после обновления app.py (модуль 8 без sleep PID1, cgroup для auditd)."
