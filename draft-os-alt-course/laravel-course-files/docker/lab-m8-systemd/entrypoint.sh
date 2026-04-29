#!/bin/bash
# См. docker/lab-m8/entrypoint.sh (единая точка правки).
set -euo pipefail
if [[ ! -f /var/lib/os-alt-lab-m8/.setup-done ]]; then
  /opt/lab/lab-m8-setup.sh
fi
exec /lib/systemd/systemd
