<?php

use App\Support\CourseQuizBankLoader;

return [
    'email_domain' => 'croc.ru',

    /** Заглушка «Портал обновляется» для вошедших обучающихся (не staff). Включить: PORTAL_USER_MAINTENANCE=true */
    'portal_user_maintenance' => filter_var(env('PORTAL_USER_MAINTENANCE', false), FILTER_VALIDATE_BOOL),

    'teacher_report_token' => env('TEACHER_REPORT_TOKEN', ''),

    'step_titles' => [
        'theory_quiz' => 'Тестирование по теоретической части',
        'practice' => 'Практическое занятие',
        'module_exam' => 'Итоговый тест по модулю',
    ],

    'modules' => [
        1 => [
            'letter' => 'A',
            'title' => 'Дистрибутив и линейка',
            'summary' => 'Линейка ОС «Альт»: Сизиф, платформы, продукты; практика в контейнере — версия, класс продукта, графика/режим загрузки, архитектура и ядро.',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_01_theory.md',
            'practice' => file_get_contents(__DIR__.'/snippets/module_01_practice_lab.md'),
        ],
        2 => [
            'letter' => 'B',
            'title' => 'Репозитории и пакеты',
            'summary' => 'apt-rpm, ветки, зеркала, политика подключения репозиториев.',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_02_theory.md',
            'practice' => file_get_contents(__DIR__.'/snippets/module_02_practice_lab.md'),
        ],
        3 => [
            'letter' => 'C',
            'title' => 'ЦУС, Alterator и сетевая установка',
            'summary' => 'Alterator, ЦУС, сетевая установка как часть экосистемы, не "просто dnsmasq".',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_03_theory.md',
            'practice' => file_get_contents(__DIR__.'/snippets/module_03_practice_tasks.md'),
        ],
        4 => [
            'letter' => 'D',
            'title' => 'Установка ОС: инсталлятор и профили',
            'summary' => 'Профили установки, VNC-install, выбор ядра, сценарии из тетради.',
            'skip_practice' => true,
            'theory' => '@snippet:module_04_theory.md',
            'practice' => '',
        ],
        5 => [
            'letter' => 'E',
            'title' => 'Сеть: три менеджера и контексты',
            'summary' => 'Четыре режима сети: etcnet, NM (etcnet/native), systemd-networkd; hostname, DNS, resolvconf, диагностика.',
            'theory' => '@snippet:module_05_theory.md',
            'practice' => file_get_contents(__DIR__.'/snippets/module_05_practice_tasks.md'),
        ],
        6 => [
            'letter' => 'F',
            'title' => 'PAM: аутентификация и политика паролей в ОС Альт',
            'summary' => 'Стеки PAM, TCB и pam_tcb, pam_passwdqc и /etc/passwdqc.conf, цепочки include; отличия от РедОС и «Астра Линукс».',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_06_theory.md',
            'practice' => <<<'MD'
## Практикум в контейнере

Нажмите **Выделить стенд** — поднимется образ **`os-alt-lab-m6`**. Среда **уже «сломана»** при сборке: пять независимых сценариев на одном контейнере; починка одного не должна ломать остальные.

**Рабочая оболочка:** в контейнере есть **`passwd`**, **`sudo`**, **`journalctl`**, **`su`**.

**Утилита `faillock`:** в образ явно установлен пакет **util-linux** (на «Альт» бинарник может быть **`/sbin/faillock`** или **`/usr/sbin/faillock`**). В **`/etc/profile.d/`** в **`PATH`** добавлены **`/usr/sbin`** и **`/sbin`**, чтобы **`faillock`** находился из обычной интерактивной оболочки. Просмотр и сброс счётчика выполняй от **root** или через **`sudo faillock …`**.

**Важно про `passwd`:** смена пароля **другому** пользователю (`passwd testuser`) доступна **только суперпользователю**. Если приглашение **`student@…`**, выполни **`sudo -i`** (пароль не запрашивается) и работай как **`root@…`**, либо одной командой: **`sudo passwd testuser`**.

**Учётные записи:** для заданий **1–4** — **`testuser`**, начальный пароль **`L@b6pass!`**. Для задания **5** отдельная учётка **`lockuser`** с паролем **`L@b6lock!`**: при сборке образа для неё набран счётчик **faillock** после неудачных попыток входа; в заданиях **1–4** не меняй пароль **lockuser**, чтобы сценарии не пересекались.

**На странице курса:** сначала **Проверить результат**, при успехе — **Принять результат**. Блоки **подсказок** внизу каждого задания скрыты до первой автопроверки, в которой **не набран полный балл** (после этого подсказки появятся в этом же тексте).

---

### Задание 1. Политика паролей не работает

**Ситуация:** администратор безопасности проверил систему и обнаружил, что **root** может задать пользователю **тривиальный** пароль (например один символ). Политика passwdqc в системе есть, но **не действует** так, как требует методичка: **слишком слабый пароль не должен был бы приниматься**, в том числе когда его задаёт root для **`testuser`**.

**Важно (частая путаница):** пока в **`/etc/pam.d/passwd`** у **`pam_passwdqc.so`** в аргументе **`config=`** указан **`/etc/passwdqc_backup.conf`**, команда **`passwd`** читает **именно этот** файл, а **не** то, что вы правите в **`/etc/passwdqc.conf`**. Поэтому смена **`enforce=`** в **`/etc/passwdqc.conf`** может **не менять** поведение `passwd`, пока не выполнено **задание 3** (в PAM нужно указать **`config=/etc/passwdqc.conf`**). Проверь: `grep pam_passwdqc /etc/pam.d/passwd` и при необходимости сравни **`grep enforce`** в обоих файлах.

**Проверь сам** (обязательно **под root**, см. выше про `student` / `sudo -i`):

```bash
# 1) Интерактивно (как в методичке): смена пароля testuser на один символ «1»
passwd testuser
# введи новый пароль: 1
# подтверждение: 1
#
# При текущей «поломке» часто увидишь строки вида:
#   passwd: updating all authentication tokens for user testuser.
#   passwd: all authentication tokens updated successfully.
# — это и есть симптом: тривиальный пароль принят (для ИБ это ошибка).
# Если вместо этого сразу «Authentication token manipulation error» — стек TCB/pam
# в контейнере может отреагировать иначе; тогда для отчёта используй строку (2).

# 2) Однозначная проверка «root задал слабый пароль пользователю» (обходит особенности интерактива):
echo 'testuser:1' | chpasswd
echo $?
# при поломке ожидаемо: 0 (успех команды = тривиальный пароль реально установлен)
```

Если видишь **`only superuser can give different username`** — команда запущена не от root (нужен **`sudo -i`** или **`sudo passwd testuser`**).

**Важно:** если для проверки ты реально сменил пароль **`testuser`** на **`1`**, верни учебный: **`echo 'testuser:L@b6pass!' | chpasswd`** — для задания **4** понадобится **`testuser`** с паролем **`L@b6pass!`**.

**Твоя задача:** в типовом для «Альт» файле политики **passwdqc** найти параметр, который задаёт, **к кому** применяется политика, и изменить его так, чтобы политика действовала **на всех пользователей, включая root** (конкретное значение — из тетради по модулю).

> **Подсказка:** параметр называется **`enforce`**. Допустимые значения в документации passwdqc: **`none`**, **`users`**, **`everyone`**. Посмотри текущее значение: `grep 'enforce' /etc/passwdqc.conf`. Цель — такое значение **`enforce`**, при котором проверка распространяется и на root (см. теорию модуля).

---

### Задание 2. Никакой пароль не принимается

**Ситуация:** пользователи жалуются: **`passwd`** отклоняет **любой** пароль, даже очень сложный.

**Проверь сам** (под root):

```bash
passwd testuser
# введи сложный пароль, например: C0mplexP@ssw0rd!
#
# При «поломке» ожидаемо что-то вроде:
#   BAD PASSWORD: ... too short
# (формулировка может чуть отличаться — суть: отказ для явно длинного пароля)
```

**Твоя задача:** в файле политики **passwdqc** найти параметр **`min`** и выставить **разумную** политику по методичке: пароли из **одного** класса символов **запрещены**; из **двух** — минимум **24** символа; из **трёх** — **11**; из **четырёх** — **8**; **парольная фраза** — **7** символов. Итоговая строка **`min=`** должна совпасть с учебным эталоном из тетради (пять полей через запятую).

> **Подсказка:** параметр **`min`** — пять значений через запятую (по одному на «уровень» сложности). **`disabled`** в поле означает «класс запрещён». Текущая строка часто выглядит как «всё disabled». Эталон для стенда: **`min=disabled,24,11,8,7`**.

---

### Задание 3. Неверный файл политики для `passwd` (или ошибка токена)

**Ситуация:** для службы **`passwd`** в PAM указан **не тот** файл политики **`pam_passwdqc`** (или из-за этого **`passwd`** падает с ошибкой токена / не применяет то, что вы правите в **`/etc/passwdqc.conf`**).

**Проверь сам** (под root):

```bash
grep pam_passwdqc /etc/pam.d/passwd
# при «поломке» в строке pam_passwdqc будет config= не на /etc/passwdqc.conf

passwd testuser
# при неверном config= возможны: ошибка токена, ошибка pam_passwdqc в stderr,
# либо passwd всё ещё читает «резервный» конфиг — тогда правки в /etc/passwdqc.conf не ощущаются (см. задание 1).
```

Дополнительно (в контейнере поднят **journald**):

```bash
journalctl -t passwd -n 20 --no-pager
# если пусто:
journalctl _COMM=passwd -n 20 --no-pager
```

Если записей мало или нет, ориентируйся на **строку ошибки `passwd` выше** и на цепочку **PAM** в **`/etc/pam.d/`** (в тетради указано, какой файл отвечает за локальную смену пароля).

**Твоя задача:** по сообщению об ошибке найти **неверный аргумент `config=`** у **pam_passwdqc** в файле, который обрабатывает именно **службу `passwd`**, и указать **основной** рабочий файл политики в системе (типичное имя — из стандартной поставки «Альт», **без** «резервных» имён вроде `_backup`).

> **Подсказка:** в **`journalctl`** ищи строки про **pam_passwdqc** и «нет такого файла» / **No such file**. Затем: `grep 'passwdqc' /etc/pam.d/passwd` — обрати внимание на аргумент **`config=`**.

---

### Задание 4. Аутентификация не проверяет пароль

**Ситуация:** в систему можно войти так, будто **пароль не проверяется** (вход возможен с заведомо неверным паролем).

**Проверь сам** (от root; учётка **student** входит в **wheel** и тоже может вызывать **`su`**):

```bash
su - testuser
# на запрос пароля введи случайный набор символов (не L@b6pass!)
#
# При «поломке» ожидаемо: вход в shell testuser УСПЕШЕН (приглашение testuser@…)
# — так быть не должно при неверном пароле.
```

**Твоя задача:** выяснить, **почему** PAM не выполняет настоящую проверку пароля. Начни с **`/etc/pam.d/system-auth`**: это **симлинк**. Посмотри, **куда** он указывает, и что в целевом файле (сравни с полной учебной цепочкой из методички). Исправь ссылку так, чтобы использовалась **правильная** цепочка (**не** упрощённый фрагмент с «всё разрешено»).

> **Подсказка:** симлинк **`system-auth`** должен указывать на файл **`system-auth-local`**. Полезно: `ls /etc/pam.d/system-auth*` и `grep 'pam_tcb' /etc/pam.d/system-auth-local`.

---

### Задание 5. Пользователь «заблокирован»

**Ситуация:** учётная запись **`lockuser`** не может войти в систему, хотя вводится **верный** пароль **`L@b6lock!`** (эта учётка **не** используется в заданиях 1–4 с **`passwd`**, чтобы не путать с **`testuser`**).

**Проверь сам:**

```bash
su - lockuser
# пароль: L@b6lock!
#
# Ожидаемо при «поломке»:
#   su: Authentication failure
```

**Твоя задача:** диагностировать **блокировку** (механизм **faillock**) и **снять** её административно так, чтобы **`su - lockuser`** с паролем **`L@b6lock!`** снова выполнялся успешно.

> **Подсказка:** **`faillock --dir /var/lib/os-alt-lab-m6/faillock --user lockuser`** (или с **`sudo`**). Сброс: **`faillock --dir /var/lib/os-alt-lab-m6/faillock --user lockuser --reset`**. См. **`man faillock`** — в контейнере счётчики лежат не в **`/run`**, чтобы они не пропадали при старте.

---

Когда закончишь правки по всем пяти сценариям, на странице курса нажми **Проверить результат**, затем при успехе — **Принять результат**.
MD,
        ],
        7 => [
            'letter' => 'G',
            'title' => 'Контроль целостности файлов. Osec и rpm -V в Альт Линукс',
            'summary' => 'FIM: эталон и проверки; rpm -Va/--restore; Osec в Альт; конфигурация DIRS/EXCLUDE; порядок внедрения; сравнение с Ред ОС и «Астра».',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_07_theory.md',
            'practice' => <<<'MD'
## Практика в Docker (`os-alt-lab-m7`)

Выделите контейнер с образом **`os-alt-lab-m7`**. При **первом запуске** контейнера среда намеренно «ломается» скриптом **`/opt/lab/lab-m7-setup.sh`** (в образе для справки есть unit **`os-alt-lab-m7-setup.service`** — аналог **oneshot** в **systemd**). Работайте под пользователем **`student`**; команды, требующие root, выполняйте через **`sudo`** (пароль **`labstudy`** не нужен для **`sudo`** в этой лабе).

**Как проходить практику:** три задания ниже идут в том же порядке, что и автопроверка. Выполняйте **шаги по очереди**; после шага сверяйтесь с «ожидаемым результатом» — так вы повторяете рабочий сценарий администратора и видите, **зачем** каждая операция.

> **Технические детали образа (зачем это важно):** в **`os-alt-lab-m7`** команда **`rpm --restore`** реализована обёрткой и вызывает **`apt-get install --reinstall`** (нужен доступ к репозиториям). Утилита **`osec`** — через обёртку **`/usr/bin/osec`**: без аргументов подставляется **`/etc/osec/osec.conf`** (списки **`DIRS=`** и **`EXCLUDE=`**); для «прод»-профиля — **`osec -f /etc/osec/osec-prod.conf`**. У «настоящего» **osec** ключ **`-f`** — это файл со **списком путей** по строкам; в лабе путь **`/etc/osec/osec-prod.conf`** обрабатывается обёрткой особым образом (см. **`/usr/bin/osec`** в контейнере).

**Сдача:** когда все три задания выполнены в терминале, на странице курса нажмите **Проверить результат**, затем при успехе — **Принять результат**. Скрипт **`sudo /opt/lab-check/check.sh`** в первой строке выводит **`# lab7-check:`** — краткая сводка состояния (**режим `chsh`**, **`backdoor`** в **`passwd`**, начало строки **`EXCLUDE`**); её удобно сравнить с тем, что вы видите у себя после шагов.

**Важно:** для **`/usr/bin/chsh`** в этом образе пакет **`shadow-change`**, не **`util-linux`**. При **`apt`** иногда появляется сообщение вроде **`control-restore: ... chsh ... cannot be restored`** — ориентируйтесь на **`stat`** и **`rpm -Va`**: права **`chsh`** в лабе доводятся после переустановки пакета. Выполняйте команды **по одной строке** (дождитесь приглашения **`bash-4.4$`** после длинного вывода **`apt`**).

---

### Задание 1. «Найди что подменили»

**Задача:** убедиться, что критичные файлы **совпадают с эталоном RPM** (целостность поставки). Так вы отвечаете на вопрос: *подменили ли бинарь или права так, что это не «легальная» конфигурация пакета?*

**Ситуация:** есть подозрение на несанкционированные правки системных файлов; нужно **обнаружить расхождения** с базой пакетного менеджера и **вернуть файлы к виду из пакета**.

**Шаг 1 — зафиксировать нарушения.**  
**Зачем:** **`rpm -Va`** сравнивает установленные файлы с метаданными RPM (размер, хэш, права и т.д.).  
Выполните: **`sudo rpm -Va`**. Обратите внимание на строки с **`/bin/ls`** и **`/usr/bin/chsh`** (в подсказке по фильтру теории можно использовать **`grep`** по признакам **`5`** — содержимое, **`M`** — права).

**Шаг 2 — понять, какому пакету принадлежит каждый файл.**  
**Зачем:** восстанавливать нужно **именно тот пакет**, который «владеет» путём; иначе вы поставите лишнее или не то.  
Выполните: **`sudo rpm -qf /bin/ls /usr/bin/chsh`**. Запомните имена пакетов (в лабе это обычно **`coreutils`** и **`shadow-change`**; **не** подставляйте **`util-linux`**, если **`rpm -qf`** для **`chsh`** указал **`shadow-change`**).

**Шаг 3 — восстановить эталон пакета.**  
**Зачем:** **`rpm --restore`** в этой среде переустанавливает файлы из пакета и убирает «левые» правки содержимого и прав.  
Выполните **одной** командой: **`sudo rpm --restore coreutils shadow-change`**. Дождитесь окончания (**`Done.`**); не прерывайте вывод **`apt`**.

**Шаг 4 — проверить, что риск устранён.**  
**Зачем:** иначе защита формальна: взлом мог остаться в файле или в правах (**`chsh`** без setuid — отдельная уязвимость/аномалия).  
Выполните: **`sudo stat -L -c '%a' /usr/bin/chsh`** — ожидается **4755** или **4711**; затем снова **`sudo rpm -Va`** и убедитесь, что **нет** предупреждений по **`/bin/ls`** и **`/usr/bin/chsh`**.

**Если задание 1 не проходит:** повторите шаги 3–4. Если вы уже делали задания 2–3, но **не** выполняли шаг 3, **`chsh`** останется **777** — это нормально исправляется возвратом к шагу 3, после чего при необходимости снова обновите эталоны **Osec** (шаги заданий 2 и 3).

---

### Задание 2. «Osec обнаружил изменение — найди угрозу»

**Задача:** отработать цикл **«сигнал → расследование → устранение → обновление эталона»** для контроля целостности **вне** пакетов RPM — по списку путей в **`/etc/osec/osec.conf`**.

**Ситуация:** **Osec** уже построил эталонную базу по «учебному» конфигу; при очередном запуске он должен либо подтвердить совпадение, либо показать **что** и **где** изменилось относительно последнего «хорошего» состояния.

**Шаг 1 — получить отчёт.**  
**Зачем:** без запуска проверки вы не увидите, какой из контролируемых файлов «всплыл».  
Выполните: **`sudo osec`**. Прочитайте вывод (в контейнере **`/var/log/osec/osec.log`** может отсутствовать — ориентируйтесь на stdout).

**Шаг 2 — локализовать угрозу в `passwd`.**  
**Зачем:** в отчёте обычно фигурирует **`/etc/passwd`**; лишняя учётная запись с **uid=0** даёт **второго суперпользователя** — это критический инцидент.  
Выполните: **`sudo awk -F: '$3==0' /etc/passwd`** — должны увидеть **только `root`**. Если есть ещё логин (в лабе — **`backdoor`**), зафиксируйте строку: **`sudo grep -n '^backdoor:' /etc/passwd`**.

**Шаг 3 — убрать лишнюю запись и обновить эталон Osec.**  
**Зачем:** пока запись есть, система остаётся скомпрометированной; пока эталон не обновлён, **Osec** будет снова ругаться на то же самое.  
Выполните: **`sudo sed -i '/^backdoor:/d' /etc/passwd`**. Проверьте: **`sudo grep -c '^backdoor:' /etc/passwd`** → **0**. Затем снова **`sudo osec`** — отчёт не должен содержать **`changed` / `new` / `removed`** по вашим ключевым путям (в лабе достаточно «чистого» прохода после исправления).

---

### Задание 3. «Найди дыру в конфигурации Osec»

**Задача:** понять, как **ошибка в `EXCLUDE`** обнуляет смысл мониторинга (исключили слишком важные пути — и инструмент «слеп» к компрометации).

**Ситуация:** для «прод»-профиля используется **`/etc/osec/osec-prod.conf`**. В **`EXCLUDE`** ошибочно перечислены пути, которые как раз нужно контролировать (**`passwd`**, **`sudoers`**, **`sudo`**, **`su`**).

**Шаг 1 — прочитать конфиг и осознать ошибку.**  
**Зачем:** без чтения **`EXCLUDE`** нельзя объяснить, почему проверка «не видит» важные файлы.  
Откройте файл (**`cat`**, **`less`** или **`nano`**): **`/etc/osec/osec-prod.conf`**. Найдите строку **`EXCLUDE=`** и перечисленные в ней пути.

**Шаг 2 — убедиться на практике, что контроль отключён.**  
**Зачем:** связать конфиг с поведением: при широком **`EXCLUDE`** изменения в **`sudoers`** не попадут в отчёт «прод»-профиля.  
Выполните: **`sudo osec -f /etc/osec/osec-prod.conf`** и посмотрите, отражается ли в выводе работа с **`sudoers`** (в лабе в **`/etc/sudoers`** есть маркер **`# lab7-backdoor`** — при «дырявом» **`EXCLUDE`** сигнал по **`sudoers`** не проявится так, как после исправления).

**Шаг 3 — исправить `EXCLUDE` (оставить только допустимые исключения).**  
**Зачем:** вернуть мониторинг на критические пути; **`EXCLUDE`** должен исключать только то, что **осознанно** не считается угрозой (часто это «шумные» или перезаписываемые служебные файлы).  
Замените строку **`EXCLUDE=`** так, чтобы в кавычках остались только **`/etc/mtab`**, **`/etc/resolv.conf`**, **`/etc/adjtime`** (без **`passwd`**, **`sudoers`**, **`sudo`**, **`su`**). Надёжно сделать одной командой, заменив всю строку целиком:

```bash
sudo perl -i -pe 's|^EXCLUDE=.*|EXCLUDE="/etc/mtab /etc/resolv.conf /etc/adjtime"|' /etc/osec/osec-prod.conf
```

Проверьте: **`sudo grep '^EXCLUDE=' /etc/osec/osec-prod.conf`**.

**Шаг 4 — пересобрать эталон «прод»-профиля.**  
**Зачем:** после смены конфигурации старая база **Osec** для этого профиля не соответствует новым правилам; нужен новый «снимок» допустимого состояния.  
Выполните: **`sudo osec -f /etc/osec/osec-prod.conf`** ещё раз.

> Для справки по «чистому» **osec** без обёртки см. **`man osec`** (ключ **`-f` / `--file`** — файл со списком каталогов для обхода).

---
MD
        ],
        8 => [
            'letter' => 'H',
            'title' => 'Аудит событий. auditd в Альт Линукс',
            'summary' => 'auditd vs journald; установка; типы правил; ausearch/aureport; кейсы; Альт и «Астра»; минимальный hardening.rules.',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_08_theory.md',
            'practice' => require __DIR__.'/snippets/module_08_practice_lab.php',
        ],
        9 => [
            'letter' => 'I',
            'title' => 'Polkit, модуль ролей и control',
            'summary' => 'Управление привилегиями приложений в ОС Альт Линукс.',
            'module_exam_time_limit_minutes' => 60,
            'theory' => '@snippet:module_09_theory.md',
            'practice' => require __DIR__.'/snippets/module_09_practice_lab.php',
        ],
    ],

    'module_quizzes' => [
            1 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_01_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_01_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_01_module_exam_questions.json',
                    __DIR__.'/snippets/module_01_module_exam_questions.php'
                ),
            ],
            2 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_02_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_02_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_02_module_exam_questions.json',
                    __DIR__.'/snippets/module_02_module_exam_questions.php'
                ),
            ],
            3 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_03_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_03_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_03_module_exam_questions.json',
                    __DIR__.'/snippets/module_03_module_exam_questions.php'
                ),
            ],
            4 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_04_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_04_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_04_module_exam_questions.json',
                    __DIR__.'/snippets/module_04_module_exam_questions.php'
                ),
            ],
            5 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_05_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_05_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_05_module_exam_questions.json',
                    __DIR__.'/snippets/module_05_module_exam_questions.php'
                ),
            ],
            6 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_06_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_06_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_06_module_exam_questions.json',
                    __DIR__.'/snippets/module_06_module_exam_questions.php'
                ),
            ],
            7 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_07_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_07_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_07_module_exam_questions.json',
                    __DIR__.'/snippets/module_07_module_exam_questions.php'
                ),
            ],
            8 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_08_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_08_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_08_module_exam_questions.json',
                    __DIR__.'/snippets/module_08_module_exam_questions.php'
                ),
            ],
            9 => [
                'theory_quiz' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_09_theory_quiz_questions.json',
                    __DIR__.'/snippets/module_09_theory_quiz_questions.php'
                ),
                'module_exam' => CourseQuizBankLoader::loadBankWithFallback(
                    __DIR__.'/snippets/module_09_module_exam_questions.json',
                    __DIR__.'/snippets/module_09_module_exam_questions.php'
                ),
            ],
    ],

    'final_lab_questions' => CourseQuizBankLoader::loadBankWithFallback(
        __DIR__.'/snippets/final_lab_questions.json',
        null
    ),
];
