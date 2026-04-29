#!/bin/bash
# Один раз при сборке образа: готовит «сломанную» среду для 5 заданий М6.
# Не запускать на рабочей системе вне Docker.
set -uo pipefail

log() { echo "[lab-m6-setup] $*"; }

# Пользователь для проверок
if ! id testuser &>/dev/null; then
  useradd -m -s /bin/bash testuser
fi
echo 'testuser:L@b6pass!' | chpasswd

# Отдельная учётка только для задания 5 (faillock): не пересекается с passwd/chpasswd для testuser в заданиях 1–4.
if ! id lockuser &>/dev/null; then
  useradd -m -s /bin/bash lockuser
fi
echo 'lockuser:L@b6lock!' | chpasswd

# --- Задание 2 (в образе поломка): enforce=none ---
if grep -q '^enforce=' /etc/passwdqc.conf 2>/dev/null; then
  sed -i 's/^enforce=.*/enforce=none/' /etc/passwdqc.conf
else
  printf '\nenforce=none\n' >> /etc/passwdqc.conf
fi

# --- Задание 3 (в образе поломка): min — все классы запрещены ---
if grep -q '^min=' /etc/passwdqc.conf 2>/dev/null; then
  sed -i 's/^min=.*/min=disabled,disabled,disabled,disabled,disabled/' /etc/passwdqc.conf
else
  printf '\nmin=disabled,disabled,disabled,disabled,disabled\n' >> /etc/passwdqc.conf
fi

# --- Задание 1 (в образе поломка): pam_passwdqc указывает на несуществующий файл ---
# Файла /etc/passwdqc.labbroken нет — passwd не может загрузить политику, пока студент не поправит PAM.
# system-auth-local-only не трогаем — нужен для su после починки симлинка (задание 4).
if [[ -f /etc/pam.d/passwd ]]; then
  cat > /etc/pam.d/passwd <<'PAMEOF'
# Учебный сервис passwd: auth/account через локальную цепочку; password — неверный config= (задание 1).
auth     include  system-auth-local
account  include  system-auth-local
password required  pam_passwdqc.so config=/etc/passwdqc.labbroken
password required  pam_tcb.so use_authtok shadow fork nullok write_to=tcb
session  optional pam_permit.so
PAMEOF
  chmod 644 /etc/pam.d/passwd
fi

# --- Задание 4: симлинк ведёт не туда ---
ln -sfn system-auth-common /etc/pam.d/system-auth

# --- Задание 5: набить счётчик faillock для lockuser (не для testuser) ---
if [[ -f /etc/pam.d/system-auth-local-only ]] && ! grep -q 'pam_faillock' /etc/pam.d/system-auth-local-only; then
  # На всякий случай (если базовый файл без faillock)
  sed -i '/pam_tcb.so shadow fork nullok$/i auth     required  pam_faillock.so preauth silent deny=3 unlock_time=300 dir=/var/lib/os-alt-lab-m6/faillock' /etc/pam.d/system-auth-local-only
  sed -i '/pam_tcb.so shadow fork nullok$/a auth     [default=die] pam_faillock.so authfail deny=3 unlock_time=300 dir=/var/lib/os-alt-lab-m6/faillock' /etc/pam.d/system-auth-local-only
fi

# Временно восстановим правильный симлинк, чтобы набить faillock через su
ln -sfn system-auth-local /etc/pam.d/system-auth

# От student (в группе wheel): иначе root→su не спрашивает пароль и faillock не пополняется.
if command -v expect &>/dev/null && id student &>/dev/null && id -nG student | grep -qw wheel; then
  for _i in 1 2 3 4 5; do
    runuser -u student -- expect <<'EOS' || true
set timeout 15
spawn su - lockuser -c exit
expect {
  -re "(?i)(password|пароль)" { send "wrongpass\r"; exp_continue }
  eof
}
EOS
  done
elif command -v expect &>/dev/null; then
  log "Предупреждение: student не в wheel — faillock может остаться пустым, пропуск набивки через su."
else
  for _i in 1 2 3 4 5; do
    echo 'wrongpass' | su - lockuser -c true 2>/dev/null || true
  done
fi

# Вернём «поломку» задания 4
ln -sfn system-auth-common /etc/pam.d/system-auth

log "Готово. $(grep '^enforce=' /etc/passwdqc.conf | head -1 || true)"
log "$(grep '^min=' /etc/passwdqc.conf | head -1 || true)"
log "system-auth -> $(readlink /etc/pam.d/system-auth 2>/dev/null || echo '?')"
echo "=== Контейнер подготовлен для лабораторной работы М6 ==="
