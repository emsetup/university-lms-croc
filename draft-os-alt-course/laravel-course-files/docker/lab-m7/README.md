# Лабораторный образ модуля 7 (контроль целостности)

Образ: **`os-alt-lab-m7:latest`**. Три задания: **rpm -V** (восстановление из пакетов), **Osec** по **`/etc/osec/osec.conf`**, **дыра в EXCLUDE** в **`/etc/osec/osec-prod.conf`**.

## Сборка

Из каталога **`laravel-course-files`**:

```bash
docker build -f docker/lab-m7/Dockerfile -t os-alt-lab-m7:latest .
```

## Первый запуск контейнера

«Поломка» **не** вшита в слой сборки: при **первом** старте **`entrypoint.sh`** вызывает **`/opt/lab/lab-m7-setup.sh`** (флаг **`/var/lib/os-alt-lab-m7/.setup-done`**). В образе есть unit **`os-alt-lab-m7-setup.service`** — для полноценного systemd (PID 1) можно включить его вместо ручного вызова из entrypoint.

## Пакеты и совместимость

- Устанавливаются **`osec`**, **`rpm`**, **`coreutils`** и базовые утилиты лабы.
- У **`rpm`** в ветви **p10** часто **нет** **`--restore`**: в **`/usr/bin/rpm`** — обёртка, для **`rpm --restore пакет`** выполняется **`apt-get install --reinstall`** (нужна сеть до репозиториев).
- Утилита **`osec`** из пакета переименована в **`osec.real`**. В **`/usr/bin/osec`** — учебная обёртка: без аргументов подставляет списки из **`/etc/osec/osec.conf`** (**`DIRS=`**, **`EXCLUDE=`**) и отдельные базы в **`/var/lib/osec/lab-main`** / **`lab-prod`**. Вызов **`osec -f /etc/osec/osec-prod.conf`** обрабатывается обёрткой (у «чистого» **osec** ключ **`-f`** — это файл со **списком каталогов**; см. **`man osec`**).
- Для проверок платформы **`check.sh`** задаёт **`OSEC_LAB_READONLY=1`**, обёртка добавляет **`-r`**, чтобы не перезаписывать эталон при проверке.

## Проверка

Скрипт **`examples/practice-checks/module_07/check.sh`** → **`/opt/lab-check/check.sh`**.

Формат вывода: **`TASKn:PASS`** или **`TASKn:FAIL:сообщение`**, строка **`RESULT:пройдено:провалено`**, **`OUTCOME:PASS`**, маркер **`===PRACTICE_RESULT_JSON===`** с баллами для Laravel. **Код выхода всегда 0** — итог по строкам **`RESULT`** / JSON.

## Файлы

| Путь | Назначение |
|------|------------|
| `lab-m7-setup.sh` | **`/opt/lab/lab-m7-setup.sh`**: правки **ls**/**chsh**, конфиги Osec, эталоны, **backdoor** в passwd, строка в **sudoers** |
| `files/usr-bin-rpm-lab7.sh` | Обёртка **`rpm`** |
| `files/usr-bin-osec-lab.sh` | Обёртка **`osec`** |
| `files/etc/systemd/system/os-alt-lab-m7-setup.service` | Oneshot (справочно) |
