# Модуль 5: Сеть — три менеджера и контексты

## 1. Почему в Альт Линукс несколько менеджеров сети

Это один из самых частых вопросов у тех, кто пришёл с Ubuntu или CentOS: там сеть настраивается одним способом, а здесь их сразу **четыре**. Дело в том, что Альт Линукс исторически развивался как универсальная платформа — от встроенных систем до серверов и рабочих станций. Каждый сценарий требует своего подхода к сети.

Важно понять главное: **менеджер сети в Альт — это не просто инструмент настройки, это контекст, в котором живёт сетевой интерфейс.** От выбора менеджера зависят то, где хранятся настройки, кто может их менять и как интерфейс реагирует на события.

Все четыре режима:

| Режим | Где хранятся настройки | Как управлять состоянием |
|--------|-------------------------|---------------------------|
| **Etcnet** | `/etc/net`, ЦУС | `ifup` / `ifdown` |
| **NetworkManager (etcnet)** | `/etc/net`, ЦУС | NetworkManager |
| **NetworkManager (native)** | NetworkManager (`/etc/NetworkManager/system-connections/` и др.) | NetworkManager |
| **systemd-networkd** | `/etc/systemd/network`, ЦУС | `networkctl up` / `down` |

---

## 2. Etcnet — собственная система Альт Линукс

### Что это такое

**Etcnet** — собственная разработка команды Альт, которой нет ни в одном другом дистрибутиве в таком виде. Это не просто набор скриптов, а полноценная система управления сетевыми интерфейсами через файловую систему. Каждый интерфейс получает собственный каталог с набором файлов-параметров. Конфигурация получается максимально прозрачной: посмотрел в каталог — сразу видно всё, что настроено.

### Структура каталогов

```
/etc/net/ifaces/
├── lo/          — loopback интерфейс
├── default/     — настройки по умолчанию для всех интерфейсов
├── unknown/     — конфигурация для hotplug-интерфейсов без своего каталога
└── eth0/        — конфигурация конкретного интерфейса
    ├── options      — основные параметры
    ├── ipv4address  — IP-адрес
    ├── ipv4route    — маршруты
    └── resolv.conf  — DNS для этого интерфейса
```

### Файл `options` — сердце конфигурации

Это главный файл каждого интерфейса. Он определяет тип интерфейса, режим получения адреса и кто им управляет.

**Настройка DHCP:**

```bash
TYPE=eth
DISABLED=no
NM_CONTROLLED=no
BOOTPROTO=dhcp
```

**Настройка статического IP:**

```bash
TYPE=eth
DISABLED=no
NM_CONTROLLED=no
BOOTPROTO=static
CONFIG_IPV4=yes
CONFIG_IPV6=no
ONBOOT=yes
```

**Параметры файла `options`:**

| Параметр | Значения | Смысл |
|----------|-----------|--------|
| `BOOTPROTO` | `dhcp` / `static` | Как получать адрес |
| `NM_CONTROLLED` | `yes` / `no` | Передать управление NetworkManager |
| `DISABLED` | `yes` / `no` | Включить/выключить интерфейс для etcnet |
| `TYPE` | `eth` / `bri` / … | Тип интерфейса |
| `CONFIG_IPV4` | `yes` / `no` | Конфигурировать IPv4 |
| `CONFIG_IPV6` | `yes` / `no` | Конфигурировать IPv6 |
| `ONBOOT` | `yes` / `no` | Поднимать при старте системы |
| `MODULE` | имя модуля | Ядерный модуль для карты, если udev не подгрузил |

### Дополнительные файлы настроек

```bash
# IP-адрес интерфейса
cat /etc/net/ifaces/eth0/ipv4address
# 10.0.0.20/24

# Маршруты через интерфейс
cat /etc/net/ifaces/eth0/ipv4route
# default via 10.0.0.254

# DNS для этого интерфейса
cat /etc/net/ifaces/eth0/resolv.conf
# nameserver 8.8.8.8
```

У каждого интерфейса может быть **свой** `resolv.conf`. Итоговый `/etc/resolv.conf` системы собирается утилитой **resolvconf** из интерфейсных файлов при активации интерфейса. **Редактировать `/etc/resolv.conf` вручную не нужно и не рекомендуется** (подробнее — в разделе 7).

### Как создать конфигурацию для нового интерфейса

