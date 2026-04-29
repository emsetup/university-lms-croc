#!/bin/bash
# Запуск journald в фоне; остальная команда из CMD или от docker run — через exec "$@".
set -euo pipefail
mkdir -p /run/systemd/journal /var/log/journal
if [[ -x /lib/systemd/systemd-journald ]]; then
  /lib/systemd/systemd-journald &
  sleep 1
fi
exec "$@"
