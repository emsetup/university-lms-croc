# Лаборатория модуля 8 — конфигурация **audit**, PID 1 = **systemd**

Образ: **`os-alt-lab-m8:latest`** (при деплое на стенд скрипт вешает ещё тег **`os-alt-lab-m8-systemd:latest`**). **Одно задание:** поправить в файлах **`auditd.conf`**, **`ssh_watch.rules`** (путь к **`sshd_config`** в **Альт**) и сохранить **`hardening.rules`**. Автопроверка **не** требует успешного запуска **`auditd`** в контейнере (см. ниже про **EPERM** / **Docker**).

Сборка из каталога **`laravel-course-files`**:

```bash
docker build -f docker/lab-m8/Dockerfile -t os-alt-lab-m8:latest -t os-alt-lab-m8-systemd:latest .
```

«Поломка» при **первом** старте: **`entrypoint.sh`** вызывает **`/opt/lab/lab-m8-setup.sh`**, затем **`exec /lib/systemd/systemd`** (флаг **`/var/lib/os-alt-lab-m8/.setup-done`**).

На стенде для **модуля 8** lab-daemon задаёт **`--privileged`**, cgroup хоста и tmpfs (см. **`lab-daemon/app.py`**).

**Важно (ядро 6.1 un-def-alt, Docker):** в отдельном PID namespace **`auditd`/`auditctl`** часто получают **EPERM** на **NETLINK_AUDIT**, при этом на **хосте** `auditctl` работает. Технически помогает **`docker run --pid=host`**, но тогда **systemd не может быть PID 1** (контейнер сразу выходит с ошибкой про *user manager*). Поэтому **в одном контейнере** нельзя совместить «как на ВМ» **systemd** и рабочий **локальный audit** на таком стенде — практику модуля 8 нужно вести на **ВМ с полноценной ОС** (не Docker на этом ядре) или менять стек (например отдельный хост/образ ядра).

Папка **[`../lab-m8-systemd/`](../lab-m8-systemd/)** — альтернативный путь сборки того же rootfs (удобно, если CI ссылается на старый Dockerfile).

Проверка внутри контейнера:

```bash
sudo /opt/lab-check/check.sh
```

### Если `systemctl start auditd` сразу падает

**A. В журнале: `Unknown keyword "…" in … auditd.conf`**  
В **p10** в **`auditd.conf`** нет части директив из самых новых man (например **`report_interval`**). Удалите строку или обновите образ лабы.

**B. В журнале: `Operation not permitted` / `Unable to set initial audit startup state to 'enable'`**  
На **6.1 un-def-alt** в Docker с **отдельным PID namespace** **NETLINK_AUDIT** часто даёт **EPERM** (`auditctl -s` в контейнере падает, на хосте — нет). **`--pid=host`** это обходит, но **ломает образ с systemd как PID 1** (в логах: *Explicit --user argument required to run as user manager*, контейнер **Exited (1)**). Используйте **ВМ**, не Docker на этом ядре, для полной практики auditd + systemctl.

Дополнительно: на **хосте** уже работает **`auditd`** — **`sudo systemctl stop auditd`** на время занятий (если EPERM не из-за namespace). **WSL2**: часто невозможно — лучше **Linux-ВМ**.

Сообщение **`No plugins found, not dispatching events`** само по себе не главное; критичны строки про **`enable`** / **`Operation not permitted`**.

Вариант **`local_events=no`** иногда поднимает процесс без локального аудита, но тогда **`ausearch`** не отражает локальные события — для полной отработки **audit** на **ВМ** учитывайте это отдельно; **зачёт** в Docker идёт по **`/opt/lab-check/check.sh`** (только файлы).

Диагностика в контейнере:

```bash
sudo journalctl -u auditd -b --no-pager -n 25
sudo /usr/sbin/auditd -f
```