```bash
# Посмотреть какие интерфейсы есть в системе
ip l
ls /sys/class/net

# Создать каталог для интерфейса
mkdir /etc/net/ifaces/eth1

# Создать файл options
cat > /etc/net/ifaces/eth1/options << EOF
TYPE=eth
DISABLED=no
NM_CONTROLLED=no
BOOTPROTO=static
CONFIG_IPV4=yes
ONBOOT=yes
EOF

# Задать IP-адрес
echo "192.168.1.10/24" > /etc/net/ifaces/eth1/ipv4address

# Задать шлюз
echo "default via 192.168.1.1" > /etc/net/ifaces/eth1/ipv4route

# Задать DNS
echo "nameserver 77.88.8.8" > /etc/net/ifaces/eth1/resolv.conf
```

### Управление состоянием интерфейсов

```bash
# Поднять интерфейс
ifup eth0

# Опустить интерфейс
ifdown eth0

# Применить изменения конфигурации (перезапуск всей сети)
systemctl restart network

# Добавить в автозапуск
systemctl enable network
```

### Как переключить интерфейс в режим чистого etcnet

Если до этого работал NetworkManager — нужно его отключить:

```bash
systemctl stop NetworkManager
systemctl disable NetworkManager
systemctl mask NetworkManager   # запрещаем случайный запуск
systemctl restart network
```

---

## 3. NetworkManager — два режима работы в Альт

NetworkManager в Альт Линукс работает в **двух принципиально разных режимах** — это важная особенность дистрибутива. Понимание разницы между ними критично для администратора.

### Режим NetworkManager (etcnet)

NetworkManager использует файлы **`/etc/net`** как источник настроек — то есть конфигурация хранится там же, где и в режиме чистого etcnet. Меняются в основном **права и сценарий использования**: параметры редактирует **root** через файлы или ЦУС, а включать и выключать интерфейсы могут обычные пользователи через графическую среду.

В файле `options` это выглядит так:

```bash
TYPE=eth
DISABLED=no
NM_CONTROLLED=yes   # NetworkManager управляет состоянием
BOOTPROTO=static
CONFIG_IPV4=yes
```

### Режим NetworkManager (native)

NetworkManager хранит настройки в **своём формате** в `/etc/NetworkManager/system-connections/`. Обычные пользователи могут менять и параметры, и состояние интерфейсов через GUI. Это типичный режим для рабочих станций.

В файле `options` это выглядит так:

```bash
DISABLED=yes            # etcnet не трогает этот интерфейс
NM_CONTROLLED=yes     # всё отдано NetworkManager
BOOTPROTO=static
```

### Интерфейсы управления NetworkManager

**`nmcli`** — командная строка:

```bash
# Список интерфейсов
nmcli dev

# Список соединений
nmcli con

# Подробная информация о соединении
nmcli con show "System eth0"
nmcli con show "System eth0" | grep IP4.ADDRESS

# Добавить IP-адрес к соединению
nmcli con modify "System eth0" +ipv4.addresses 192.168.1.10/24

# Задать шлюз
nmcli con modify "System eth0" ipv4.gateway 192.168.1.1

# Задать DNS
nmcli con modify "System eth0" ipv4.dns 77.88.8.8

# Переключить метод на статический
nmcli con modify "System eth0" ipv4.method manual

# Активировать соединение
nmcli con up "System eth0"

# Деактивировать
nmcli con down "System eth0"
```

**`nmtui`** — текстовый интерфейс (ncurses). Устанавливается отдельным пакетом:

```bash
apt-get install NetworkManager-tui
nmtui
```

Удобен, когда нет графики, но нужен интерактивный интерфейс — например при первоначальной настройке сервера через консоль.

### Служба NetworkManager

```bash
# Запустить
systemctl start NetworkManager

# Перезапустить после изменений
systemctl restart NetworkManager

# Добавить в автозапуск
systemctl enable NetworkManager
```

---

## 4. systemd-networkd

### Что это такое

**systemd-networkd** — системная служба из пакета systemd; её задача — обнаруживать и настраивать сетевые устройства по мере их появления, а также создавать виртуальные сетевые устройства. В Альт Линукс используется, в частности, в профиле **«Офисный сервер»**.

Главное отличие от etcnet и NetworkManager: конфигурация хранится в **`/etc/systemd/network/`** в формате **`.network`**-файлов, похожих на INI-файлы systemd.

