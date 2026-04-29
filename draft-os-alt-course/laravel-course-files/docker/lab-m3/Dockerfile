# Учебный образ модуля 3: ЦУС / Alterator (PID1 = systemd).
# Сборка из корня laravel-course-files:
#   docker build -f docker/lab-m3/Dockerfile -t os-alt-lab-m3:latest -t os-alt-lab-m3-systemd:latest .
#
# Тег *-systemd нужен lab-daemon: иначе контейнер стартует как sleep infinity без systemd.
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install \
    systemd \
    sudo \
    nano \
    vim-console \
    curl \
    iproute2 \
    glibc-locales \
    alterator-fbi \
    alterator-users \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && test -x /lib/systemd/systemd

RUN printf '%s\n' \
  'set meta-flag on' \
  'set input-meta on' \
  'set output-meta on' \
  'set convert-meta off' \
  > /etc/inputrc

ENV LANG=C
ENV LC_ALL=C
RUN printf '%s\n' 'LANG=C' 'LC_ALL=C' >> /etc/environment

RUN printf '%s\n' \
  '#!/bin/sh' \
  'export LANG=C LC_ALL=C LC_CTYPE=C' \
  'export EDITOR=nano VISUAL=nano' \
  > /etc/profile.d/00-lab-utf8.sh \
 && chmod 644 /etc/profile.d/00-lab-utf8.sh

RUN if ! grep -q '/etc/profile.d' /etc/profile 2>/dev/null; then \
      printf '\nfor i in /etc/profile.d/*.sh; do [ -r "$i" ] && . "$i"; done\n' >> /etc/profile; \
    fi

RUN mkdir -p /etc/systemd/system \
 && (ln -sf /lib/systemd/system/multi-user.target /etc/systemd/system/default.target 2>/dev/null \
     || ln -sf /usr/lib/systemd/system/multi-user.target /etc/systemd/system/default.target)

RUN useradd -m -s /bin/bash student 2>/dev/null || true \
 && usermod -aG wheel student 2>/dev/null || true \
 && echo 'student:labstudy' | chpasswd

COPY docker/lab-m3/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile

RUN printf '%s\n' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

RUN mkdir -p /etc/sudoers.d \
 && printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

RUN mkdir -p /opt/lab /var/lib/os-alt-lab-m3 \
 && chmod 755 /opt/lab /var/lib/os-alt-lab-m3

COPY docker/lab-m3/lab-m3-setup.sh /opt/lab/lab-m3-setup.sh
RUN chmod 755 /opt/lab/lab-m3-setup.sh

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_03/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

COPY docker/lab-m3/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV container=docker
STOPSIGNAL SIGRTMIN+3

USER root
ENTRYPOINT ["/entrypoint.sh"]
