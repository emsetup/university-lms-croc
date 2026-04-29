# Модуль 2 — Теория: Репозитории и пакеты в ОС Альт

## 1. Почему в Альт используется RPM — историческая основа

Чтобы понять, как устроено управление пакетами в Альт, нужно понять, откуда взялась эта архитектура.

RPM (Red Hat Package Manager) — формат пакетов, созданный в Red Hat в 1997 году. Пакет `.rpm` — это архив, содержащий исполняемые файлы, конфигурацию, документацию и метаданные: имя, версию, зависимости, скрипты установки. Формат стал стандартом для большой группы дистрибутивов: Red Hat, Fedora, CentOS, SUSE и Альт Линукс.

Проект ALT Linux Team с самого начала выбрал RPM как основу. Это решение определило всю дальнейшую архитектуру: формат пакетов, базу данных установленных программ, инструменты проверки целостности.

Важное разграничение: в Linux мире существуют два больших лагеря по формату пакетов — RPM-дистрибутивы (Альт, РЕД ОС, Fedora, RHEL, SUSE) и DEB-дистрибутивы (Debian, Ubuntu, Астра Линукс). Это принципиальное различие: пакеты между лагерями напрямую несовместимы.

## 2. Два уровня управления пакетами

В Альт управление пакетами работает на двух уровнях, которые нельзя путать.

### Уровень 1 — менеджер пакетов (`rpm`)

Низкоуровневый инструмент.

Умеет:
- поставить конкретный `.rpm`-файл,
- удалить пакет,
- запросить информацию о пакетах.

Не умеет:
- скачивать пакеты из интернета,
- автоматически разрешать зависимости.

### Уровень 2 — менеджер зависимостей (`apt`)

Высокоуровневый инструмент, работает поверх `rpm`.

Умеет:
- подключаться к репозиториям,
- скачивать пакеты,
- автоматически разрешать зависимости,
- обновлять систему.

Именно `apt` используется в повседневной работе.

Аналогия: `rpm` — гаечный ключ, `apt` — мастер, который знает где купить нужные детали и в каком порядке все собрать.

## 3. RPM — менеджер пакетов

### База данных RPM

Все данные об установленных пакетах хранятся в `/var/lib/rpm`. Это SQLite-база, которую `rpm` использует для запросов.

### Команды rpm

```bash
# Запросы об установленных пакетах
rpm -q bash              # установлен ли пакет bash?
rpm -qi bash             # подробная информация о пакете
rpm -ql bash             # список файлов пакета
rpm -qa                  # все установленные пакеты
rpm -qa | grep bash      # поиск среди установленных
rpm -qf /bin/bash        # какому пакету принадлежит файл

# Запросы по файлу пакета (не по базе)
rpm -qpi bash-5.1-alt1.x86_64.rpm   # информация о файле пакета
rpm -qpl bash-5.1-alt1.x86_64.rpm   # содержимое файла пакета

# Установка пакета напрямую из файла
rpm -Uvh bash-5.1-alt1.x86_64.rpm

# Проверка целостности
rpm -V bash              # соответствуют ли файлы пакету в базе
rpm -Va                  # проверка всех установленных пакетов
```

### Когда использовать rpm напрямую

`rpm` напрямую используется для сервисных задач:
- найти, какому пакету принадлежит файл: `rpm -qf /usr/bin/sudo`,
- проверить целостность: `rpm -V openssh-server`,
- посмотреть состав пакета: `rpm -ql nginx`,
- восстановить файлы пакета: `rpm --restore openssh-server` (в классических RPM-дистрибутивах; **в ALT Linux у `rpm` нет `--restore`**, тот же эффект даёт `apt-get install --reinstall пакет`).

Для установки, удаления и обновления пакетов `rpm` обычно не используется напрямую — это зона ответственности `apt`.

## 4. APT — менеджер зависимостей

APT (Advanced Packaging Tool) изначально создан для Debian/Ubuntu (где работает с `.deb`), но в Альт Линукс используется адаптация APT для работы с RPM-пакетами.

Это важное сочетание:
- формат пакетов от Red Hat (RPM),
- менеджер зависимостей с подходом APT.

APT кэширует метаинформацию о пакетах (имена, версии, зависимости), что позволяет быстро искать пакеты и строить дерево зависимостей.

### Основные команды apt-get

```bash
# Обновить локальный кэш репозитория
apt-get update

# Установить пакет
apt-get install nginx

# Установить без вопросов (для скриптов)
apt-get install -y nginx

# Установить несколько пакетов
apt-get install -y nginx postgresql redis

# Удалить пакет (конфиги сохраняются)
apt-get remove nginx

# Удалить пакет вместе с конфигами
apt-get remove --purge nginx

# Обновить систему
apt-get dist-upgrade

# Удалить ненужные пакеты
apt-get autoremove

# Очистить кэш пакетов
apt-get clean
```

### `apt-cache` — поиск и информация

```bash
apt-cache search gparted
apt-cache show nginx
apt-cache depends nginx
apt-cache rdepends nginx
apt-cache policy nginx
```

### `apt-shell` — интерактивный режим

```bash
apt-shell

# внутри apt-shell:
install nginx
remove nginx
update
quit
```