### Настройка статического адреса

```bash
cat > /etc/systemd/network/10-eth0.network << EOF
[Match]
Name=eth0

[Network]
Address=192.168.1.10/24
Gateway=192.168.1.1
DNS=77.88.8.8
EOF
```

### Настройка DHCP

```bash
cat > /etc/systemd/network/10-eth0.network << EOF
[Match]
Name=eth0

[Network]
DHCP=yes
EOF
```

### Управление интерфейсами

```bash
# Посмотреть статус всех интерфейсов
networkctl

# Поднять интерфейс
networkctl up eth0

# Опустить интерфейс
networkctl down eth0

# Статус конкретного интерфейса
networkctl status eth0
```

### Служба

```bash
systemctl enable systemd-networkd
systemctl start systemd-networkd
systemctl restart systemd-networkd
```

---

## 5. Как определить текущий режим интерфейса

Полезный навык администратора — попасть на чужой сервер и быстро понять, что настроено:

```bash
# Посмотреть файл options конкретного интерфейса
cat /etc/net/ifaces/eth0/options

# Если NM_CONTROLLED=no — чистый etcnet
# Если NM_CONTROLLED=yes и DISABLED=no — NetworkManager (etcnet)
# Если NM_CONTROLLED=yes и DISABLED=yes — NetworkManager (native)

# Проверить что запущено
systemctl status NetworkManager
systemctl status network
systemctl status systemd-networkd

# Проверить есть ли файлы systemd-networkd
ls /etc/systemd/network/
```

---

## 6. Управление hostname

Имя узла хранится в файле **`/etc/hostname`** и может содержать доменное имя.

```bash
# Просмотр текущего имени
hostname
hostnamectl

# Изменение имени
hostnamectl set-hostname alt-server.company.local

# Или через прямое редактирование файла
echo "alt-server.company.local" > /etc/hostname
```

**Важно:** система с графическим интерфейсом плохо переносит смену hostname без перезагрузки — многие GUI-приложения кешируют имя при старте. На серверах без GUI достаточно перезапустить командный интерпретатор (или перелогиниться).

---

## 7. Разрешение имён

Тема часто вызывает путаницу, особенно когда DNS настроен, а имена не резолвятся.

### Как работает порядок разрешения

Порядок задаётся в **`/etc/nsswitch.conf`**:

```bash
cat /etc/nsswitch.conf
# hosts: files dns
```

`files dns` означает: сначала **`/etc/hosts`**, потом **DNS**. Это стандартный порядок.

### Статическое разрешение — `/etc/hosts`

```bash
cat /etc/hosts
# 127.0.0.1   localhost localhost.localdomain
# 192.168.50.201   server server.domain.com
```

### Динамическое разрешение — `/etc/resolv.conf`

```bash
cat /etc/resolv.conf
# nameserver 10.0.2.3
# nameserver 8.8.8.8
# search courses.alt
```

Директива **`search`** задаёт доменный суффикс: при запросе `server` система автоматически попробует, например, `server.courses.alt`.

**Важно:** **`/etc/resolv.conf` не нужно править вручную** в типичной схеме с etcnet и **resolvconf** — файл формируется при активации интерфейсов; ручные правки будут перезаписаны (см. ниже).

### Как работает resolvconf

**Проблема, которую решает resolvconf.** В системе может быть несколько активных интерфейсов, у каждого — свои DNS (один от DHCP, другой прописан в `/etc/net/ifaces/eth1/resolv.conf`). Без общего механизма «последний записавший» перетирает остальных — при нескольких интерфейсах это даёт хаос.

**resolvconf** собирает DNS-настройки от активных интерфейсов и формирует единый актуальный **`/etc/resolv.conf`**.

Схематично:

- `/etc/net/ifaces/eth0/resolv.conf` ─┐
- `/etc/net/ifaces/eth1/resolv.conf` ─┼──► **resolvconf** ──► `/etc/resolv.conf`
- ответ DHCP (если используется) ─────┘

При активации интерфейса (`ifup eth0`) etcnet передаёт resolvconf параметры интерфейса; при деактивации (`ifdown eth0`) записи этого интерфейса убираются из общего файла.

**Почему нельзя дописывать вручную в `/etc/resolv.conf`:**

