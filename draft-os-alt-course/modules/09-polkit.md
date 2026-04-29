# Polkit, модуль ролей и control

Управление привилегиями приложений в ОС Альт Линукс.

**Полная теория (Markdown для LMS):** `laravel-course-files/config/snippets/module_09_theory.md` — Polkit (`polkitd`, агент, действия и правила), **`.policy`** / **`/etc/polkit-1/rules.d/`**, **NetworkManager** и **ЦУС (Alterator)**, **`pkaction`** / **`pkcheck`** / **`pkexec`**, диагностика **`journalctl`**, сравнение с РедОС и Астра Линукс, три механизма (**`sudo`**, Polkit, **`control`**), утилита **`control`**, модуль ролей (**`libnss-role`**, **`/etc/role`**, **`roleadd`** / **`roledel`** / **`rolelst`**), итоговая схема уровней привилегий.

**Фикстур курса:** в `scripts/fixtures/course-recovered-from-stand-tgz.php` для модуля **9** поле **`theory`** указывает на **`@snippet:module_09_theory.md`**. На стенде в `config/course.php` должно быть то же подключение (или эквивалентная загрузка сниппета).

## Практика

Docker **`os-alt-lab-m9`**: см. **`laravel-course-files/config/snippets/module_09_practice_lab.php`**, **`docker/lab-m9/README.md`**, **`scripts/deploy-lab-m9-stand.sh`**.

## Контроль

1. Понимание различия **sudo**, **Polkit** и **`control`** по уровню и контексту применения.
2. Чтение **`allow_active` / `allow_inactive` / `allow_any`** в `.policy` и типовые кейсы NM по SSH.
3. Место правил **`/etc/polkit-1/rules.d/`** и порядок диагностики: **`pkaction`**, **`journalctl -u polkit`**.
