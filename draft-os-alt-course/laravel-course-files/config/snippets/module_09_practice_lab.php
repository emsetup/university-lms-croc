<?php

/**
 * Практика модуля 9 — Polkit, модуль ролей и control (Docker os-alt-lab-m9). Подключение в course.php:
 * 'practice' => require __DIR__.'/snippets/module_09_practice_lab.php',
 */
return <<<'MD'
**Polkit, модуль ролей и control**

Управление привилегиями приложений в ОС Альт Линукс.

## Практика в Docker (`os-alt-lab-m9`)

Контейнер с **PID 1 = systemd**, пакеты **dbus** и **polkit**. Лабораторные действия **`ru.altcourse.lab.*`** описаны в **`/usr/share/polkit-1/actions/ru.altcourse.lab.policy`** (не трогайте идентификаторы). Пользователь **`student`** в группе **`operators`**; есть учётки **`operator`**, **`auditor`** и группа **`auditors`**. Для правок под **`sudo`** пароль не нужен.

> **Ограничения Docker:** нет полноценной графической сессии и типичного **logind**-контекста. Практика завязана на **файлы** правил **`/etc/polkit-1/rules.d/*.rules`**, правку **`.policy`**, проверки **`pkaction`** / **`pkcheck`**. Не используйте **NetworkManager**, **udisks**, **logind power** — в контейнере нет нужной инфраструктуры. В **polkit 0.120** (как в образе лабы) у **`pkcheck` нет опции `--user`**; субъект задаётся так: **`sudo -u student sh -c 'pkcheck --action-id … --process $$'`** (без **`--process`** будет «Subject not specified»).

**Сдача:** кнопки **Проверить результат** / **Принять результат**; проверка: **`/opt/lab-check/check.sh`** от **root** после **`sudo -i`** (путь **ровно так**, без **`[check.sh](…)`**). От учётки **student** можно **`sudo /opt/lab-check/check.sh`** — в образе и **root**, и **student** в **sudoers** без пароля. Скрипт читает **`/etc/polkit-1/rules.d`**, перезапускает **polkit**; без прав root тест мог бы ошибочно писать «нет файла». **Не вставляйте** текст правил из предпросмотра страницы курса: редакторы подменяют **`action.id`** на **`[action.id](http://…)`**, и **polkit** такое не выполняет — набирайте условие **`if (action.id === "ru.altcourse.lab…")`** вручную в **nano** или копируйте из «сырого» текста без ссылок. После **любых** правок правил или политики: **`sudo systemctl restart polkit`**. В выводе проверки — **`TASK1`…`TASK3`**, **`RESULT:`**, JSON с баллом (максимум **100**, по **3** заданиям).

> **Новый контейнер:** при каждом запуске практики поднимается «чистый» экземпляр: **`10-network-operators.rules`** снова с **`NO`**, **`20-auditors-update.rules`** по умолчанию **нет**, в **`.policy`** снова **`allow_active`** = **`no`** у **service-restart**. Все **три** задания нужно выполнить **в этой** сессии (или заново после пересоздания контейнера).

### Эталон правил (набейте вручную в nano, не копируйте из превью курса)

**`/etc/polkit-1/rules.d/10-network-operators.rules`** — только замените **`NO`** на **`YES`** (или целиком как ниже):

```javascript
polkit.addRule(function(action, subject) {
    if (action.id === "ru.altcourse.lab.network-manage" &&
        subject.isInGroup("operators")) {
        return polkit.Result.YES;
    }
});
```

**`/etc/polkit-1/rules.d/20-auditors-update.rules`** — файл создаёте сами (**`sudo nano …`**):

```javascript
polkit.addRule(function(action, subject) {
    if (action.id === "ru.altcourse.lab.system-update" &&
        subject.isInGroup("auditors")) {
        return polkit.Result.YES;
    }
});
```

---

### Задание 1. Правило «наоборот»

В **`/etc/polkit-1/rules.d/10-network-operators.rules`** группе **`operators`** для действия **`ru.altcourse.lab.network-manage`** ошибочно возвращают **`polkit.Result.NO`**. Нужно **`YES`**. Затем перезапуск **`polkit`** и проверка:

```bash
sudo cat /etc/polkit-1/rules.d/10-network-operators.rules
sudo systemctl restart polkit
pkaction --action-id ru.altcourse.lab.network-manage --verbose
sudo -u student sh -c 'pkcheck --action-id ru.altcourse.lab.network-manage --process $$'
```

---

### Задание 2. Правило для **`auditors`**

Действие **`ru.altcourse.lab.system-update`** по умолчанию запрещено всем. Создайте **`/etc/polkit-1/rules.d/20-auditors-update.rules`** с правилом **`polkit.addRule`**, которое для **`auditors`** возвращает **`YES`** на это действие. Перезапуск **`polkit`**, проверка:

```bash
sudo systemctl restart polkit
sudo -u auditor sh -c 'pkcheck --action-id ru.altcourse.lab.system-update --process $$'
```

---

### Задание 3. Восстановить **`.policy`**

Для **`ru.altcourse.lab.service-restart`** в политике сломано значение **`allow_active`** (должно быть **`auth_admin`**, не **`no`**). Найдите файл **`/usr/share/polkit-1/actions/ru.altcourse.lab.policy`**, исправьте блок действия, перезапустите **`polkit`**, проверьте **`pkaction --verbose`** для этого действия.

```bash
grep -n service-restart /usr/share/polkit-1/actions/ru.altcourse.lab.policy
sudo nano /usr/share/polkit-1/actions/ru.altcourse.lab.policy
sudo systemctl restart polkit
pkaction --action-id ru.altcourse.lab.service-restart --verbose
```
MD;