```bash
# Так делать не нужно — при следующем ifup/ifdown resolvconf перезапишет файл
echo "nameserver 8.8.8.8" >> /etc/resolv.conf
```

**Правильно** — прописать DNS в файл интерфейса и переактивировать интерфейс:

```bash
echo "nameserver 8.8.8.8" > /etc/net/ifaces/eth0/resolv.conf
ifdown eth0 && ifup eth0
```

**Проверка:**

```bash
cat /etc/resolv.conf
cat /etc/net/ifaces/eth0/resolv.conf
# В редких случаях: принудительно обновить агрегированный файл
resolvconf -u
```

**Поведение при разных менеджерах (ориентир):**

| Менеджер | Кто обновляет итоговый DNS / resolv.conf |
|----------|------------------------------------------|
| Etcnet | `ifup` / `ifdown` и цепочка с **resolvconf** |
| NetworkManager (etcnet) | NetworkManager при активации соединения, источник — `/etc/net` |
| NetworkManager (native) | NetworkManager из своих профилей |
| systemd-networkd | **systemd-resolved** или прямое управление — смотрите фактическую схему на конкретной системе |

**Диагностика:** если DNS «не работает» при верных настройках интерфейса — посмотрите **`/etc/resolv.conf`**. Если данные устарели или пусты, возможно, не был вызван resolvconf при последнем подъёме интерфейса; часто помогает **`ifdown eth0 && ifup eth0`**.

---

## 8. Диагностика сети

### Утилита `ip` — базовая диагностика

```bash
# Список интерфейсов
ip l
ip link show

# IP-адреса на интерфейсах
ip a
ip addr show
ip -br a           # краткий вывод

# Таблица маршрутизации
ip route
ip r

# Статистика по интерфейсу
ip -s l show eth0

# Временно назначить IP (не сохраняется после перезагрузки)
ip a add 192.168.1.12/24 dev eth0

# Временно добавить маршрут
ip route add 0.0.0.0/0 via 10.0.0.1

# Включить/выключить интерфейс
ip link set eth0 up
ip link set eth0 down
```

### `ping` — проверка доступности

```bash
ping -c 4 192.168.1.1
ping6 -c 4 fe80::1
ping -c 2 ya.ru
```

### `ss` — просмотр сетевых соединений

Современная замена устаревшему `netstat`:

```bash
ss -atnp
ss -tlnp
ss -atunp
ss -s
```

### Утилиты DNS-диагностики

```bash
apt-get install bind-utils
dig ya.ru
dig ya.ru @8.8.8.8
host ya.ru
nslookup ya.ru
```

---

## 9. Сравнение режимов — когда что использовать

| Сценарий | Рекомендуемый режим | Почему |
|----------|---------------------|--------|
| Сервер с фиксированным IP | **Etcnet** | Контроль, настройки в файлах, без лишних зависимостей |
| Сервер сетевых установок | **Etcnet** | Требование документации: интерфейс в режиме etcnet |
| Рабочая станция одного пользователя | **NetworkManager (native)** | Переключение сетей, Wi‑Fi, VPN через GUI |
| Корпоративная рабочая станция | **NetworkManager (etcnet)** | Параметры задаёт admin, пользователь включает/выключает |
| Офисный сервер (профиль установки) | **systemd-networkd** | Используется в этом профиле по умолчанию |

---

## 10. Типичные ошибки при смене менеджера

Самая частая проблема — **конфликт менеджеров**: если на интерфейсе одновременно «тянут» etcnet и NetworkManager, настройки перетирают друг друга. Симптомы: IP пропадает или меняется сам, сеть нестабильна.

**Правило:** на одном интерфейсе должен работать **один** менеджер. Ориентир по `options`:

```bash
# Чистый etcnet — NetworkManager не касается интерфейса
NM_CONTROLLED=no
DISABLED=no

# NetworkManager (etcnet) — NM управляет состоянием, настройки в /etc/net
NM_CONTROLLED=yes
DISABLED=no

# NetworkManager (native) — etcnet отстранён от интерфейса
NM_CONTROLLED=yes
DISABLED=yes
```

Полный уход на etcnet и отключение NetworkManager:

```bash
systemctl stop NetworkManager
systemctl disable NetworkManager
systemctl mask NetworkManager
systemctl restart network
```

---

*Материал согласован с тестом по теории и итоговым тестом модуля 5 в конфигурации курса.*
