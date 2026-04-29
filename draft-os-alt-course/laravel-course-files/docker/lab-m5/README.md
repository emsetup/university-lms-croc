# Практика модуля 5 — `os-alt-lab-m5-systemd` (etcnet, маршруты, DNS)

- **Сборка** (из каталога `laravel-course-files`):

  ```bash
  docker build -f docker/lab-m5/Dockerfile -t os-alt-lab-m5:latest -t os-alt-lab-m5-systemd:latest .
  ```

- **Тег `*-systemd`:** lab-daemon запускает контейнер с **systemd** как PID 1 и cgroup/tmpfs (как у M3), плюс **`--cap-add=NET_ADMIN`** и **`--tmpfs /etc/resolv.conf`** (иначе Docker даёт bind-mount на `resolv.conf` и **ifup** из etcnet пишет **rm: … Device or resource busy**).

- **Первый старт:** `entrypoint.sh` → `lab-m5-setup.sh` один раз: dummy **eth1**, «ломаные» **eth0**/**eth1** в etcnet, **nsswitch** (`hosts: dns files`). Правки **`/etc/nsswitch.conf`** и **`/etc/hosts`** делаются через временный файл (не `sed -i`: в Docker overlay часто **Device or resource busy**).

- **Проверка:** `/opt/lab-check/check.sh`.

- **Деплой на стенд** (из корня репо курса):

  ```bash
  STAND_SSH=user@host bash scripts/deploy-lab-m5-stand.sh
  ```

В `.env` Laravel: **`PRACTICE_LAB_IMAGE_5=os-alt-lab-m5-systemd:latest`**.
