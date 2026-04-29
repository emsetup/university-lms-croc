#!/bin/bash
# PID1 = systemd: один раз lab-m3-setup, затем init.
set -euo pipefail
if [[ ! -f /var/lib/os-alt-lab-m3/.setup-done ]]; then
  /opt/lab/lab-m3-setup.sh
fi
exec /lib/systemd/systemd
