# Модуль 8. Аудит событий (`auditd`)

Полная теория: `laravel-course-files/config/snippets/module_08_theory.md`. Практика в Docker: **`os-alt-lab-m8`** — одно задание по файлам конфигурации **audit** (без обязательного запуска **auditd** в контейнере), см. `docker/lab-m8/README.md` и `scripts/deploy-lab-m8-stand.sh`.

Кратко: **`journald`** — логи служб и приложений; **`auditd`** — события уровня ядра (в т.ч. syscall), для расследований и требований по аудиту. Правила — в **`/etc/audit/rules.d/`**, журнал — **`/var/log/audit/audit.log`**, разбор — **`ausearch`** / **`aureport`**.