## 5. Почему нельзя использовать apt-get upgrade

Для Альт Линукс корректное обновление:

```bash
apt-get update && apt-get dist-upgrade
```

`apt-get upgrade` в Альт использовать не рекомендуется.

Почему:
- `upgrade` обновляет только то, что можно обновить без изменения состава зависимостей,
- `dist-upgrade` умеет ставить новые зависимости и убирать конфликтующие пакеты.

В ветках Альт зависимости пакетов могут изменяться, поэтому нужен именно `dist-upgrade`.

## 6. Обновление ядра — отдельная процедура

Ядро не обновляется автоматически при `apt-get dist-upgrade`.

Для ядра используется отдельный цикл:

```bash
# 1. Обновить кэш
apt-get update

# 2. Обновить систему (без ядра)
apt-get dist-upgrade

# 3. Обновить ядро
update-kernel

# 4. Почистить систему
remove-old-kernels
apt-get autoremove
apt-get clean

# 5. Перезагрузиться (если обновляли ядро)
reboot
```

Для GUI-управления ядрами через ЦУС:

```bash
apt-get install alterator-update-kernel
```

## 7. Репозитории: конфигурация и управление

### Файлы конфигурации

- `/etc/apt/sources.list` — основной файл,
- `/etc/apt/sources.list.d/*.list` — дополнительные файлы,
- `/etc/apt/vendor.list` — описание электронных подписей.

### Синтаксис строки репозитория

`rpm [подпись] метод:путь база название`

Где:
- `rpm` — тип (или `rpm-src` для исходников),
- `[подпись]` — указатель на подпись,
- `метод` — `http`, `ftp`, `file`, `cdrom`, `copy`, `ssh`,
- `база` — архитектура (`x86_64`, `noarch`, `x86_64-i586`),
- `название` — набор пакетов (`classic`, `main` и т.д.).

Пример для p10:

```text
rpm [p10] ftp://ftp.altlinux.org/pub/distributions/ALTLinux/p10/branch x86_64 classic
rpm [p10] ftp://ftp.altlinux.org/pub/distributions/ALTLinux/p10/branch x86_64-i586 classic
rpm [p10] ftp://ftp.altlinux.org/pub/distributions/ALTLinux/p10/branch noarch classic
```

### `apt-repo` — управление репозиториями

```bash
apt-repo list
apt-repo list -a
apt-repo add p10
apt-repo add sisyphus
apt-repo rm sisyphus
apt-repo rm all
```

### Репозиторий с ISO

```bash
apt-cdrom add
```

## 8. EPM — единая команда управления пакетами

EPM (Etersoft Package Manager) унифицирует управление пакетами в разных дистрибутивах.

Установка:

```bash
apt-get install eepm
```

Основные команды:

```bash
epm install nginx
epm install --scripts nginx
epm remove nginx
epm update
epm search nginx
```

### `epm play` — установка сторонних программ

```bash
epm play
epm play yandex-browser
epm play --update all
epm play --update zoom
```

### `epm repack` — перепаковка

```bash
epm repack program.deb
```

## 9. Flatpak — изолированные приложения

Flatpak позволяет ставить приложения независимо от базовой системы.

```bash
apt-get install flatpak
gpasswd -a username fuse
flatpak remote-add flathub https://flathub.org/repo/flathub.flatpakrepo
flatpak update
flatpak remotes
flatpak search firefox
flatpak install flathub firefox
flatpak list
flatpak update firefox
flatpak uninstall firefox
flatpak run org.mozilla.firefox
```

## 10. Обновление системы через ЦУС

Для веб-интерфейса ЦУС:

```bash
apt-get install alterator-fbi alterator-updates
systemctl restart alteratord ahttpd
```

Модуль доступен на `https://server:8080` в разделе «Обновление системы».

## 11. Сравнение инструментов — когда что использовать

- Установить пакет из репозитория: `apt-get install nginx`
- Обновить систему: `apt-get dist-upgrade`
- Обновить ядро: `update-kernel`
- Найти пакет: `apt-cache search имя`
- Узнать владельца файла: `rpm -qf /путь/к/файлу`
- Проверить целостность пакета: `rpm -V пакет`
- Установить проприетарную программу: `epm play zoom`
- Переконвертировать `.deb` в `.rpm`: `epm repack prog.deb`
- Установить изолированное приложение: `flatpak install flathub firefox`
- Управлять репозиториями: `apt-repo add p10`

## 12. Типичные ошибки администратора из другого дистрибутива

### Из Ubuntu/Debian

- `apt-get upgrade` -> правильно: `apt-get dist-upgrade`
- ожидать, что ядро обновится само -> правильно: `update-kernel` отдельно
- искать пакеты `.deb` -> в Альт используется `.rpm`

### Из RHEL/РедОС

- `dnf update` -> правильно: `apt-get dist-upgrade`
- `dnf search` -> правильно: `apt-cache search`
- `yum install` -> правильно: `apt-get install`
- команды `rpm -qf` и `rpm -V` применимы и в Альт

### Из Астра Линукс

- не ожидать, что apt в Альт работает с `.deb`
- синтаксис `apt-get` похожий, но под капотом в Альт — RPM
