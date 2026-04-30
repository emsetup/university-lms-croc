FROM registry.altlinux.org/alt/alt:p10

RUN apt-get -y update \
 && apt-get -y install \
    branding-alt-server-release \
    systemd \
    dbus \
    shadow-utils \
    rpm \
    sudo \
    passwd \
    pam \
    pam0_passwdqc \
    pam0_tcb \
    tcb-utils \
    control \
    libnss-role \
    polkit \
    alterator-fbi \
    nano \
    sed \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && test -f /etc/altlinux-release \
 && command -v systemctl \
 && command -v control \
 && command -v pkaction \
 && test -x /lib/systemd/systemd

RUN useradd -m -s /bin/bash student || true \
 && useradd -m -s /bin/bash testuser || true \
 && echo 'testuser:TestPass123!' | chpasswd \
 && usermod -aG wheel student \
 && mkdir -p /etc/sudoers.d \
 && printf '%s\n' 'student ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/student-lab \
 && chmod 440 /etc/sudoers.d/student-lab

RUN mkdir -p /etc/systemd/system \
 && (ln -sf /lib/systemd/system/multi-user.target /etc/systemd/system/default.target 2>/dev/null \
     || ln -sf /usr/lib/systemd/system/multi-user.target /etc/systemd/system/default.target)

RUN mkdir -p /opt/lab-check \
 && touch /root/exam-report.txt \
 && chmod 600 /root/exam-report.txt \
 && systemctl disable --now alteratord 2>/dev/null || true \
 && systemctl disable --now ahttpd 2>/dev/null || true

# Свести к эталону RPM «шум» от postinst/слоя сборки (mime, glib, sudo, …), не трогая дальнейшую подмену passwdqc.
RUN apt-get -y update \
 && apt-get -y install --reinstall \
    shared-mime-info \
    glib2 \
    sudo \
    util-linux \
    rpm \
    setup \
    branding-alt-server-release \
    systemd \
    vixie-cron \
    crontabs \
    at \
    nano \
    sed \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial

# Задание 3: ослабить passwdqc — первая позиция min не disabled (допускаются одноклассовые пароли),
# enforce=users (политика не для root). Сдающий должен привести к min=disabled,... и enforce=everyone.
RUN cat > /etc/passwdqc.conf <<'EOF'
min=8,24,11,8,7
max=40
passphrase=3
match=4
similar=permit
random=42
enforce=users
retry=3
EOF

# Задание 4: снова выровнять nano/sed по RPM, затем единственные целенапорченные файлы — проверка на сборке.
RUN apt-get -y update \
 && apt-get -y install --reinstall nano sed \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives/* \
 && mkdir -p /var/lib/apt/lists/partial /var/cache/apt/archives/partial \
 && echo '# exam-compromised' >> /usr/bin/nano \
 && chmod 777 /bin/sed \
 && test -n "$(rpm -V nano 2>/dev/null || true)" \
 && test -n "$(rpm -V sed 2>/dev/null | grep '/bin/sed' || true)"

COPY examples/practice-checks/final_lab/check.sh /opt/lab-check/check.sh
RUN chmod 755 /opt/lab-check/check.sh && chown root:root /opt/lab-check/check.sh

# Задание 6: apt reinstall sudo выше сбрасывает режимы control. Фиксируем начало экзамена ПОСЛЕ всех переустановок:
# sudo в public (не только wheel), sudowheel disabled — сдающий включает sudowheel enabled.
RUN control sudo public 2>/dev/null || true \
 && control sudowheel disabled 2>/dev/null || true

STOPSIGNAL SIGRTMIN+3
CMD ["/lib/systemd/systemd"]
