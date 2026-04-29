#!/usr/bin/env bash
# Сборка образа практики модуля 3 на стенде.
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/deploy-lab-m3-stand.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
DOCKERFILE="docker/lab-m3/Dockerfile"
REMOTE_DIR="${LAB_M3_REMOTE_DIR:-/tmp/os-alt-lab-m3-build}"
REMOTE_DOCKER="${LAB_M3_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE_DIR}/docker/lab-m3' '${REMOTE_DIR}/examples/practice-checks/module_03'"

echo "[deploy-m3] rsync docker/lab-m3 + examples/practice-checks/module_03 -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az "${LCF}/docker/lab-m3/" "${STAND_SSH}:${REMOTE_DIR}/docker/lab-m3/"
rsync -az "${LCF}/examples/practice-checks/module_03/" "${STAND_SSH}:${REMOTE_DIR}/examples/practice-checks/module_03/"

echo "[deploy-m3] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-lab-m3:latest -t os-alt-lab-m3-systemd:latest"
ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-lab-m3:latest -t os-alt-lab-m3-systemd:latest .; ${REMOTE_DOCKER} image ls 'os-alt-lab-m3' | head -8"

echo "[deploy-m3] готово. В .env Laravel: PRACTICE_LAB_IMAGE_3=os-alt-lab-m3-systemd:latest"
