# Учебный образ модуля 7: rpm -V и Osec (ОС «Альт» p10).
# «Поломка» при первом старте контейнера: /opt/lab/lab-m7-setup.sh (см. entrypoint;
# unit os-alt-lab-m7-setup.service — для справки, как oneshot).
# Сборка из корня laravel-course-files:
#   docker build -f docker/lab-m7/Dockerfile -t os-alt-lab-m7:latest .
#
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install vim-console nano glibc-locales sudo systemd util-linux shadow-change \
    curl rpm perl perl-Pod-Usage perl-Digest-SHA coreutils osec \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && command -v rpm \
 && command -v osec \
 && command -v chsh \
 && rpm_real=$(command -v rpm) \
 && mv "$rpm_real" /usr/bin/rpm.real \
 && osec_bin=$(command -v osec) \
 && mv "$osec_bin" /usr/bin/osec.real \
 && /usr/bin/osec.real -h | grep -q -- '--file'

COPY docker/lab-m7/files/usr-bin-osec-lab.sh /usr/bin/osec
RUN chmod 755 /usr/bin/osec

COPY docker/lab-m7/files/usr-bin-rpm-lab7.sh /usr/bin/rpm
RUN chmod 755 /usr/bin/rpm

COPY docker/lab-m7/files/etc/profile.d/m7-lab-path.sh /etc/profile.d/m7-lab-path.sh
RUN chmod 644 /etc/profile.d/m7-lab-path.sh

COPY docker/lab-m7/files/etc/systemd/system/os-alt-lab-m7-setup.service /etc/systemd/system/os-alt-lab-m7-setup.service
RUN chmod 644 /etc/systemd/system/os-alt-lab-m7-setup.service

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
COPY docker/lab-m7/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile
RUN printf '%s\n' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

RUN usermod -aG wheel student
RUN printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

RUN mkdir -p /opt/lab /var/lib/os-alt-lab-m7 && chmod 755 /opt/lab /var/lib/os-alt-lab-m7
COPY docker/lab-m7/lab-m7-setup.sh /opt/lab/lab-m7-setup.sh
RUN chmod 755 /opt/lab/lab-m7-setup.sh

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_07/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

COPY docker/lab-m7/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

USER root
ENTRYPOINT ["/entrypoint.sh"]
CMD ["sleep", "infinity"]
