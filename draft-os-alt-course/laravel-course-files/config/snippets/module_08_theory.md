# Модуль 8 — Теория: аудит событий. `auditd` в Альт Линукс

---

## 1. Зачем нужен `auditd` и чем он отличается от `journald`

Многие путают **`auditd`** и **`journald`**: оба собирают информацию о событиях в системе. Но это **разные инструменты** с **разными задачами**.

### Высокий уровень: `journald`

**`journald`** собирает логи приложений и служб — то, что попало в **stdout/stderr**, **syslog** и связанные источники. Это **высокоуровневый** взгляд: «nginx запустился», «sshd отклонил соединение».

### Низкий уровень: `auditd`

**`auditd`** работает на уровне **ядра Linux**: он перехватывает **системные вызовы**. Это **низкоуровневый** взгляд: «процесс с PID 1234 от пользователя `ivan` открыл файл `/etc/passwd` на чтение в 03:17:42». Такую детализацию **`journald`** дать не может.

### Почему это важно для безопасности

Злоумышленник может **подчистить** логи приложений или перенастроить доставку сообщений. Записи **`auditd`** попадают в **отдельный путь** (буфер ядра → демон) и **существенно сложнее** тихо подделать или стереть «как текстовый файл в `/var/log`». Именно поэтому для соответствия требованиям вроде **ФСТЭК** часто требуют **аудит на уровне ядра** — не вместо журналирования служб, а **в дополнение** к нему.

### Что умеет `auditd`

- фиксировать системные вызовы (**`open`**, **`execve`**, **`connect`**, **`chmod`** и т.д.);
- следить за доступом к **конкретным файлам** и каталогам;
- записывать попытки входа в систему — **успешные** и **неудачные** (в связке с подсистемой аудита ядра);
- фиксировать использование **`sudo`** и **`su`** (через правила на бинарники и/или события);
- отслеживать изменения **прав** и **владельца** файлов;
- записывать **сетевую** активность (в рамках доступных в ядре хуков и настроенных правил).

### Чего `auditd` не делает сам по себе

- **не анализирует** бизнес-логику приложений — он видит **вызовы ОС**, а не «смысл операции» в терминах прикладной системы;
- **неудобен в изолированных контейнерах** без доступа к аудиту **хоста** — нужен согласованный дизайн платформы;
- **не является IDS** — это инструмент **записи** и последующего **расследования**, а не автоматического обнаружения атак (для этого нужны SIEM, правила корреляции, реагирование).

---

## 2. Установка и первоначальная настройка в Альт

В **Альт Линукс** пакет с **`auditd`** **не обязан** быть установлен по умолчанию — его ставят **явно**, если нужен аудит.

### Установка и служба

```bash
# Установка
apt-get install audit

# Запуск и автозагрузка
systemctl enable --now auditd

# Проверка статуса
systemctl status auditd
```

### Ключевые файлы и каталоги

| Путь | Назначение |
|------|------------|
| `/etc/audit/auditd.conf` | основной конфиг **демона** |
| `/etc/audit/rules.d/` | каталог с файлами **правил** |
| `/etc/audit/rules.d/audit.rules` | типовой «главный» файл правил (имя может различаться по дистрибутиву) |
| `/var/log/audit/audit.log` | журнал **событий** аудита |

### Права на файлы правил

Файлы в **`/etc/audit/rules.d/`** должны принадлежать **только root** и иметь права **`600`**:

```bash
chown root:root /etc/audit/rules.d/my.rules
chmod 600 /etc/audit/rules.d/my.rules
```

**Зачем:** если атакующий видит **полный список** того, что вы логируете, он может действовать **в обход** правил (другие утилиты, другие пути, другой порядок действий). Конфигурацию аудита защищают так же тщательно, как и сами журналы.

### Фрагмент `/etc/audit/auditd.conf`

Ниже — ориентир по **смыслу** параметров (значения подбирают под политику и ёмкость диска):

```ini
log_file = /var/log/audit/audit.log
log_format = RAW
max_log_file = 8
max_log_file_action = ROTATE
num_logs = 5
space_left = 75
space_left_action = SYSLOG
admin_space_left = 50
admin_space_left_action = SUSPEND
disk_full_action = SUSPEND
disk_error_action = SUSPEND
```

