## Репозитории и пакеты

Работайте в контейнере под пользователем `student`.

В этой практике нужно выполнить 5 заданий по `rpm`, `apt`, `apt-cache`, `apt-repo`.

Заполняйте только требуемые файлы отчета:
- задание 1 -> `~/lab-m2-task1.txt`
- задание 5 -> `~/lab-m2-task5.txt`

---

## Задание 1. Детектив с rpm — найди владельца файла

Ситуация: нужно выяснить, какому пакету принадлежит команда `passwd`, получить информацию о пакете и список его файлов.

Сделайте команды:

```bash
rpm -qf "$(command -v passwd)"
rpm -q --queryformat 'Name: %{NAME}\nVersion: %{VERSION}-%{RELEASE}\nArch: %{ARCH}\nSummary: %{SUMMARY}\n' <имя_пакета>
rpm -ql <имя_пакета>
rpm -V <имя_пакета>
```

Зафиксируйте результат в файл:

```bash
rpm -qf "$(command -v passwd)" > ~/lab-m2-task1.txt
rpm -q --queryformat 'Name: %{NAME}\nVersion: %{VERSION}-%{RELEASE}\nArch: %{ARCH}\nSummary: %{SUMMARY}\n' "$(rpm -qf "$(command -v passwd)")" >> ~/lab-m2-task1.txt
```

## Задание 2. Сломанный репозиторий — найди и почини

Ситуация: `apt-get update` не работает из-за ошибки в конфигурации репозитория.

Сделайте:

```bash
apt-get update
apt-repo list
cat /etc/apt/sources.list.d/*.list
```

Найдите опечатку в URL и исправьте ее в проблемном `.list`-файле, затем проверьте:

```bash
apt-get update
```

## Задание 3. Проверь целостность и восстанови пакет nano

Ситуация: один из файлов пакета `nano` изменен.

Проверка целостности по базе RPM:

```bash
rpm -V nano
```

В «чистых» RPM-дистрибутивах файлы пакета возвращают командой `rpm --restore пакет`. **В ALT Linux у `rpm` нет `--restore`; восстановление делается через APT — переустановка того же пакета:**

```bash
sudo apt-get install --reinstall -y nano
rpm -V nano
```

Если `rpm -V nano` после переустановки ничего не выводит — целостность восстановлена.

## Задание 4. Установи пакет через apt-get

Нужно установить `htop` корректным порядком.

Сделайте:

```bash
apt-get update
apt-cache search htop
apt-get install -y htop
rpm -q htop
htop --version
```

## Задание 5. Найди пакет git и его зависимости

Нужно посмотреть информацию о пакете `git` и записать имя/версию в отчет.

Сделайте:

```bash
apt-cache search git | grep '^git '
apt-cache show git
apt-cache depends git
apt-cache show git | grep -E '^(Package|Version)' > ~/lab-m2-task5.txt
cat ~/lab-m2-task5.txt
```

---

## Сдача

На странице курса:
- **Запустить контейнер**
- выполнить задания
- **Проверить результат**
- при нужном результате нажать **Принять результат**
