#!/usr/bin/env bash
# Запуск lab-daemon на стенде в Docker (--network host, порт 8090).
# Секрет: PRACTICE_LAB_DAEMON_SECRET из .env Laravel → LAB_DAEMON_SECRET в контейнере.
#
#   export STAND_SSH=emednikov@172.26.76.216
#   bash scripts/start-lab-daemon-stand.sh
#
# Публичный хост для ссылок ttyd в браузере:
#   — явно: STAND_PUBLIC_HOST=172.26.76.216 или PRACTICE_LAB_PUBLIC_HOST в .env Laravel на стенде;
#   — иначе берётся хост из STAND_SSH (user@172.26.76.216 → 172.26.76.216).
# Не используйте 127.0.0.1: браузер студента подключится к его own localhost, а не к стенду.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LCF="$(cd "${SCRIPT_DIR}/../laravel-course-files" && pwd)"

STAND_SSH="${STAND_SSH:?Задайте STAND_SSH=user@host}"
REMOTE_ENV="${LAB_DAEMON_STAND_ENV:-/var/www/os-alt-lab/.env}"
REMOTE_BUILD="${LAB_DAEMON_STAND_BUILD:-/tmp/os-alt-lab-m8-build/lab-daemon}"
NAME="${LAB_DAEMON_CONTAINER:-os-alt-lab-daemon-run}"
IMAGE="${LAB_DAEMON_IMAGE:-os-alt-lab-daemon:latest}"
REMOTE_LARAVEL="${LARAVEL_REMOTE:-/var/www/os-alt-lab}"
EXPORT_DIR="${LAB_IMAGE_EXPORT_DIR:-/var/lib/course-practice-images}"

_ssh_host="${STAND_SSH##*@}"
_ssh_host="${_ssh_host%%:*}"
export DEFAULT_LAB_PUBLIC_HOST="${STAND_PUBLIC_HOST:-${_ssh_host}}"
echo "[start-daemon] LAB_PUBLIC_HOST (ttyd в браузере) будет: ${DEFAULT_LAB_PUBLIC_HOST}"

echo "[start-daemon] rsync lab-daemon -> ${STAND_SSH}:${REMOTE_BUILD}/"
rsync -az --delete \
  "${LCF}/lab-daemon/" \
  "${STAND_SSH}:${REMOTE_BUILD}/"

# Незакрытый delimiter: подставляются только REMOTE_* / NAME / IMAGE в начале; остальное — на стенде (\$).
ssh -o BatchMode=yes "$STAND_SSH" 'bash -s' <<REMOTE
set -euo pipefail
ENVF="${REMOTE_ENV}"
BUILD="${REMOTE_BUILD}"
NAME="${NAME}"
IMAGE="${IMAGE}"
LARAVEL="${REMOTE_LARAVEL}"
EXPORT_DIR="${EXPORT_DIR}"

if [[ ! -f "\$ENVF" ]]; then
  echo "Нет файла \$ENVF" >&2
  exit 1
fi
if ! grep -q '^PRACTICE_LAB_DAEMON_SECRET=' "\$ENVF"; then
  echo "В \$ENVF нет PRACTICE_LAB_DAEMON_SECRET=" >&2
  exit 1
fi

EF=\$(mktemp)
chmod 600 "\$EF"
grep '^PRACTICE_LAB_DAEMON_SECRET=' "\$ENVF" | sed 's/^PRACTICE_LAB_DAEMON_SECRET=/LAB_DAEMON_SECRET=/' > "\$EF"
{
  echo "LAB_TTL_MINUTES=\${LAB_TTL_MINUTES:-480}"
  echo "LAB_ENABLE_TTY=\${LAB_ENABLE_TTY:-1}"
  echo "LAB_TTY_PORT_MIN=\${LAB_TTY_PORT_MIN:-40000}"
  echo "LAB_TTY_PORT_MAX=\${LAB_TTY_PORT_MAX:-41000}"
  echo "LAB_BUILD_WORKDIR=\${LAB_BUILD_WORKDIR:-\$LARAVEL/storage/app}"
  echo "LAB_IMAGE_EXPORT_DIR=\${LAB_IMAGE_EXPORT_DIR:-\$EXPORT_DIR}"
  echo "LAB_BUILD_LOG_MAX_CHARS=\${LAB_BUILD_LOG_MAX_CHARS:-60000}"
} >> "\$EF"
if grep -q '^PRACTICE_LAB_PUBLIC_HOST=' "\$ENVF" 2>/dev/null; then
  grep '^PRACTICE_LAB_PUBLIC_HOST=' "\$ENVF" | sed 's/^PRACTICE_LAB_PUBLIC_HOST=/LAB_PUBLIC_HOST=/' >> "\$EF"
else
  echo "LAB_PUBLIC_HOST=${DEFAULT_LAB_PUBLIC_HOST}" >> "\$EF"
fi

echo "[stand] docker build -t \$IMAGE \$BUILD"
sudo docker build -f "\$BUILD/Dockerfile" -t "\$IMAGE" "\$BUILD/"

sudo docker rm -f "\$NAME" 2>/dev/null || true
sudo mkdir -p "\$LARAVEL/storage/app" "\$EXPORT_DIR"
echo "[stand] docker run \$NAME (lab-daemon: --network host)"
sudo docker run -d --name "\$NAME" --restart unless-stopped \
  --network host \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v "\$LARAVEL/storage/app:\$LARAVEL/storage/app" \
  -v "\$EXPORT_DIR:\$EXPORT_DIR" \
  --env-file "\$EF" \
  "\$IMAGE"
rm -f "\$EF"

sleep 2
curl -sfS "http://127.0.0.1:8090/health"
echo
echo "[stand] lab-daemon: http://127.0.0.1:8090/health"
REMOTE
