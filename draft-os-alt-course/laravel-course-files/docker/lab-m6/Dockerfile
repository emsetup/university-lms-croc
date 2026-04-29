# Учебный образ модуля 6: PAM, passwdqc, TCB, faillock (ОС «Альт» p10).
# Сборка из корня laravel-course-files:
#   docker build -f docker/lab-m6/Dockerfile -t os-alt-lab-m6:latest .
#
# Если apt не находит пакет (имя в ветке p10 другое), поправьте список в RUN ниже.
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install vim-console nano glibc-locales expect su passwd sudo systemd \
    util-linux pam pam0_passwdqc pam0_tcb passwdqc-utils tcb-utils \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && command -v pwqcheck \
 && command -v faillock \
 && command -v su \
 && command -v passwd \
 && command -v sudo \
 && command -v journalctl \
 && test -f /lib64/security/pam_faillock.so

# Счётчики faillock не в /var/run: при старте контейнера /run чаще tmpfs, слой образа не виден.
RUN mkdir -p /var/lib/os-alt-lab-m6/faillock && chmod 755 /var/lib/os-alt-lab-m6 /var/lib/os-alt-lab-m6/faillock

COPY docker/lab-m6/files/etc/profile.d/m6-lab-path.sh /etc/profile.d/m6-lab-path.sh
RUN chmod 644 /etc/profile.d/m6-lab-path.sh

RUN printf '%s\n' \
  'set meta-flag on' \
  'set input-meta on' \
  'set output-meta on' \
  'set convert-meta off' \
  > /etc/inputrc

ENV LANG=C.UTF-8
ENV LC_ALL=C.UTF-8
RUN printf '%s\n' 'LANG=C.UTF-8' 'LC_ALL=C.UTF-8' >> /etc/environment
ENV EDITOR=nano
ENV VISUAL=nano

RUN printf '%s\n' \
  '#!/bin/sh' \
  'export LANG=C.UTF-8 LC_ALL=C.UTF-8 LC_CTYPE=C.UTF-8' \
  'export EDITOR=nano VISUAL=nano' \
  > /etc/profile.d/00-lab-utf8.sh \
 && chmod 644 /etc/profile.d/00-lab-utf8.sh

RUN if ! grep -q '/etc/profile.d' /etc/profile 2>/dev/null; then \
      printf '\nfor i in /etc/profile.d/*.sh; do [ -r "$i" ] && . "$i"; done\n' >> /etc/profile; \
    fi

# Базовая цепочка PAM + passwdqc (до «поломки»)
COPY docker/lab-m6/files/etc/passwdqc.conf /etc/passwdqc.conf
COPY docker/lab-m6/files/etc/pam.d/system-auth-common /etc/pam.d/system-auth-common
COPY docker/lab-m6/files/etc/pam.d/system-auth-local-only /etc/pam.d/system-auth-local-only
COPY docker/lab-m6/files/etc/pam.d/system-auth-local /etc/pam.d/system-auth-local
COPY docker/lab-m6/files/etc/pam.d/passwd /etc/pam.d/passwd
COPY docker/lab-m6/files/etc/pam.d/su /etc/pam.d/su
RUN chmod 644 /etc/passwdqc.conf /etc/pam.d/system-auth-common /etc/pam.d/system-auth-local-only \
    /etc/pam.d/system-auth-local /etc/pam.d/passwd /etc/pam.d/su \
 && ln -sfn system-auth-local /etc/pam.d/system-auth

# Пользователь student + приветствие
RUN useradd -m -s /bin/bash student || true \
 && echo 'student:labstudy' | chpasswd
COPY docker/lab-m6/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile
RUN printf '%s\n' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

# /bin/su в ALT только для wheel; для набивки faillock su должен идти не от root (иначе пароль не спрашивают).
RUN usermod -aG wheel student

RUN printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

# su пишет в wtmp; в минимальном образе файла нет — без него su завершается до ввода пароля.
RUN touch /var/log/wtmp /var/log/btmp \
 && chmod 666 /var/log/wtmp /var/log/btmp

# «Поломка» среды для 5 заданий (один проход при сборке)
COPY docker/lab-m6/scripts/lab-m6-setup.sh /tmp/lab-m6-setup.sh
RUN chmod +x /tmp/lab-m6-setup.sh && /tmp/lab-m6-setup.sh && rm -f /tmp/lab-m6-setup.sh

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_06/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

COPY docker/lab-m6/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER root
ENTRYPOINT ["/entrypoint.sh"]
CMD ["sleep", "infinity"]
