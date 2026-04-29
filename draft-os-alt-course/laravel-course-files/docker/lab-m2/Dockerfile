# Учебный образ модуля 2: репозитории и пакеты (ОС «Альт» p10).
# Сборка из корня laravel-course-files:
#   docker build -f docker/lab-m2/Dockerfile -t os-alt-lab-m2:latest .
FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install \
    nano \
    passwd \
    sudo \
    apt-repo \
    eepm \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && test -f /etc/altlinux-release \
 && command -v rpm \
 && command -v apt-get \
 && command -v apt-cache \
 && command -v apt-repo \
 && command -v epm

# Репозитории в .d для практики модуля 2.
RUN mkdir -p /etc/apt/sources.list.d \
 && cp -f /etc/apt/sources.list /etc/apt/sources.list.d/p10-main.list

RUN printf '%s\n' \
  'set meta-flag on' \
  'set input-meta on' \
  'set output-meta on' \
  'set convert-meta off' \
  > /etc/inputrc

ENV LANG=C
ENV LC_ALL=C
RUN printf '%s\n' 'LANG=C' 'LC_ALL=C' >> /etc/environment
ENV EDITOR=nano
ENV VISUAL=nano

RUN printf '%s\n' \
  '#!/bin/sh' \
  'export LANG=C LC_ALL=C LC_CTYPE=C' \
  'export EDITOR=nano VISUAL=nano' \
  > /etc/profile.d/00-lab-utf8.sh \
 && chmod 644 /etc/profile.d/00-lab-utf8.sh

# Совместимость с учебной командой rpm -qf /usr/bin/passwd.
RUN P="$(command -v passwd || true)" \
 && if [ -n "$P" ] && [ "$P" != "/usr/bin/passwd" ]; then ln -sf "$P" /usr/bin/passwd; fi

RUN if ! grep -q '/etc/profile.d' /etc/profile 2>/dev/null; then \
      printf '\nfor i in /etc/profile.d/*.sh; do [ -r "$i" ] && . "$i"; done\n' >> /etc/profile; \
    fi

RUN useradd -m -s /bin/bash student || true \
 && echo 'student:labstudy' | chpasswd
RUN usermod -aG wheel student

COPY docker/lab-m2/home-student-bash_profile /home/student/.bash_profile
RUN chown student:student /home/student/.bash_profile

RUN printf '%s\n' \
  'case $- in *i*) ;; *) return 0 ;; esac' \
  > /home/student/.bashrc \
 && chown student:student /home/student/.bashrc

RUN mkdir -p /etc/sudoers.d \
 && printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

# Обертка для учебного сценария: rpm --restore nano восстанавливает пакет через reinstall.
RUN mv /usr/bin/rpm /usr/bin/rpm.real
COPY docker/lab-m2/files/usr-bin-rpm-lab2.sh /usr/bin/rpm
RUN chmod 755 /usr/bin/rpm

# Подготовка "поломок" модуля 2.
COPY docker/lab-m2/lab-m2-setup.sh /tmp/lab-m2-setup.sh
RUN chmod +x /tmp/lab-m2-setup.sh && /tmp/lab-m2-setup.sh && rm -f /tmp/lab-m2-setup.sh

RUN mkdir -p /opt/lab-check && chown root:root /opt/lab-check
COPY examples/practice-checks/module_02/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

USER root
CMD ["sleep", "infinity"]
