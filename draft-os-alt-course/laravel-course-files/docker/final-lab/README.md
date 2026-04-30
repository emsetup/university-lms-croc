# Финальная лабораторная: практический экзамен

Образ имитирует "свежий" сервер ALT p10: без преднамеренных поломок, с базовой системой.
Студент должен сам довести систему до требуемого состояния по ТЗ экзамена.

## Сборка

Из корня `laravel-course-files`:

```bash
docker build -f docker/final-lab/Dockerfile -t os-alt-final-lab:latest .
```

## Запуск (локально)

Для работы `systemd`, `auditd`, `polkit` требуется привилегированный запуск:

```bash
docker run -d --privileged --name alt-final-exam os-alt-final-lab:latest
```

Внутри контейнера проверка:

```bash
sudo /opt/lab-check/check.sh
```

## Что уже предустановлено

- Базовые утилиты: `systemd`, `dbus`, `rpm`, `curl`, `nano`, `expect`
- PAM/TCB и `passwdqc`: `pam`, `pam_passwdqc`, `tcb`, `passwd`
- Привилегии ALT: `control`, `libnss-role`
- `polkit` (для задания по проверке Polkit)
- Пользователи:
  - `student` с `NOPASSWD sudo`
  - `testuser` для проверки политики паролей

## Что студент должен сделать сам

- Установить и настроить `alterator-fbi` + сервисы CUS
- Настроить `passwdqc`
- Установить и настроить `auditd` + правила
- Установить и настроить `osec`
- Включить `control sudowheel enabled`
- Подготовить `/root/exam-report.txt`

## Критерий сдачи

- `check.sh` выставляет итог `SCORE:x:100`
- Порог: `>= 70`