**`disk_full_action = SUSPEND` vs `HALT`:** на **production** чаще выбирают **`SUSPEND`** — аудит **останавливается**, но система **продолжает** работать. **`HALT`** — **жёсткий** режим для сред, где **работа без аудита** считается недопустимой.

---

## 3. Типы правил `auditd`

Правила делятся на **три типа**. Различие между ними — основа грамотной настройки.

### Тип 1 — правила управления (control)

Задают поведение подсистемы аудита. Задаются через **`auditctl`** (и/или загрузочные скрипты).

```bash
# Размер буфера событий (пример)
-b 8192

# Режим при ошибке доставки: 0=silent, 1=printk, 2=panic
-f 1

# Заблокировать изменение правил до перезагрузки (защита от tamper)
-e 2
```

Параметр **`-e 2`** особенно важен в жёстких сценариях: после применения **нельзя** снять правила **без перезагрузки**. Это не «волшебная кнопка», а компромисс: и легитимный администратор тоже не поправит правила на лету.

### Тип 2 — наблюдение за файлами (file watches)

Следят за доступом к **файлу** или **каталогу**.

```bash
# Синтаксис
-w <путь> -p <права> -k <метка>
```

**`-p`:** **`r`** чтение, **`w`** запись, **`x`** выполнение, **`a`** изменение атрибутов (append/attribute change — в зависимости от версии документации смотрите **`auditctl(8)`**).

Примеры:

```bash
-w /etc/passwd -p wa -k user_accounts_change
-w /etc/sudoers -p wa -k sudoers_change
-w /etc/audit/ -p wa -k audit_config_change
-w /etc/pam.d/ -p wa -k pam_config_change
-w /usr/bin/sudo -p x -k sudo_usage
-w /etc/shadow -p r -k shadow_read
```

**`-k`** — произвольная **метка** для поиска: **`ausearch -k shadow_read`**.

### Тип 3 — правила системных вызовов (syscall)

Перехватывают **конкретные** системные вызовы.

```bash
# Схема
-a <список>,<действие> -F <поле>=<значение> -S <syscall> -k <метка>
```

**Списки/действия:** в учебных примерах чаще всего **`always,exit`** — писать при **выходе** из syscall. **`never`** — явное исключение.

Примеры:

```bash
# Удаление и переименование файлов
-a always,exit -F arch=b64 -S unlink,unlinkat,rename,renameat -k file_deletion

# Изменение прав и владельца
-a always,exit -F arch=b64 -S chmod,fchmod,fchmodat,chown,fchown,fchownat -k permission_change

# Неудачный доступ к файлам (типовые errno)
-a always,exit -F arch=b64 -S open,openat -F exit=-EACCES -k access_denied
-a always,exit -F arch=b64 -S open,openat -F exit=-EPERM -k access_denied

# Команды от имени пользователя с заданным login uid (auid)
-a always,exit -F arch=b64 -F auid=1001 -S execve -k user_commands

# Изменение времени
-a always,exit -F arch=b64 -S adjtimex,settimeofday,clock_settime -k time_change

# Загрузка/выгрузка модулей ядра
-a always,exit -F arch=b64 -S init_module,delete_module -k kernel_modules
```

**`-F arch=b64`:** на **64-битной** системе без указания архитектуры часть правил может **не сработать** так, как ожидается; для **x86_64** используют **`b64`** (см. **`auditctl(8)`** / списки архитектур).

---

## 4. Как добавлять правила

### Способ 1 — файл в `/etc/audit/rules.d/` (рекомендуется)

Создайте файл с суффиксом **`.rules`**, затем перезапустите демон (политика перезапуска зависит от дистрибутива; на практике часто **`systemctl restart auditd`**, но проверяйте документацию **Альт** для вашей версии):

```bash
nano /etc/audit/rules.d/hardening.rules
systemctl restart auditd
```

### Способ 2 — `auditctl` в реальном времени

Удобно для **проверки** правила до записи в файл. **До перезагрузки** правило живёт в памяти; после перезагрузки — только то, что загрузилось из файлов/скриптов.

```bash
auditctl -w /etc/passwd -p wa -k passwd_change
auditctl -l
auditctl -W /etc/passwd -p wa -k passwd_change   # удалить конкретное watch
auditctl -D                                         # удалить все правила
```

