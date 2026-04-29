#!/usr/bin/env python3
"""Patch /var/www/os-alt-lab/config/course.php: module 6 -> PAM."""
from pathlib import Path

p = Path("/var/www/os-alt-lab/config/course.php")
text = p.read_text(encoding="utf-8")

old_mod6 = """        6 => [
            'letter' => 'F',
            'title' => 'GRUB и восстановление',
            'summary' => 'GRUB2, Rescue, LiveCD из дистрибутива.',
            'theory' => <<<'MD'
## Цели модуля

Понять сценарии восстановления загрузки в экосистеме ОС "Альт".

## Ключевые темы

- GRUB2, режимы загрузки
- Rescue / LiveCD

## Практика

Чек-лист действий при сбое загрузки на стенде.
MD,
            'practice' => 'Составьте чек-лист из 5 шагов для диагностики проблемы загрузки на стенде (без реального ломания продакшена).',
        ],
"""

new_mod6 = """        6 => [
            'letter' => 'F',
            'title' => 'PAM: аутентификация и политика паролей в ОС Альт',
            'summary' => 'Стеки PAM, TCB и pam_tcb, pam_passwdqc и /etc/passwdqc.conf, цепочки include; отличия от РедОС и «Астра Линукс».',
            'theory' => '@snippet:module_06_theory.md',
            'practice' => <<<'MD'
Практическое задание будет оформлено отдельно. Пока достаточно теории: по желанию на стенде **только просмотр** (без правок) файлов `/etc/passwdqc.conf`, `/etc/pam.d/passwd` и цепочки `system-auth` → `system-auth-local` → `system-auth-local-only`. В 5–7 строках выпишите, какие модули указаны для типов `auth` и `password` в «локальном ядре» цепочки.
MD,
        ],
"""

old_q6 = """            6 => [
                'theory_quiz' => [
                    ['q' => 'GRUB2 в курсе - это:', 'a' => ['Загрузчик', 'Только редактор видео', 'Файловый менеджер'], 'c' => 0],
                    ['q' => 'Rescue-режим нужен для:', 'a' => ['Игр', 'Восстановления системы', 'Установки игр'], 'c' => 1],
                    ['q' => 'LiveCD из дистрибутива полезен для:', 'a' => ['Только просмотра фото', 'Диагностики и восстановления', 'Только BIOS'], 'c' => 1],
                ],
                'module_exam' => [
                    ['q' => 'Чек-лист восстановления начинается с:', 'a' => ['Случайных действий', 'Фиксации симптомов и доступа к консоли', 'Удаления GRUB'], 'c' => 1],
                    ['q' => 'Редактирование параметров ядра в GRUB относится к:', 'a' => ['Только украшению', 'Диагностике загрузки', 'Только звуку'], 'c' => 1],
                    ['q' => 'LiveCD и установочный образ - это:', 'a' => ['Всегда одно и то же по роли', 'Разные сценарии применения в курсе', 'Только для macOS'], 'c' => 1],
                ],
            ],
"""

new_q6 = """            6 => [
                'theory_quiz' => require __DIR__.'/snippets/module_06_theory_quiz_questions.php',
                'module_exam' => [
                    ['q' => 'Какой PAM-модуль в Альт типично используется для работы с TCB вместо pam_unix для локальных учётных записей?', 'a' => ['pam_ldap.so', 'pam_tcb.so', 'pam_systemd.so'], 'c' => 1],
                    ['q' => 'Файл политики pam_passwdqc в Альт обычно называется:', 'a' => ['/etc/security/pwquality.conf', '/etc/passwdqc.conf', '/etc/pam.d/passwdqc'], 'c' => 1],
                    ['q' => 'Символическая ссылка /etc/pam.d/system-auth в Альт нужна прежде всего чтобы:', 'a' => ['Удалить PAM из системы', 'Переключать общую политику аутентификации без правки каждого сервиса', 'Отключить sudo'], 'c' => 1],
                ],
            ],
"""

old_final = "        ['q' => 'GRUB2 в модуле F - это:', 'a' => ['Загрузчик', 'Только редактор видео', 'Сетевой менеджер'], 'c' => 0],"
new_final = "        ['q' => 'В модуле F (Альт): типичный модуль для политики паролей вместо pam_pwquality?', 'a' => ['pam_passwdqc', 'pam_unix', 'pam_firewall'], 'c' => 0],"

if old_mod6 not in text:
    raise SystemExit("old_mod6 not found")
text = text.replace(old_mod6, new_mod6, 1)
if old_q6 not in text:
    raise SystemExit("old_q6 not found")
text = text.replace(old_q6, new_q6, 1)
if old_final not in text:
    raise SystemExit("old_final not found")
text = text.replace(old_final, new_final, 1)

p.write_text(text, encoding="utf-8")
print("OK: modules[6], module_quizzes[6], final_lab")
