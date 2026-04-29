# Polkit, модуль ролей и control

Управление привилегиями приложений в ОС Альт Линукс.

**Лаборатория модуля 9** (Docker, PID 1 = **systemd**). Образ: **`os-alt-lab-m9:latest`** (при деплое можно повесить **`os-alt-lab-m9-systemd:latest`** — тот же rootfs). Три задания: правило **`10-network-operators.rules`**, новое **`20-auditors-update.rules`**, исправление **`ru.altcourse.lab.policy`**. Учебные действия **`ru.altcourse.lab.*`** не зависят от NetworkManager / udisks / logind.

Сборка из каталога **`laravel-course-files`**:

```bash
docker build -f docker/lab-m9/Dockerfile -t os-alt-lab-m9:latest -t os-alt-lab-m9-systemd:latest .
```

На стенде: **`scripts/deploy-lab-m9-stand.sh`**. Для модуля **9** **lab-daemon** запускает контейнер **с systemd как PID 1** (см. **`lab-daemon/app.py`**, `module_id in (8, 9)`).

Проверка в контейнере:

```bash
# от учётки student:
sudo /opt/lab-check/check.sh
# от root (после sudo -i) — без sudo, иначе в минимальном sudoers может не быть записи для root:
/opt/lab-check/check.sh
```

После правок **`/etc/polkit-1/rules.d/*.rules`** или **`.policy`**: **`sudo systemctl restart polkit`**.

Пакет **`polkit-pkla-compat`** подключается, если есть в репозитории; иначе шаг пропускается.
