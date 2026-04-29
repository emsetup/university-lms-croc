#!/bin/bash
# PID1 = systemd: один раз «поломка» лабы, затем init как на ВМ.
set -euo pipefail
if [[ ! -f /var/lib/os-alt-lab-m8/.setup-done ]]; then
  /opt/lab/lab-m8-setup.sh
fi
exec /lib/systemd/systemd
