<?php

/**
 * Текст практики модуля 8 (Docker lab-m8). Подключение в course.php:
 * 'practice' => require __DIR__.'/snippets/module_08_practice_lab.php',
 */
return <<<'MD'
## Практика в Docker — модуль 8 (`os-alt-lab-m8`)

> **Одно задание = одна автопроверка.** В терминале лаборатории вы правите **файлы** конфигурации **audit**. Кнопка **«Проверить результат»** запускает **`sudo /opt/lab-check/check.sh`**: в ответе будет **ровно одна** строка **`TASK1:PASS`** или **`TASK1:FAIL`**, плюс JSON с баллом **0** или **100**. Отдельных «заданий 2 и 3» в проверке **нет** — только описанные ниже правки в трёх файлах.

### Что именно проверяется (сверка с `check.sh`)

| Файл | Условие |
|------|---------|
| **`/etc/audit/auditd.conf`** | **`space_left`** — целое число **меньше 1000** (МБ). **`space_left_action`** — **не** **`HALT`**. |
| **`/etc/audit/rules.d/ssh_watch.rules`** | Строка **watch** на **`/etc/openssh/sshd_config`** с **`-p wa`** и **`-k sshd_config_change`** (в лабе достаточно заменить путь в уже выданной строке). |
| **`/etc/audit/rules.d/hardening.rules`** | Есть правило **`-w /etc/passwd`** … **`-p wa`** … **`-k user_accounts`** — **не удаляйте** эту строку. |

### Среда

Выделите контейнер с образом **`os-alt-lab-m8:latest`** (на стенде может быть тег **`os-alt-lab-m8-systemd:latest`** — тот же rootfs). **PID 1 = systemd**, контейнер с **`--privileged`** (см. **`lab-daemon/app.py`**).

При **первом** запуске выполняется **`/opt/lab/lab-m8-setup.sh`** (флаг **`/var/lib/os-alt-lab-m8/.setup-done`**): завышен **`space_left`**, для **`space_left_action`** выставлен **`HALT`**, в **`ssh_watch.rules`** путь к **`sshd_config`** как в «типовом» Linux (**`/etc/ssh/...`**), а не как в **Альт**. Пользователь **`student`**, **`sudo`** без пароля.

> **Почему не требуем работающий `auditd`:** на части стендов (Docker, ядро **un-def-alt**) из контейнера аудит по netlink даёт **EPERM**. Зачёт — **только по файлам**; на бою после правок вы включили бы **`systemctl enable --now auditd`** сами.

### Команды в контейнере

Проверить текущее состояние и сдать:

```bash
sudo grep -E '^space_left|^space_left_action' /etc/audit/auditd.conf
sudo cat /etc/audit/rules.d/ssh_watch.rules
sudo grep -E 'passwd|user_accounts' /etc/audit/rules.d/hardening.rules
sudo /opt/lab-check/check.sh
```

В начале вывода проверки — строка **`# lab8-check:`**. **`systemctl`** / **`journalctl -u auditd`** можете использовать для себя — на **зачёт** автопроверки они не влияют.
MD;
