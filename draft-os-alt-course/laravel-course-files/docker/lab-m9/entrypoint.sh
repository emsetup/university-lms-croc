#!/bin/bash
# PID1 = systemd: один раз lab-m9-setup, затем init.
set -euo pipefail
if [[ ! -f /var/lib/os-alt-lab-m9/.setup-done ]]; then
  /opt/lab/lab-m9-setup.sh
fi
exec /lib/systemd/systemd
