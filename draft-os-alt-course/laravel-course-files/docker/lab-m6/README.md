# Docker-образ лабораторной модуля 6 (PAM / passwdqc / faillock)

## Сборка

Из корня репозитория `laravel-course-files`:

```bash
docker build -f docker/lab-m6/Dockerfile -t os-alt-lab-m6:latest .
```

## Деплой на стенд (образ + напоминание про текст курса)

На машине, где установлен **Docker** и есть **SSH** на сервер стенда:

```bash
cd /path/to/draft-os-alt-course
export STAND_SSH=user@stand-host
./scripts/deploy-lab-m6-stand.sh
```

Скрипт собирает **`os-alt-lab-m6:latest`** из `laravel-course-files` и выполняет **`docker save | ssh … docker load`**.

Текст практикума модуля 6 на сайте берётся из **`config/course.php`** (или эквивалента на стенде): при необходимости перенесите блок модуля **6** из репозитория **`scripts/fixtures/course-recovered-from-stand-tgz.php`**. После смены образа студентам со **старым** контейнером нужно нажать **«Завершить работу со стендом»** и выделить стенд заново.

## Подключение к LMS

В `config/practice_lab.php` (или `.env`) задайте образ для ключа `6`, например:

```php
'images' => [
    // ...
    '6' => 'os-alt-lab-m6:latest',
],
```

Перезапустите lab-daemon, если он кеширует список образов.

## Если сборка падает на apt

Имена пакетов в ветке p10 могут отличаться. Проверьте в контейнере базы:

```bash
docker run --rm -it registry.altlinux.org/alt/alt:p10 bash -lc "apt-get update && apt-cache search passwdqc | head"
```

Подставьте найденные имена в `Dockerfile` (блок `apt-get install`).

## Содержимое

- `entrypoint.sh` + `CMD` — при старте контейнера поднимается **`systemd-journald`**, чтобы был рабочий **`journalctl`**; команда по умолчанию — `sleep infinity`.
- В образ ставятся **`passwd`**, **`sudo`**, **`systemd`** (и учебный **`/etc/sudoers.d/student-lab`**: `student` может `sudo -i` без пароля).
- `files/etc/passwdqc.conf`, `files/etc/pam.d/*` — стартовая **рабочая** цепочка до поломки.
- `scripts/lab-m6-setup.sh` — выполняется **один раз при сборке**: пользователи **`testuser`** (задания 1–4) и **`lockuser`** (задание 5, faillock), пять сценариев поломки; в образе **`util-linux`** (`faillock`) и **`/etc/profile.d/m6-lab-path.sh`** (`PATH` включает `/usr/sbin`).
- **faillock:** счётчики в **`/var/lib/os-alt-lab-m6/faillock`** (в PAM указано **`dir=`**), а не в `/run`: иначе при старте контейнера tmpfs затирает `/var/run/faillock` из слоя образа. В **`/etc/pam.d/su`** добавлены **`preauth`/`authsucc`**, чтобы при «поломке» симлинка **`system-auth`** (задание 4) проверка faillock для **`su`** всё равно выполнялась до цепочки с **`pam_permit`**.
- `examples/practice-checks/module_06/check.sh` — копируется в образ как `/opt/lab-check/check.sh` (5×20 баллов).
