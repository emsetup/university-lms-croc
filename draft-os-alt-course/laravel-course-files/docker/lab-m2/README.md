# Образ `os-alt-lab-m2` (модуль 2)

Практика по теме репозиториев и пакетного менеджмента в ОС «Альт».

В контейнере доступны:
- `rpm`, `apt-get`, `apt-cache`, `apt-repo`
- `epm` (пакет `eepm`)
- проверка: `/opt/lab-check/check.sh`

## Сборка

Из каталога `laravel-course-files`:

```bash
docker build -f docker/lab-m2/Dockerfile -t os-alt-lab-m2:latest .
```

## Что делает setup

Скрипт `docker/lab-m2/lab-m2-setup.sh`:
- вносит опечатку `htp://` в одном из `.list` файлов репозиториев,
- устанавливает `nano`,
- портит метаданные `/usr/bin/nano` для задания на восстановление.

## Файлы отчета студента

- `~/lab-m2-task1.txt`
- `~/lab-m2-task5.txt`
