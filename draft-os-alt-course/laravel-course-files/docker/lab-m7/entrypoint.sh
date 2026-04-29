#!/bin/bash
# journald в фоне (как в М6); первый запуск — «поломка» лабы М7 (аналог systemd oneshot).
set -euo pipefail
mkdir -p /run/systemd/journal /var/log/journal /var/lib/os-alt-lab-m7
if [[ -x /lib/systemd/systemd-journald ]]; then
  /lib/systemd/systemd-journald &
  sleep 1
fi

if [[ ! -f /var/lib/os-alt-lab-m7/.setup-done ]]; then
  /opt/lab/lab-m7-setup.sh
fi

exec "$@"
