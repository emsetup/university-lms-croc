#!/bin/bash
# Первый старт контейнера: «поломка» лабы M8 (auditd).
set -euo pipefail
STAMP=/var/lib/os-alt-lab-m8/.setup-done
if [[ -f "$STAMP" ]]; then
  exit 0
fi

mkdir -p /var/lib/os-alt-lab-m8
log() { echo "[lab-m8-setup] $*"; }

log "writing auditd.conf (intentionally broken space_left / HALT)"
install -d -m 750 /var/log/audit
install -d -m 755 /etc/audit/plugins.d
# Полный набор директив (как в audit-userspace): иначе auditd в p10 может сразу выходить с ошибкой.
cat > /etc/audit/auditd.conf << 'EOF'
local_events = yes
write_logs = yes
log_file = /var/log/audit/audit.log
log_group = root
log_format = RAW
flush = INCREMENTAL_ASYNC
freq = 50
max_log_file = 8
max_log_file_action = ROTATE
num_logs = 5
priority_boost = 4
name_format = NONE
space_left = 99999
space_left_action = HALT
admin_space_left = 50
admin_space_left_action = SUSPEND
disk_full_action = SUSPEND
disk_error_action = SUSPEND
use_libwrap = no
plugin_dir = /etc/audit/plugins.d
EOF

log "hardening.rules"
cat > /etc/audit/rules.d/hardening.rules << 'EOF'
-b 8192
-f 1
-w /etc/passwd -p wa -k user_accounts
-w /etc/sudoers -p wa -k sudoers_change
-w /etc/pam.d/ -p wa -k pam_config
-w /etc/passwdqc.conf -p wa -k pam_config
-w /etc/tcb/ -p wa -k tcb_change
-a always,exit -F arch=b64 -S unlink,unlinkat -k file_deletion
EOF

log "ssh_watch.rules (wrong path for Alt)"
cat > /etc/audit/rules.d/ssh_watch.rules << 'EOF'
-w /etc/ssh/sshd_config -p wa -k sshd_config_change
EOF

chown root:root /etc/audit/rules.d/*.rules 2>/dev/null || true
chmod 600 /etc/audit/rules.d/*.rules 2>/dev/null || true

log "auditd must be off until the student fixes the lab"
# Не вызываем systemctl до PID1=systemd (в entrypoint «обычного» образа PID1=sleep).
pkill -x auditd 2>/dev/null || true
rm -f /etc/systemd/system/multi-user.target.wants/auditd.service 2>/dev/null || true
rm -f /lib/systemd/system/multi-user.target.wants/auditd.service 2>/dev/null || true

touch "$STAMP"
log "=== Lab M8 setup complete ==="
