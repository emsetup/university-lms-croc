<?php

/**
 * Итоговый тест модуля 5: сеть — etcnet, NetworkManager (etcnet/native), systemd-networkd, resolvconf, диагностика.
 * 20 вопросов · 100 баллов · порог зачёта 70% (см. CourseScoringService::PASS_THRESHOLD).
 *
 * Подключение в config/course.php (module_quizzes[5]):
 *   'module_exam' => require __DIR__.'/snippets/module_05_module_exam_questions.php',
 *
 * В modules[5] задайте при необходимости:
 *   'module_exam_time_limit_minutes' => 60,
 */
return [
    [
        'points' => 4,
        'q' => 'Администратор создал файл /etc/net/ifaces/eth0/ipv4address с содержимым 10.0.0.5/24, но после ifup eth0 адрес не появился. В чём причина?',
        'a' => [
            'Файл должен называться ipv4addr, а не ipv4address',
            'В файле options не указан CONFIG_IPV4=yes и BOOTPROTO=static',
            'Нужно сначала выполнить systemctl restart NetworkManager',
            'Файл ipv4address работает только с IPv6-интерфейсами',
        ],
        'c' => 1,
    ],
    [
        'points' => 4,
        'q' => 'Администратор настроил DNS через файл /etc/net/ifaces/eth0/resolv.conf, но после перезагрузки /etc/resolv.conf снова пустой. Что произошло?',
        'a' => [
            'Файл /etc/resolv.conf защищён и не обновляется автоматически',
            'Интерфейс eth0 не поднялся при загрузке — в options отсутствует ONBOOT=yes',
            'resolvconf не установлен в системе',
            'DNS нужно прописывать напрямую в /etc/resolv.conf',
        ],
        'c' => 1,
    ],
    [
        'points' => 4,
        'q' => 'Какой режим сетевого интерфейса позволяет обычному пользователю включать и выключать соединение через GUI, но не позволяет менять IP-адрес без прав root?',
        'a' => [
            'Etcnet',
            'systemd-networkd',
            'NetworkManager (native)',
            'NetworkManager (режим etcnet)',
        ],
        'c' => 3,
    ],
    [
        'points' => 4,
        'q' => 'Администратор выполнил nmcli con modify "System eth0" ipv4.method manual. Что это означает?',
        'a' => [
            'Интерфейс переводится в режим etcnet',
            'Интерфейс будет получать адрес по DHCP',
            'Интерфейс переходит на статическую адресацию',
            'Соединение удаляется из NetworkManager',
        ],
        'c' => 2,
    ],
    [
        'points' => 4,
        'q' => 'В файле /etc/nsswitch.conf строка hosts изменена на hosts: dns files. Что изменится в поведении системы?',
        'a' => [
            'Система перестанет использовать /etc/hosts',
            'DNS будет опрашиваться раньше чем /etc/hosts',
            '/etc/hosts станет единственным источником разрешения имён',
            'Изменений не будет — порядок не имеет значения',
        ],
        'c' => 1,
    ],
    [
        'points' => 4,
        'q' => 'Администратор хочет временно назначить IP-адрес на интерфейс для диагностики, не затрагивая конфигурационные файлы. Какая команда подходит?',
        'a' => [
            'ifup eth0 192.168.1.10/24',
            'ip a add 192.168.1.10/24 dev eth0',
            'nmcli con modify eth0 +ipv4.addresses 192.168.1.10/24',
            'echo "192.168.1.10/24" > /etc/net/ifaces/eth0/ipv4address',
        ],
        'c' => 1,
    ],
    [
        'points' => 4,
        'q' => 'Что означает каталог unknown в /etc/net/ifaces/?',
        'a' => [
            'Интерфейс с неизвестным типом — будет проигнорирован системой',
            'Резервный каталог для интерфейсов без собственной конфигурации при hotplug',
            'Каталог для хранения устаревших настроек',
            'Интерфейс в режиме NetworkManager (native)',
        ],
        'c' => 1,
    ],
    [
        'points' => 5,
        'q' => 'Какие утверждения про файл options в etcnet верны? Отметьте все подходящие.',
        'a' => [
            'DISABLED=yes означает, что etcnet не будет управлять этим интерфейсом',
            'NM_CONTROLLED=yes и DISABLED=yes одновременно соответствуют режиму NetworkManager (native)',
            'BOOTPROTO=dhcp требует установленного пакета dhcpcd для работы',
            'ONBOOT=no означает, что интерфейс вообще нельзя поднять вручную',
            'Параметр MODULE указывается, когда udev не подгружает драйвер автоматически',
        ],
        'c' => [0, 1, 2, 4],
    ],
    [
        'points' => 5,
        'q' => 'Какие команды nmcli корректны для настройки статического IP? Отметьте все подходящие.',
        'a' => [
            'nmcli con modify "eth0" ipv4.addresses 10.0.0.5/24',
            'nmcli con modify "eth0" ipv4.method manual',
            'nmcli con modify "eth0" ipv4.gateway 10.0.0.1',
            'nmcli con static "eth0" ip 10.0.0.5',
            'nmcli con up "eth0"',
        ],
        'c' => [0, 1, 2, 4],
    ],
    [
        'points' => 5,
        'q' => 'Какие файлы могут присутствовать в каталоге /etc/net/ifaces/eth0/ при статической настройке? Отметьте все подходящие.',
        'a' => [
            'options',
            'ipv4address',
            'ipv4route',
            'resolv.conf',
            'nameserver.conf',
        ],
        'c' => [0, 1, 2, 3],
    ],
    [
        'points' => 5,
        'q' => 'Какие утверждения про resolvconf верны? Отметьте все подходящие.',
        'a' => [
            'Формирует /etc/resolv.conf автоматически при активации интерфейса',
            'Вызывается командой ifup при поднятии интерфейса',
            'Позволяет объединить DNS-настройки от нескольких интерфейсов',
            'Заменяет dig и nslookup для проверки DNS',
            'Ручное редактирование /etc/resolv.conf будет перезаписано при следующем ifup',
        ],
        'c' => [0, 1, 2, 4],
    ],
    [
        'points' => 5,
        'q' => 'Какие утверждения про ss верны? Отметьте все подходящие.',
        'a' => [
            'Является современной заменой устаревшего netstat',
            'Флаг -l показывает только слушающие сокеты',
            'Флаг -p требует прав root для отображения процессов',
            'ss -atnp показывает все активные UDP-соединения',
            'ss -s выводит статистику по сокетам',
        ],
        'c' => [0, 1, 2, 4],
    ],
    [
        'points' => 5,
        'q' => 'Администратор переходит с NetworkManager на чистый etcnet. Какие шаги обязательны? Отметьте все подходящие.',
        'a' => [
            'systemctl stop NetworkManager',
            'systemctl disable NetworkManager',
            'systemctl mask NetworkManager',
            'Изменить NM_CONTROLLED=no в файле options каждого интерфейса',
            'Удалить пакет NetworkManager из системы',
        ],
        'c' => [0, 1, 2, 3],
    ],
    [
        'points' => 4,
        'q' => 'Сопоставление: какие пары «команда — менеджер сети» верны? Отметьте все верные.',
        'a' => [
            'ifup eth0 — etcnet (классический стек /etc/net)',
            'nmcli con up "System eth0" — NetworkManager',
            'networkctl up eth0 — systemd-networkd',
            'ifup eth0 — NetworkManager',
            'networkctl up eth0 — etcnet',
        ],
        'c' => [0, 1, 2],
    ],
    [
        'points' => 4,
        'q' => 'Сопоставление: какие пары «путь — назначение» верны? Отметьте все верные.',
        'a' => [
            '/etc/net/ifaces/eth0/ipv4route — настройки маршрутов через этот интерфейс',
            '/etc/net/ifaces/eth0/resolv.conf — DNS для конкретного интерфейса',
            '/etc/net/ifaces/default/ — настройки по умолчанию для всех интерфейсов etcnet',
            '/etc/systemd/network/ — конфигурационные файлы systemd-networkd',
            '/etc/net/ifaces/default/ — только устаревшие настройки, система их игнорирует',
        ],
        'c' => [0, 1, 2, 3],
    ],
    [
        'points' => 4,
        'q' => 'Сопоставление: какие пары «сценарий — рекомендуемый режим» верны? Отметьте все верные.',
        'a' => [
            'Сервер с фиксированным IP, максимальный контроль — etcnet',
            'Рабочая станция: пользователь сам подключается к Wi‑Fi — NetworkManager (native)',
            'Корпоративная станция: параметры задаёт администратор, пользователь только вкл/выкл — NetworkManager (режим etcnet)',
            'Офисный сервер (профиль установки Альт) — systemd-networkd',
            'Корпоративная станция — только etcnet без NetworkManager',
        ],
        'c' => [0, 1, 2, 3],
    ],
    [
        'points' => 8,
        'q' => 'Администратор настроил два интерфейса: eth0 в режиме etcnet, eth1 в режиме NetworkManager (etcnet). После перезагрузки eth1 не поднялся. Коллега говорит, что нужно выполнить ifup eth1. Он прав?',
        'a' => [
            'Да — ifup работает для любого режима',
            'Нет — ifup поднимает интерфейсы под управлением etcnet; для eth1 под NetworkManager нужно активировать соединение через nmcli con up',
            'Нет — нужно перезапустить systemctl restart network',
            'Да — в режиме NetworkManager (etcnet) ifup и nmcli взаимозаменяемы',
        ],
        'c' => 1,
    ],
    [
        'points' => 7,
        'q' => 'На сервере два интерфейса: eth0 в интернет, eth1 во внутреннюю сеть. После настройки eth1 DNS перестал работать — запросы уходят не на тот сервер. Что скорее всего произошло?',
        'a' => [
            'DNS из /etc/net/ifaces/eth1/resolv.conf перезаписал общий /etc/resolv.conf через resolvconf',
            'Нужно перезапустить NetworkManager',
            '/etc/resolv.conf не поддерживает несколько записей nameserver',
            'Проблема в /etc/nsswitch.conf — изменился порядок разрешения имён',
        ],
        'c' => 0,
    ],
    [
        'points' => 8,
        'q' => 'Выполнены команды: systemctl stop NetworkManager; systemctl disable NetworkManager; systemctl restart network. В options интерфейса при этом NM_CONTROLLED=yes и DISABLED=yes. Что произошло и как исправить?',
        'a' => [
            'Нужно снова запустить NetworkManager — без него сеть в этом режиме не работает',
            'Интерфейс в режиме NetworkManager (native): etcnet его не поднимет. Нужно DISABLED=no, NM_CONTROLLED=no, параметры в /etc/net и перезапуск network',
            'Достаточно ifup eth0 — интерфейс поднимется независимо от настроек',
            'Нужно добавить BOOTPROTO=dhcp в options и перезапустить network',
        ],
        'c' => 1,
    ],
    [
        'points' => 7,
        'q' => 'DNS прописали вручную в /etc/resolv.conf, работало, но после ifdown eth0 && ifup eth0 записи пропали. Верное объяснение и решение?',
        'a' => [
            '/etc/resolv.conf сбрасывается только при перезагрузке — добавьте запись в /etc/rc.local',
            '/etc/resolv.conf собирается resolvconf при активации интерфейса и перезаписывает ручные правки; DNS нужно задавать в /etc/net/ifaces/eth0/resolv.conf',
            'Нужно выполнить chattr +i /etc/resolv.conf',
            'Нужно перейти на NetworkManager (native), тогда DNS сохранится',
        ],
        'c' => 1,
    ],
];
