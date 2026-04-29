#!/bin/bash
# PID1 = systemd: один раз lab-m5-setup, затем init.
set -euo pipefail
if [[ ! -f /var/lib/os-alt-lab-m5/.setup-done ]]; then
  /opt/lab/lab-m5-setup.sh
fi
exec /lib/systemd/systemd
