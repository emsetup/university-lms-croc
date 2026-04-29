# Практика модуля 3 — `os-alt-lab-m3` (ЦУС / Alterator)

- **Сборка** (из каталога `laravel-course-files`):

  ```bash
  docker build -f docker/lab-m3/Dockerfile -t os-alt-lab-m3:latest -t os-alt-lab-m3-systemd:latest .
  ```

- **Почему два тега:** lab-daemon запускает контейнер с **systemd** как PID 1 только если в имени образа есть подстрока `-systemd` или модуль 8/9. Для модуля 3 в `.env` задайте **`PRACTICE_LAB_IMAGE_3=os-alt-lab-m3-systemd:latest`**.

- **Первый старт:** `entrypoint.sh` выполняет `lab-m3-setup.sh` один раз (флаг `/var/lib/os-alt-lab-m3/.setup-done`): маски `ahttpd`/`alteratord`, удалён `alterator-users`, «ломаные» hostname и `resolv.conf`.

- **Службы Guile (ahttpd/alteratord):** в образе ставится **`glibc-locales`** — без него в логах бывает `guile: warning: failed to install locale` и порт **8080** не поднимается.

- **Проверка:** `/opt/lab-check/check.sh` (кнопка «Проверить результат» вызывает его от root через `docker exec`).

- **Деплой на стенд:** из корня репо курса  
  `STAND_SSH=user@host bash scripts/deploy-lab-m3-stand.sh`
