#!/usr/bin/env bash
# Сборка образа практики модуля 5 на стенде.
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/deploy-lab-m5-stand.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
DOCKERFILE="docker/lab-m5/Dockerfile"
REMOTE_DIR="${LAB_M5_REMOTE_DIR:-/tmp/os-alt-lab-m5-build}"
REMOTE_DOCKER="${LAB_M5_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE_DIR}/docker/lab-m5/files' '${REMOTE_DIR}/examples/practice-checks/module_05'"

echo "[deploy-m5] rsync docker/lab-m5 + examples/practice-checks/module_05 -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az "${LCF}/docker/lab-m5/" "${STAND_SSH}:${REMOTE_DIR}/docker/lab-m5/"
rsync -az "${LCF}/examples/practice-checks/module_05/" "${STAND_SSH}:${REMOTE_DIR}/examples/practice-checks/module_05/"

echo "[deploy-m5] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-lab-m5:latest -t os-alt-lab-m5-systemd:latest"
ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-lab-m5:latest -t os-alt-lab-m5-systemd:latest .; ${REMOTE_DOCKER} image ls 'os-alt-lab-m5' | head -8"

echo "[deploy-m5] готово. В .env Laravel: PRACTICE_LAB_IMAGE_5=os-alt-lab-m5-systemd:latest"