### Способ 3 — загрузка из произвольного пути

```bash
auditctl -R /opt/my-audit-rules.rules
```

---

## 5. Анализ журнала: `ausearch` и `aureport`

«Сырой» **`/var/log/audit/audit.log`** плохо читается человеком; для работы используют **`ausearch`** и **`aureport`**.

### Пример строки (упрощённо)

```text
type=SYSCALL msg=audit(1714042662.123:456): arch=c000003e syscall=2
success=yes exit=3 ... comm="cat" exe="/usr/bin/cat" key="shadow_read"
```

### Поля, которые чаще всего нужны при расследовании

| Поле | Смысл |
|------|--------|
| **`auid`** | **login uid** — «кто вошёл в систему»; при типовом **`sudo`** часто сохраняет связь с исходным пользователем |
| **`uid` / `euid`** | текущий пользователь процесса |
| **`comm`** | короткое имя команды |
| **`exe`** | путь к исполняемому файлу |
| **`key`** | метка правила |
| **`success`** | успех или отказ syscall |

**Про `auid`:** после **`sudo bash`** у процесса может быть **`euid=0`**, но **`auid`** часто остаётся **не нулевым** — это помогает отвечать на вопрос «**кто** реально инициировал сессию».

### `ausearch` — поиск

```bash
ausearch -k shadow_read
ausearch -ua 1001
ausearch -x /usr/bin/sudo
ausearch --start 2026-04-23 --end 2026-04-24
ausearch --success no
ausearch -m USER_LOGIN
ausearch -k access_denied --start today --success no
ausearch -k passwd_change -i
```

Ключ **`-i`** — **интерпретируемый** (более читаемый) вывод.

### `aureport` — сводки

```bash
aureport
aureport -au --failed
aureport --login
aureport -f
aureport -x
aureport --start today --summary
aureport -u --start today
```

---

## 6. Практические кейсы

### Кейс 1. Кто читал `/etc/shadow`?

```bash
-w /etc/shadow -p r -k shadow_read
ausearch -k shadow_read -i | grep -E 'auid|comm|exe|time'
```

### Кейс 2. Несанкционированное использование `sudo`

```bash
-w /usr/bin/sudo -p x -k sudo_usage
ausearch -k sudo_usage -i --start today
aureport --login --summary
```

### Кейс 3. Подозрительное удаление файлов в каталоге

```bash
-a always,exit -F arch=b64 -S unlink,unlinkat -F dir=/var/www -k web_files_delete
ausearch -k web_files_delete -i
```

### Кейс 4. Брутфорс SSH

Часть событий аутентификации приходит как типы сообщений аудита (набор зависит от конфигурации **PAM/sshd** и политики). Типовой приём:

```bash
ausearch -m USER_AUTH -i --success no --start today
aureport -au --failed --start today
```

### Кейс 5. Команды конкретного сотрудника (`auid=1002`)

```bash
-a always,exit -F arch=b64 -F auid=1002 -S execve -k user1002_commands
ausearch -k user1002_commands -i | grep 'comm='
```

### Кейс 6. Изменение сетевых настроек (**etcnet** в Альт)

```bash
-w /etc/net/ifaces/ -p wa -k network_config_change
ausearch -k network_config_change -i
```

---

## 7. Альт vs «Астра»: один инструмент — разный контекст

**`auditd`** как компонент — **тот же класс** решений, но **политика** и **набор полей** в правилах могут отличаться.

### Альт Линукс

- **`auditd`** — **дополнительный** инструмент: правила **пишутся вручную** под задачу, **нет** «единого обязательного профиля из коробки» для всех установок;
- типовые правила — **стандартные** для подсистемы **`audit`** в Linux.

### Астра Линукс

- **`auditd`** часто **связан** с **Parsec** и мандатным контролем доступа;
- в правилах могут встречаться **специфические фильтры** (пример для иллюстрации — **не** копировать в Альт без проверки):

```bash
# Пример специфики Parsec (на Астра) — на Альт не переносится «как есть»
-a always,exit -F subj_type=psaud -F arch=b64 -S all -k parsec-p
-a always,exit -F obj_type=faud  -F arch=b64 -S all -k parsec-f
```

