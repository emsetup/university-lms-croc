#!/bin/bash
# Первый старт контейнера (до PID1=systemd): «поломки» лабы M9 (Polkit).
set -euo pipefail
STAMP=/var/lib/os-alt-lab-m9/.setup-done
if [[ -f "$STAMP" ]]; then
  exit 0
fi

mkdir -p /var/lib/os-alt-lab-m9 /etc/polkit-1/rules.d
log() { echo "[lab-m9-setup] $*"; }

log "правило с ошибкой: operators получают NO вместо YES (network-manage)"
cat > /etc/polkit-1/rules.d/10-network-operators.rules << 'EOF'
polkit.addRule(function(action, subject) {
    if (action.id === "ru.altcourse.lab.network-manage" &&
        subject.isInGroup("operators")) {
        return polkit.Result.NO;
    }
});
EOF
chmod 644 /etc/polkit-1/rules.d/10-network-operators.rules

log "задание 2: правила для auditors на system-update студент создаёт сам (20-auditors-update.rules не создаём)"

log "задание 3: ломаем allow_active для ru.altcourse.lab.service-restart в .policy"
if ! grep -q 'ru.altcourse.lab.service-restart' /usr/share/polkit-1/actions/ru.altcourse.lab.policy 2>/dev/null; then
  log "ERROR: нет ru.altcourse.lab.policy с действием service-restart"
  exit 1
fi
perl -0777 -i -pe \
  's/(<action id="ru\.altcourse\.lab\.service-restart">.*?<allow_active>)auth_admin(<\/allow_active>)/${1}no$2/s' \
  /usr/share/polkit-1/actions/ru.altcourse.lab.policy

touch "$STAMP"
log "=== Lab M9 setup complete ==="
