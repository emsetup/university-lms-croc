#!/usr/bin/env bash
# Сборка образа практики модуля 1 на стенде (rsync минимального контекста → SSH → docker build).
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/deploy-lab-m1-stand.sh
#
# Один тег: LAB_M1_IMAGE=myregistry/m1:1 bash scripts/deploy-lab-m1-stand.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
DOCKERFILE="docker/lab-m1/Dockerfile"
REMOTE_DIR="${LAB_M1_REMOTE_DIR:-/tmp/os-alt-lab-m1-build}"
REMOTE_DOCKER="${LAB_M1_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE_DIR}/docker/lab-m1' '${REMOTE_DIR}/examples/practice-checks/module_01'"

echo "[deploy-m1] rsync docker/lab-m1 + examples/practice-checks/module_01 -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az "${LCF}/docker/lab-m1/" "${STAND_SSH}:${REMOTE_DIR}/docker/lab-m1/"
rsync -az "${LCF}/examples/practice-checks/module_01/" "${STAND_SSH}:${REMOTE_DIR}/examples/practice-checks/module_01/"

if [[ -n "${LAB_M1_IMAGE:-}" ]]; then
  echo "[deploy-m1] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t ${LAB_M1_IMAGE}"
  ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t '${LAB_M1_IMAGE}' .; ${REMOTE_DOCKER} image ls '${LAB_M1_IMAGE}' | head -5"
  echo "[deploy-m1] готово. В .env Laravel: PRACTICE_LAB_IMAGE_1=${LAB_M1_IMAGE}"
else
  echo "[deploy-m1] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-lab-m1:latest"
  ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-lab-m1:latest .; ${REMOTE_DOCKER} image ls 'os-alt-lab-m1' | head -8"
  echo "[deploy-m1] готово. В .env Laravel при необходимости: PRACTICE_LAB_IMAGE_1=os-alt-lab-m1:latest"
fi
