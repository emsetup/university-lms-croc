# Лаборатория модуля 8 — альтернативный путь сборки (конфиги **audit**, systemd)

Каноничный Dockerfile: **[`../lab-m8/Dockerfile`](../lab-m8/Dockerfile)** (PID 1 = **systemd**). Этот каталог — тот же rootfs, если у вас в CI указан **`docker/lab-m8-systemd/Dockerfile`**.

```bash
docker build -f docker/lab-m8-systemd/Dockerfile -t os-alt-lab-m8-systemd:latest .
```

На стенде **`scripts/deploy-lab-m8-stand.sh`** по умолчанию собирает **`docker/lab-m8/Dockerfile`** с двумя тегами. Для **модуля 8** lab-daemon всегда поднимает контейнер **без `sleep` как PID 1**, с cgroup/tmpfs (см. **`lab-daemon/app.py`**).

```bash
sudo /opt/lab-check/check.sh
```
