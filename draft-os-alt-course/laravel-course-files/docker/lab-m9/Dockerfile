# Учебный образ модуля 9: Polkit (PID1 = systemd, D-Bus + polkit).
# Сборка из корня laravel-course-files:
#   docker build -f docker/lab-m9/Dockerfile -t os-alt-lab-m9:latest -t os-alt-lab-m9-systemd:latest .
#
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install vim-console nano glibc-locales sudo systemd util-linux \
    curl dbus polkit perl \
 \
 && (apt-get -y install polkit-pkla-compat || true) \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && command -v pkcheck \
 && command -v pkaction \
 && test -x /lib/systemd/systemd

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

RUN groupadd -f operators \
 && groupadd -f auditors \
 && (getent passwd operator >/dev/null || useradd -m -s /bin/bash operator) \
 && (getent passwd auditor >/dev/null || useradd -m -s /bin/bash auditor) \
 && (getent passwd student >/dev/null || useradd -m -s /bin/bash student) \
 && usermod -aG operators operator \
 && usermod -aG auditors auditor \
 && usermod -aG operators student \
 && usermod -aG wheel student \
 && echo 'student:labstudy' | chpasswd \
 && echo 'operator:labstudy' | chpasswd \
 && echo 'auditor:labstudy' | chpasswd

COPY docker/lab-m9/ru.altcourse.lab.policy /usr/share/polkit-1/actions/ru.altcourse.lab.policy
RUN chmod 644 /usr/share/polkit-1/actions/ru.altcourse.lab.policy

COPY docker/lab-m9/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile
RUN printf '%s\n' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

RUN printf '%s\n' \
  'root ALL=(ALL) NOPASSWD:ALL' \
  'student ALL=(ALL) NOPASSWD:ALL' \
  > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

RUN mkdir -p /opt/lab /var/lib/os-alt-lab-m9 && chmod 755 /opt/lab /var/lib/os-alt-lab-m9
COPY docker/lab-m9/lab-m9-setup.sh /opt/lab/lab-m9-setup.sh
RUN chmod 755 /opt/lab/lab-m9-setup.sh

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_09/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

COPY docker/lab-m9/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV container=docker
STOPSIGNAL SIGRTMIN+3

USER root
ENTRYPOINT ["/entrypoint.sh"]
