## Конструктор Docker-образов (guided)

### Что это
Админка позволяет собирать учебные Docker-образы практики в режиме «конструктор»:
- выбор базовой ОС / base image
- список пакетов на установку/удаление
- `startup.sh` (подготовка окружения)
- `check.sh` (автопроверка)
- сборка на стенде через `lab-daemon`
- привязка готового образа к модулю курса

### Где лежат рецепты
Laravel генерирует контекст сборки в:
- `/var/www/os-alt-lab/storage/app/practice-images/{id}/`

Lab-daemon видит этот каталог через bind-mount (см. `scripts/start-lab-daemon-stand.sh`).

### Переменные окружения lab-daemon
В `scripts/start-lab-daemon-stand.sh` выставляются:
- `LAB_BUILD_WORKDIR=/var/www/os-alt-lab/storage/app` (корень контекстов)
- `LAB_IMAGE_EXPORT_DIR=/var/lib/course-practice-images` (куда `docker save`)
- `LAB_PKG_SEARCH_TIMEOUT_SEC` (по умолчанию 20)
- `LAB_PKG_SEARCH_CACHE_SEC` (по умолчанию 300)

### Важное про base images (РЕДОС/Астра)
РЕДОС/Астра часто требуют закрытых репозиториев/лицензий. Для конструктора это означает:
- base image должен быть доступен стенду (`docker pull` работает), или
- base image должен быть заранее загружен/зеркалирован в локальный registry стенда.

Если base image недоступен — сборка/поиск пакетов будет падать.

### CentOS/Alma (RHCSE)
Для Alma/CentOS конструктор использует `dnf` внутри контейнера base image. Это требует:
- рабочие репозитории внутри base image (обычно есть по умолчанию),
- доступ к сети до зеркал (на стенде).