**Практический вывод:** правила с **неизвестными** полями **`auditctl`** на стандартном ядре **отвергнет**. Смешанный парк и миграции требуют **пересборки** политики аудита, а не слепого копирования файлов.

### Сравнительная таблица

| Параметр | Альт Линукс | Астра Линукс |
|----------|-------------|--------------|
| Инструмент | `auditd` (стандартная подсистема) | `auditd` + интеграция с **Parsec** |
| Специфические поля | нет (стандартные фильтры) | `subj_type`, `obj_type`, др. |
| Преднастроенные профили | как правило **нет** — настраивает администратор | часто есть **готовые** ориентиры под мандатную модель |
| Журналы/пути | типично `/var/log/audit/audit.log` | плюс административные журналы вроде **`/parsec/log/astra/events`** (по документации поставки) |
| Переносимость правил | стандартные правила переносимы между «обычными» Linux | правила с полями Parsec **не** переносимы на Альт без переработки |

---

## 8. Рекомендуемый минимальный набор правил для Альт

Ниже — **учебный** каркас **`/etc/audit/rules.d/hardening.rules`**. Подгоните под нагрузку, **исключения** (шумные каталоги) и политику **`-e 2`**.

```bash
## Буфер и поведение при переполнении
-b 8192
--backlog_wait_time 60000
-f 1

## Учётные записи
-w /etc/passwd -p wa -k user_accounts
-w /etc/group -p wa -k user_accounts
-w /etc/shadow -p r -k shadow_read
-w /etc/sudoers -p wa -k sudoers_change
-w /etc/sudoers.d/ -p wa -k sudoers_change

## PAM и политика паролей (специфика Альт)
-w /etc/pam.d/ -p wa -k pam_config
-w /etc/passwdqc.conf -p wa -k pam_config
-w /etc/tcb/ -p wa -k tcb_change

## Сеть (etcnet)
-w /etc/net/ifaces/ -p wa -k network_config

## SSH
-w /etc/openssh/sshd_config -p wa -k sshd_config

## Планировщик
-w /etc/cron.d/ -p wa -k cron_change
-w /var/spool/cron/ -p wa -k cron_change

## Защита конфигурации и журналов аудита
-w /etc/audit/ -p wa -k audit_config
-w /var/log/audit/ -p wa -k audit_log

## Syscall: удаление
-a always,exit -F arch=b64 -S unlink,unlinkat,rename,renameat -k file_deletion

## Syscall: права и владелец
-a always,exit -F arch=b64 -S chmod,fchmod,fchmodat,chown,fchown,fchownat -k permission_change

## Syscall: отказ в доступе
-a always,exit -F arch=b64 -S open,openat -F exit=-EACCES -k access_denied
-a always,exit -F arch=b64 -S open,openat -F exit=-EPERM -k access_denied

## Время
-a always,exit -F arch=b64 -S adjtimex,settimeofday,clock_settime -k time_change

## Модули ядра
-a always,exit -F arch=b64 -S init_module,delete_module -k kernel_modules

## Раскомментируйте только когда правила проверены на стенде:
## -e 2
```

Строка **`-e 2`** намеренно **закомментирована**: сначала убеждаются, что правила **не ломают** производительность и **не душат** диск, затем включают жёсткую фиксацию.

---

## 9. Итог для администратора Альт

```bash
apt-get install audit
nano /etc/audit/rules.d/hardening.rules   # вставить политику из раздела 8
systemctl enable --now auditd
auditctl -l
```

Регулярная проверка смысла аудита — не формальность:

```bash
aureport --start yesterday --end today
ausearch -k shadow_read --start yesterday -i
ausearch -k sudoers_change --start yesterday -i
```

**`auditd`** даёт ценность только тогда, когда журналы **читают**, **хранят**, **коррелируют** с другими источниками и **реагируют** на отклонения. Иначе это лишний **шум** и расход диска.

---

## Краткий глоссарий

| Термин | Пояснение |
|--------|-----------|
| **`auditd`** | пользовательский демон, забирает события из ядра и пишет лог |
| **`auditctl`** | утилита управления правилами |
| **`ausearch` / `aureport`** | поиск и отчёты по журналу |
| **`-k`** | тег (ключ) для фильтрации событий |
| **`auid`** | идентификатор исходного входа пользователя (важно при элевации) |
