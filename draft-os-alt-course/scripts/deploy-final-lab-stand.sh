#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"
REMOTE_DIR="${FINAL_LAB_REMOTE_DIR:-/tmp/os-alt-final-lab-build}"
REMOTE_DOCKER="${FINAL_LAB_REMOTE_DOCKER:-sudo docker}"
DOCKERFILE="docker/final-lab/Dockerfile"

if [[ -z "${STAND_SSH:-}" ]]; then
  echo "Set STAND_SSH, e.g.: export STAND_SSH=emednikov@172.26.76.216" >&2
  exit 1
fi

ssh -o BatchMode=yes "$STAND_SSH" "mkdir -p '${REMOTE_DIR}/docker/final-lab' '${REMOTE_DIR}/examples/practice-checks/final_lab'"

echo "[deploy-final] rsync docker/final-lab + examples/practice-checks/final_lab -> ${STAND_SSH}:${REMOTE_DIR}/"
rsync -az "${LCF}/docker/final-lab/" "${STAND_SSH}:${REMOTE_DIR}/docker/final-lab/"
rsync -az "${LCF}/examples/practice-checks/final_lab/" "${STAND_SSH}:${REMOTE_DIR}/examples/practice-checks/final_lab/"

if [[ -n "${FINAL_LAB_IMAGE:-}" ]]; then
  echo "[deploy-final] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t ${FINAL_LAB_IMAGE}"
  ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t '${FINAL_LAB_IMAGE}' .; ${REMOTE_DOCKER} image ls '${FINAL_LAB_IMAGE}' | head -5"
  echo "[deploy-final] done. image=${FINAL_LAB_IMAGE}"
else
  echo "[deploy-final] remote: ${REMOTE_DOCKER} build -f ${DOCKERFILE} -t os-alt-final-lab:latest"
  ssh -o BatchMode=yes "$STAND_SSH" "set -e; cd '${REMOTE_DIR}'; ${REMOTE_DOCKER} build -f '${DOCKERFILE}' -t os-alt-final-lab:latest .; ${REMOTE_DOCKER} image ls 'os-alt-final-lab' | head -8"
  echo "[deploy-final] done. image=os-alt-final-lab:latest"
fi

