#!/usr/bin/env bash
# Сборка образа практики модуля 2 на стенде (rsync минимального контекста -> SSH -> docker build).
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/deploy-lab-m2-stand.sh
#
# Один тег: LAB_M2_IMAGE=myregistry/m2:1 bash scripts/deploy-lab-m2-stand.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
DOCKERFILE="docker/lab-m2/Dockerfile"
REMOTE_DIR="${LAB_M2_REMOTE_DIR:-/tmp/os-alt-lab-m2-build}"
REMOTE_DOCKER="${LAB_M2_REMOTE_DOCKER:-sudo docker}"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Задайте STAND_SSH, например: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

if ! command -v rsync &>/dev/null; then
  echo "Нужен rsync в PATH." >&2
  exit 1
fi

ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE_DIR}/docker/lab-m2/files' '${REMOTE_DIR}/examples/practice-checks/module_02'"

echo "[deploy-m2] rsync docker/lab-m2 + examples/practice-checks/module_02 -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az "${LCF}/docker/lab-m2/" "${STAND_SSH}:${REMOTE_DIR}/docker/lab-m2/"
rsync -az "${LCF}/examples/practice-checks/module_02/" "${STAND_SSH}:${REMOTE_DIR}/examples/practice-checks/module_02/"

if [[ -n "${LAB_M2_IMAGE:-}" ]]; then
  echo "[deploy-m2] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t ${LAB_M2_IMAGE}"
  ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t '${LAB_M2_IMAGE}' .; ${REMOTE_DOCKER} image ls '${LAB_M2_IMAGE}' | head -5"
  echo "[deploy-m2] готово. В .env Laravel: PRACTICE_LAB_IMAGE_2=${LAB_M2_IMAGE}"
else
  echo "[deploy-m2] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-lab-m2:latest"
  ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-lab-m2:latest .; ${REMOTE_DOCKER} image ls 'os-alt-lab-m2' | head -8"
  echo "[deploy-m2] готово. В .env Laravel при необходимости: PRACTICE_LAB_IMAGE_2=os-alt-lab-m2:latest"
fi
