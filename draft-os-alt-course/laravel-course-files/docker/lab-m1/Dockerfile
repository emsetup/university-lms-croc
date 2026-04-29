# Учебный образ модуля 1: серверный профиль ALT (p10), ответы в ~/1.txt...~/4.txt.
# Сборка из корня laravel-course-files:
#   docker build -f docker/lab-m1/Dockerfile -t os-alt-lab-m1:latest .
#
# Базовый образ: registry.altlinux.org/alt/alt:p10
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install \
    branding-alt-server-release \
    vim-console \
    nano \
    glibc-locales \
    systemd \
    sudo \
    grep \
    coreutils \
    procps \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && test -f /etc/altlinux-release \
 && command -v rpm \
 && command -v systemctl

# Режим по умолчанию — как на типичном сервере (без graphical.target).
RUN mkdir -p /etc/systemd/system \
 && (ln -sf /lib/systemd/system/multi-user.target /etc/systemd/system/default.target 2>/dev/null \
     || ln -sf /usr/lib/systemd/system/multi-user.target /etc/systemd/system/default.target)

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
  'if locale -a 2>/dev/null | grep -qi "ru_ru.utf8"; then' \
  '  export LANG=ru_RU.UTF-8 LC_ALL=ru_RU.UTF-8' \
  'fi' \
  'export EDITOR=nano VISUAL=nano' \
  > /etc/profile.d/00-lab-utf8.sh \
 && chmod 644 /etc/profile.d/00-lab-utf8.sh

RUN if ! grep -q '/etc/profile.d' /etc/profile 2>/dev/null; then \
      printf '\n# lab: locale\nfor i in /etc/profile.d/*.sh; do [ -r "$i" ] && . "$i"; done\n' >> /etc/profile; \
    fi

RUN useradd -m -s /bin/bash student || true \
 && echo 'student:labstudy' | chpasswd

COPY docker/lab-m1/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile

RUN printf '%s\n' \
  '# Неинтерактивный запуск: дальше не выполняем' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

RUN mkdir -p /etc/sudoers.d \
 && printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_01/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

USER root
CMD ["sleep", "infinity"]
