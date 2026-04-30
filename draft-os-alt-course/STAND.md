# Учебный стенд (публикация)

**По умолчанию:** `STAND_SSH=emednikov@172.26.76.216`

Агент в Cursor настроен правилом `.cursor/rules/os-alt-stand-deploy.mdc` — после правок лаб/курса деплой на этот хост выполняется без отдельной просьбы «залей».

## Модуль 7 (Docker-образ)

Из каталога **`draft-os-alt-course`**:

```bash
STAND_SSH=emednikov@172.26.76.216 bash scripts/deploy-lab-m7-stand.sh
```

После сборки студентам нужен **новый контейнер** практики (перезапуск стенда в UI).

## Модуль 8 (Docker-образ: конфиги **audit**, PID 1 = **systemd**)

Скрипт по умолчанию собирает **`docker/lab-m8/Dockerfile`** с тегами **`os-alt-lab-m8:latest`** и **`os-alt-lab-m8-systemd:latest`**. Для **модуля 8** lab-daemon не подставляет **`sleep infinity`**: в контейнере полноценный **systemd**, плюс cgroup/tmpfs (см. **`lab-daemon/app.py`**). Автопроверка — **по файлам** (`/opt/lab-check/check.sh`), без обязательного работающего **auditd** в контейнере.

```bash
STAND_SSH=emednikov@172.26.76.216 bash scripts/deploy-lab-m8-stand.sh
```

Один тег: `LAB_M8_IMAGE=registry.example/m8:v2 bash scripts/deploy-lab-m8-stand.sh`.

После деплоя перезапустите **lab-daemon** на стенде, если он уже запущен.

**Модуль 8 / auditd:** на **un-def-alt** в Docker часто **EPERM** у аудита в контейнере при рабочем **`auditctl` на хосте**; **`--pid=host`** это обходит, но **несовместим с systemd как PID 1** (контейнер падает). Полная практика — на **ВМ**, не на Docker этого ядра. Подробнее: **`laravel-course-files/docker/lab-m8/README.md`**.

## Модуль 9 — Polkit, модуль ролей и control

Управление привилегиями приложений в ОС Альт Линукс.

Docker-образ **`os-alt-lab-m9:latest`** / **`os-alt-lab-m9-systemd:latest`**: **dbus**, **polkit**, учебные действия **`ru.altcourse.lab.*`**. Для **модуля 9** lab-daemon поднимает контейнер **с systemd** (как модуль 8), без **`sleep infinity`**.

**Всё по модулю 9 одной командой** (образ M9 + сниппеты/конфиг Laravel + пересборка **lab-daemon**):

```bash
export STAND_SSH=emednikov@172.26.76.216
bash scripts/deploy-module-9-stand-all.sh
```

По отдельности:

```bash
STAND_SSH=emednikov@172.26.76.216 bash scripts/deploy-lab-m9-stand.sh
STAND_SSH=emednikov@172.26.76.216 bash scripts/deploy-laravel-stand.sh
STAND_SSH=emednikov@172.26.76.216 bash scripts/start-lab-daemon-stand.sh
```

В **`.env`** Laravel на стенде добавьте при необходимости: **`PRACTICE_LAB_IMAGE_9=os-alt-lab-m9:latest`**. В **`config/course.php`** для модуля **9** должны быть те же **`theory`**, **`practice`**, **`theory_quiz`**, что в фикстуре **`laravel-course-files/scripts/fixtures/course-recovered-from-stand-tgz.php`** (сниппеты **`module_09_*.php`** / **`module_09_theory.md`**).

Подробнее: **`laravel-course-files/docker/lab-m9/README.md`**.

## Финальная лабораторная (практический экзамен)

Подготовлены артефакты:

- образ: **`laravel-course-files/docker/final-lab/Dockerfile`**
- проверка: **`laravel-course-files/examples/practice-checks/final_lab/check.sh`**
- деплой: **`scripts/deploy-final-lab-stand.sh`**

Сборка на стенде:

```bash
export STAND_SSH=emednikov@172.26.76.216
bash scripts/deploy-final-lab-stand.sh
```

По умолчанию тег: **`os-alt-final-lab:latest`** (или задайте `FINAL_LAB_IMAGE=...`).

### lab-daemon (порт 8090)

Если в браузере ошибка **cURL 7 / 127.0.0.1:8090** — демон не запущен. На стенде в `.env` Laravel должны быть **`PRACTICE_LAB_DAEMON_URL=http://127.0.0.1:8090`** и **`PRACTICE_LAB_DAEMON_SECRET=...`** (тот же секрет передаётся в контейнер как **`LAB_DAEMON_SECRET`**).

Из каталога **`draft-os-alt-course`**:

```bash
export STAND_SSH=emednikov@172.26.76.216
bash scripts/start-lab-daemon-stand.sh
```

Скрипт: rsync **`laravel-course-files/lab-daemon/`** → **`/tmp/os-alt-lab-m8-build/lab-daemon/`**, **`docker build`**, **`docker run`** с **`--network host`** и **`/var/run/docker.sock`**. Контейнер: **`os-alt-lab-daemon-run`**, **`--restart unless-stopped`**.

В `.env` можно задать **`PRACTICE_LAB_PUBLIC_HOST=172.26.76.216`** (IP или DNS стенда, видимый из браузера). Если не задано, скрипт берёт хост из **`STAND_SSH`** (например `emednikov@172.26.76.216` → **`172.26.76.216`**). Значение **`127.0.0.1`** для ttyd не подходит: браузер студента подключится к его localhost.

Откройте на стенде диапазон портов **40000–41000** (или ваш `LAB_TTY_PORT_*`) для входящих с браузера, если включён файрвол.

## Образ практики модуля 1 (`os-alt-lab-m1`)

Сборка на стенде (минимальный rsync контекста + `docker build`):

```bash
export STAND_SSH=emednikov@172.26.76.216
bash scripts/deploy-lab-m1-stand.sh
```

В **`.env`** Laravel задайте при необходимости **`PRACTICE_LAB_IMAGE_1=os-alt-lab-m1:latest`** (см. `config/practice_lab.php`).

## Образ практики модуля 5 (`os-alt-lab-m5-systemd`)

**etcnet**, dummy **eth1**, systemd PID 1, **`--cap-add=NET_ADMIN`**, **`--tmpfs /etc/resolv.conf`** (чтобы **ifup** не упирался в занятый bind-mount; см. **`lab-daemon/app.py`**).

```bash
export STAND_SSH=emednikov@172.26.76.216
bash scripts/deploy-lab-m5-stand.sh
```

В **`.env`**: **`PRACTICE_LAB_IMAGE_5=os-alt-lab-m5-systemd:latest`**. После правок **lab-daemon** — **`bash scripts/start-lab-daemon-stand.sh`** (скрипт ждёт 2 с перед health; при ошибке cURL повторите через несколько секунд).

## Laravel на стенде (текст практики, сниппеты, `practice.blade.php`)

Путь на стенде по умолчанию: **`/var/www/os-alt-lab/`** (переменная **`LARAVEL_REMOTE`** для скрипта).

Из каталога **`draft-os-alt-course`** — выкладывает **сниппеты**, **`config/practice_lab.php`**, **`config/course_admin.php`**, **`routes/web.php`**, контроллер/мидлвар админки теории, **`CourseModuleMeta`**, шаблоны **`resources/views/modules/`** (в т.ч. **hub** / **theory** / **practice**) и сбрасывает кэш:

```bash
export STAND_SSH=emednikov@172.26.76.216
bash scripts/deploy-laravel-stand.sh
```

### Редактор теории (Markdown) в браузере

1. В **`.env`** задан **`TEACHER_REPORT_TOKEN`** (ссылка вида `/instruktor/kurs-progress?key=…`) — этот же **`key`** подходит для **`/adm?key=…`**, **`/adm/kurs-teoriya?key=…`** и остальных страниц админ-навигации (отдельный **`COURSE_ADMIN_TOKEN`** не обязателен).
2. Опционально: **`COURSE_ADMIN_TOKEN`** — если задан, им можно открывать **и** редактор теории, **и** сводку по обучающимся (как у преподавательского токена).
3. На стенде должен быть **`config/course_admin.php`** из репозитория (деплой скриптом ниже).
4. **`php artisan config:clear`** после смены `.env`.
5. **Панель администратора:** **`https://<хост>/adm?key=<ваш_токен>`** — меню: панель, содержимое курса (Markdown и просмотры), обучающиеся. Редактор теории по-прежнему: **`/adm/kurs-teoriya?key=…`**.

Запись идёт в **`config/snippets/module_N_theory.md`** на сервере; нужны права на запись у пользователя **php-fpm** / веб-сервера в этот каталог.

Вручную после правок на сервере:

```bash
cd /var/www/os-alt-lab && php artisan config:clear && php artisan cache:clear && php artisan view:clear
```

Полный **`config/course.php`** на стенде при необходимости сверяйте с фикстуром **`laravel-course-files/scripts/fixtures/course-recovered-from-stand-tgz.php`** (границы **`7 =>` / `8 =>`**). Модуль 8 подключает практику из **`config/snippets/module_08_practice_lab.php`** — этот файл как раз обновляет **`deploy-laravel-stand.sh`**.
