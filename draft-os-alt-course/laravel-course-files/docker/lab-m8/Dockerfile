# Учебный образ модуля 8: auditd, PID1 = systemd (как на ВМ).
# Сборка из корня laravel-course-files (два тега — см. scripts/deploy-lab-m8-stand.sh):
#   docker build -f docker/lab-m8/Dockerfile -t os-alt-lab-m8:latest -t os-alt-lab-m8-systemd:latest .
#
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install vim-console nano glibc-locales sudo systemd util-linux \
    curl dbus audit openssh-server \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && command -v auditctl \
 && command -v augenrules \
 && test -x /lib/systemd/systemd \
 && test -f /etc/openssh/sshd_config

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

RUN useradd -m -s /bin/bash student || true \
 && echo 'student:labstudy' | chpasswd

COPY docker/lab-m8/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile
RUN printf '%s\n' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

RUN usermod -aG wheel student
RUN printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

RUN mkdir -p /opt/lab /var/lib/os-alt-lab-m8 && chmod 755 /opt/lab /var/lib/os-alt-lab-m8
COPY docker/lab-m8/lab-m8-setup.sh /opt/lab/lab-m8-setup.sh
RUN chmod 755 /opt/lab/lab-m8-setup.sh

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_08/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

COPY docker/lab-m8/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV container=docker
STOPSIGNAL SIGRTMIN+3

USER root
ENTRYPOINT ["/entrypoint.sh"]
