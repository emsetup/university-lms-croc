# Образ `os-alt-lab-m1` (модуль 1)

Серверный профиль **ALT p10** внутри контейнера: файл выпуска `/etc/altlinux-release`, пакеты ветки **alt-server**, цель по умолчанию **multi-user.target**. Студент заполняет четыре файла: **`~/1.txt`**, **`~/2.txt`**, **`~/3.txt`**, **`~/4.txt`**, проверка — **`/opt/lab-check/check.sh`**.

## Сборка локально или на стенде

Из каталога **`laravel-course-files`**:

```bash
docker build -f docker/lab-m1/Dockerfile -t os-alt-lab-m1:latest .
```

Публикация на стенд по SSH (как у остальных лаб):

```bash
export STAND_SSH=user@stand-host
bash scripts/deploy-lab-m1-stand.sh
```

В **`.env`** Laravel: **`PRACTICE_LAB_IMAGE_1=os-alt-lab-m1:latest`** (или тег, который собрали).
