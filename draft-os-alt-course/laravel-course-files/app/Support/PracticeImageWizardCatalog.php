<?php

namespace App\Support;

/**
 * Справочник мастера сборки образов практики: шаблоны, подсказки, пресеты скриптов.
 */
final class PracticeImageWizardCatalog
{
    /**
     * @return list<array{id: string, title: string, subtitle: string, template: string, icon: string, os: string, features: array<string, mixed>, packages: list<string>, tag_hint: string}>
     */
    public static function builtinTemplates(): array
    {
        return [
            [
                'id' => 'blank',
                'title' => 'Пустой рецепт',
                'subtitle' => 'Минимальный образ: только базовая ОС и учебный пользователь. Всё настроите сами.',
                'template' => 'lab-m1',
                'icon' => 'panel',
                'os' => 'alt',
                'features' => ['create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => [],
                'tag_hint' => 'my-course-lab:latest',
            ],
            [
                'id' => 'lab-m1',
                'title' => 'Alt · Модуль 1',
                'subtitle' => 'Серверный профиль, systemd, ответы в ~/1.txt…~/4.txt. Готовый check из курса ОС Альт.',
                'template' => 'lab-m1',
                'icon' => 'terminal',
                'os' => 'alt',
                'features' => ['systemd_mode' => true, 'locale' => 'C.UTF-8', 'create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => ['vim-console', 'nano', 'sudo', 'systemd'],
                'tag_hint' => 'os-alt-lab-m1:latest',
            ],
            [
                'id' => 'lab-m3',
                'title' => 'Alt · Модуль 3 (systemd)',
                'subtitle' => 'ЦУС / Alterator: контейнер с PID1=systemd. Тег лучше с суффиксом -systemd.',
                'template' => 'lab-m3',
                'icon' => 'cog',
                'os' => 'alt',
                'features' => ['systemd_mode' => true, 'locale' => 'C.UTF-8', 'create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => [],
                'tag_hint' => 'os-alt-lab-m3-systemd:latest',
            ],
            [
                'id' => 'lab-m7',
                'title' => 'Alt · Модуль 7',
                'subtitle' => 'Сетевые утилиты, типичная практика по сетям.',
                'template' => 'lab-m7',
                'icon' => 'external',
                'os' => 'alt',
                'features' => ['create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => [],
                'tag_hint' => 'os-alt-lab-m7:latest',
            ],
            [
                'id' => 'lab-m8',
                'title' => 'Alt · Модуль 8 (auditd)',
                'subtitle' => 'Аудит и systemd; для lab-daemon нужны особые флаги контейнера.',
                'template' => 'lab-m8',
                'icon' => 'check-circle',
                'os' => 'alt',
                'features' => ['systemd_mode' => true, 'create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => ['audit'],
                'tag_hint' => 'os-alt-lab-m8:latest',
            ],
            [
                'id' => 'final-lab',
                'title' => 'Финальная лабораторная',
                'subtitle' => 'Комплексный образ курса; обычно systemd и расширенный check.',
                'template' => 'final-lab',
                'icon' => 'award',
                'os' => 'alt',
                'features' => ['systemd_mode' => true, 'create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => [],
                'tag_hint' => 'os-alt-final-lab:latest',
            ],
            [
                'id' => 'alma-minimal',
                'title' => 'AlmaLinux · базовый',
                'subtitle' => 'RHEL-семейство, dnf. Для курсов по CentOS/Alma.',
                'template' => 'lab-m1',
                'icon' => 'terminal',
                'os' => 'alma',
                'features' => ['create_user' => ['enabled' => true, 'name' => 'student', 'password' => 'labstudy', 'sudo' => true]],
                'packages' => ['vim', 'sudo', 'systemd'],
                'tag_hint' => 'my-alma-lab:latest',
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, hint: string, default_image: string, pkg_mgr: string}>
     */
    public static function osChoices(): array
    {
        return [
            [
                'value' => 'alt',
                'label' => 'ALT Linux',
                'hint' => 'apt-get, типичный базовый образ registry.altlinux.org/alt/alt:p10. Подходит для курса «ОС Альт».',
                'default_image' => 'registry.altlinux.org/alt/alt:p10',
                'pkg_mgr' => 'apt-get',
            ],
            [
                'value' => 'redos',
                'label' => 'РЕД ОС',
                'hint' => 'Семейство apt/rpm; укажите свой base image из внутреннего registry, если стенд без доступа к публичным зеркалам.',
                'default_image' => '',
                'pkg_mgr' => 'apt-get',
            ],
            [
                'value' => 'astra',
                'label' => 'Astra Linux',
                'hint' => 'Часто требует лицензированных репозиториев — проверьте доступность base image на стенде.',
                'default_image' => '',
                'pkg_mgr' => 'apt-get',
            ],
            [
                'value' => 'alma',
                'label' => 'AlmaLinux 9',
                'hint' => 'dnf, образ almalinux:9. Удобен для практик в стиле RHEL.',
                'default_image' => 'almalinux:9',
                'pkg_mgr' => 'dnf',
            ],
            [
                'value' => 'centos',
                'label' => 'CentOS',
                'hint' => 'Тот же менеджер, что у Alma (dnf). Укажите актуальный тег base image.',
                'default_image' => 'almalinux:9',
                'pkg_mgr' => 'dnf',
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, packages: list<string>}>
     */
    public static function packageGroups(): array
    {
        return [
            ['id' => 'editors', 'title' => 'Редакторы и shell', 'packages' => ['vim-console', 'vim', 'nano', 'bash-completion']],
            ['id' => 'admin', 'title' => 'Администрирование', 'packages' => ['sudo', 'systemd', 'openssh-server', 'cronie']],
            ['id' => 'net', 'title' => 'Сеть', 'packages' => ['iproute2', 'bind-utils', 'traceroute', 'tcpdump', 'curl']],
            ['id' => 'security', 'title' => 'Безопасность', 'packages' => ['audit', 'aide', 'policycoreutils']],
            ['id' => 'locales', 'title' => 'Локали', 'packages' => ['glibc-locales', 'branding-alt-server-release']],
        ];
    }

    /**
     * @return list<array{key: string, title: string, hint: string, field: string}>
     */
    public static function featureToggles(): array
    {
        return [
            [
                'key' => 'systemd_mode',
                'title' => 'Режим systemd (PID 1)',
                'hint' => 'Нужен для systemctl, служб, Alterator. В тег Docker добавьте -systemd — lab-daemon поднимет контейнер с особыми флагами.',
                'field' => 'features[systemd_mode]',
            ],
            [
                'key' => 'sshd',
                'title' => 'SSH-сервер',
                'hint' => 'Установит openssh-server. Полезно, если практика предполагает подключение по SSH, а не только веб-терминал.',
                'field' => 'features[sshd]',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function startupPresetCategories(): array
    {
        return [
            'base' => 'Базовая подготовка',
            'files' => 'Файлы и задания',
            'packages' => 'Пакеты и репозитории',
            'break' => 'Учебные поломки',
            'network' => 'Сеть',
            'services' => 'Службы',
        ];
    }

    /**
     * @return list<array{id: string, category: string, title: string, description: string, body: string}>
     */
    public static function startupPresets(): array
    {
        $presets = [
            [
                'id' => 'empty',
                'category' => 'base',
                'title' => 'Пустой блок',
                'description' => 'Только комментарий — ничего не меняет.',
                'body' => "# Подготовка: без изменений\n",
            ],
            [
                'id' => 'student-home',
                'category' => 'files',
                'title' => 'Каталог ~/lab',
                'description' => 'Рабочая папка student для практики.',
                'body' => <<<'SH'
u="${LAB_USER:-student}"
home="$(getent passwd "$u" | cut -d: -f6 || echo "/home/$u")"
install -d -o "$u" -g "$u" "$home/lab"
echo "OK: $home/lab"
SH,
            ],
            [
                'id' => 'answer-files',
                'category' => 'files',
                'title' => 'Файлы ответов 1–4',
                'description' => 'Пустые ~/1.txt … ~/4.txt как в модуле 1 Alt.',
                'body' => <<<'SH'
u="${LAB_USER:-student}"
home="$(getent passwd "$u" | cut -d: -f6 || echo "/home/$u")"
for n in 1 2 3 4; do
  f="$home/${n}.txt"
  [[ -f "$f" ]] || { touch "$f" 2>/dev/null || install -o "$u" -g "$u" /dev/null "$f"; chown "$u:$u" "$f" 2>/dev/null || true; }
done
SH,
            ],
            [
                'id' => 'seed-task-file',
                'category' => 'files',
                'title' => 'Файл с условием задания',
                'description' => 'Кладёт текст задания в ~/lab/TASK.txt.',
                'body' => <<<'SH'
u="${LAB_USER:-student}"
home="$(getent passwd "$u" | cut -d: -f6 || echo "/home/$u")"
install -d -o "$u" -g "$u" "$home/lab"
cat >"$home/lab/TASK.txt" <<'EOF'
Выполните задание по инструкции преподавателя.
Результат проверки — через check.sh в портале.
EOF
chown "$u:$u" "$home/lab/TASK.txt"
SH,
            ],
            [
                'id' => 'apt-update',
                'category' => 'packages',
                'title' => 'apt update',
                'description' => 'Обновить индекс пакетов при старте (runtime).',
                'body' => <<<'SH'
if command -v apt-get >/dev/null 2>&1; then
  apt-get -y update || true
fi
SH,
            ],
            [
                'id' => 'dnf-makecache',
                'category' => 'packages',
                'title' => 'dnf makecache',
                'description' => 'Для Alma/CentOS — прогрев кэша dnf.',
                'body' => <<<'SH'
if command -v dnf >/dev/null 2>&1; then
  dnf -y makecache || true
fi
SH,
            ],
            [
                'id' => 'break-apt-sources',
                'category' => 'break',
                'title' => 'Сломать sources.list',
                'description' => 'Закомментировать рабочие репозитории apt — практика «починить apt».',
                'body' => <<<'SH'
for f in /etc/apt/sources.list /etc/apt/sources.list.d/*.list; do
  [[ -f "$f" ]] || continue
  cp -a "$f" "${f}.lab-bak" 2>/dev/null || true
  sed -i 's/^[^#]/&# disabled for lab/' "$f" 2>/dev/null || true
done
echo "WARN: apt sources masked (backup *.lab-bak)"
SH,
            ],
            [
                'id' => 'break-empty-sources',
                'category' => 'break',
                'title' => 'Пустой sources.list',
                'description' => 'Очистить sources.list — apt update падает.',
                'body' => <<<'SH'
if [[ -f /etc/apt/sources.list ]]; then
  cp -a /etc/apt/sources.list /etc/apt/sources.list.lab-bak
  echo "# broken for lab" > /etc/apt/sources.list
fi
SH,
            ],
            [
                'id' => 'break-resolv',
                'category' => 'break',
                'title' => 'Плохой DNS',
                'description' => 'Подменить nameserver на несуществующий — диагностика сети.',
                'body' => <<<'SH'
if [[ -f /etc/resolv.conf ]]; then
  cp -a /etc/resolv.conf /etc/resolv.conf.lab-bak
  printf '%s\n' 'nameserver 127.0.0.254' > /etc/resolv.conf
fi
SH,
            ],
            [
                'id' => 'break-hosts',
                'category' => 'break',
                'title' => 'Сломать /etc/hosts',
                'description' => 'Неверная запись localhost — типичная ошибка конфигурации.',
                'body' => <<<'SH'
cp -a /etc/hosts /etc/hosts.lab-bak 2>/dev/null || true
echo '127.0.1.1 broken-localhost lab-broken' >> /etc/hosts
SH,
            ],
            [
                'id' => 'break-perms-etc',
                'category' => 'break',
                'title' => 'Права на /etc/passwd',
                'description' => 'Слишком открытые права — задание по chmod/chown.',
                'body' => <<<'SH'
chmod 666 /etc/passwd 2>/dev/null || true
chmod 666 /etc/group 2>/dev/null || true
SH,
            ],
            [
                'id' => 'break-remove-sudoers',
                'category' => 'break',
                'title' => 'Убрать sudo у student',
                'description' => 'Переименовать sudoers.d для учебного пользователя.',
                'body' => <<<'SH'
for f in /etc/sudoers.d/*student* /etc/sudoers.d/*lab*; do
  [[ -f "$f" ]] && mv "$f" "${f}.disabled" 2>/dev/null || true
done
SH,
            ],
            [
                'id' => 'break-stop-sshd',
                'category' => 'services',
                'title' => 'Остановить sshd',
                'description' => 'Если SSH установлен — остановить службу для практики systemctl.',
                'body' => <<<'SH'
if command -v systemctl >/dev/null 2>&1; then
  systemctl stop sshd 2>/dev/null || systemctl stop ssh 2>/dev/null || true
  systemctl disable sshd 2>/dev/null || true
fi
SH,
            ],
            [
                'id' => 'break-failed-unit',
                'category' => 'services',
                'title' => 'Упавший юнит',
                'description' => 'Создать systemd-юнит, который падает при старте.',
                'body' => <<<'SH'
if command -v systemctl >/dev/null 2>&1; then
  cat >/etc/systemd/system/lab-fail.service <<'UNIT'
[Unit]
Description=Lab fail unit

[Service]
Type=oneshot
ExecStart=/bin/false

[Install]
WantedBy=multi-user.target
UNIT
  systemctl daemon-reload 2>/dev/null || true
  systemctl enable --now lab-fail.service 2>/dev/null || true
fi
SH,
            ],
            [
                'id' => 'break-firewall-flush',
                'category' => 'network',
                'title' => 'Блокирующий iptables',
                'description' => 'Политика DROP на INPUT — практика по firewall (осторожно в контейнере).',
                'body' => <<<'SH'
if command -v iptables >/dev/null 2>&1; then
  iptables -P INPUT DROP 2>/dev/null || true
  iptables -A INPUT -i lo -j ACCEPT 2>/dev/null || true
fi
SH,
            ],
            [
                'id' => 'break-missing-cmd',
                'category' => 'break',
                'title' => 'Переименовать vim',
                'description' => 'Спрятать vim — студент ищет/ставит редактор.',
                'body' => <<<'SH'
for bin in /usr/bin/vim /bin/vim; do
  [[ -x "$bin" ]] && mv "$bin" "${bin}.hidden" 2>/dev/null && break
done
SH,
            ],
            [
                'id' => 'break-full-disk-dummy',
                'category' => 'break',
                'title' => 'Заполнить /tmp',
                'description' => 'Большой файл в /tmp — диагностика «нет места» (лёгкий вариант).',
                'body' => <<<'SH'
dd if=/dev/zero of=/tmp/lab-fill.bin bs=1M count=64 2>/dev/null || \
  fallocate -l 64M /tmp/lab-fill.bin 2>/dev/null || true
SH,
            ],
        ];

        return array_map(static function (array $p): array {
            $body = (string) $p['body'];
            $p['script'] = "#!/usr/bin/env bash\nset -euo pipefail\n\n".$body;

            return $p;
        }, $presets);
    }

    /**
     * @return array<string, string>
     */
    public static function checkPresetCategories(): array
    {
        return [
            'packs' => 'Пакеты заданий',
            'helpers' => 'Вспомогательные функции',
            'scripts' => 'Готовые скрипты целиком',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function checkTaskTypes(): array
    {
        return [
            [
                'id' => 'file_exists',
                'label' => 'Файл существует',
                'description' => 'Проверяет, что по пути есть обычный файл. Подходит для ответов студента в ~/1.txt, ~/lab/result.txt и т.п.',
                'param_label' => 'Путь к файлу',
                'param_placeholder' => '$STUDENT_HOME/1.txt',
                'has_extra' => false,
                'default_file' => '$STUDENT_HOME/{n}.txt',
                'default_pattern' => '',
                'default_hint' => 'Создайте файл {n}.txt с ответом к заданию {n}.',
                'examples' => [
                    ['title' => '~/1.txt', 'file' => '$STUDENT_HOME/1.txt', 'pattern' => '', 'hint' => 'Создайте файл 1.txt в домашнем каталоге student.'],
                    ['title' => 'answer.txt', 'file' => '$STUDENT_HOME/answer.txt', 'pattern' => '', 'hint' => 'Создайте answer.txt с итоговым ответом.'],
                    ['title' => 'lab/result.txt', 'file' => '$STUDENT_HOME/lab/result.txt', 'pattern' => '', 'hint' => 'Создайте каталог lab и файл result.txt.'],
                ],
            ],
            [
                'id' => 'file_readable',
                'label' => 'Файл читается',
                'description' => 'Файл должен существовать и быть читаемым (права, владелец).',
                'param_label' => 'Путь к файлу',
                'param_placeholder' => '$STUDENT_HOME/report.txt',
                'has_extra' => false,
                'default_file' => '$STUDENT_HOME/{n}.txt',
                'default_pattern' => '',
                'default_hint' => 'Создайте и сделайте читаемым файл {n}.txt.',
                'examples' => [
                    ['title' => 'Отчёт', 'file' => '$STUDENT_HOME/report.txt', 'pattern' => '', 'hint' => 'Создайте report.txt и выдайте права на чтение student.'],
                ],
            ],
            [
                'id' => 'file_contains',
                'label' => 'Подстрока в файле',
                'description' => 'В файле должен встретиться текст (grep -q). В «Доп.» — что искать; можно regex-символы как в grep.',
                'param_label' => 'Путь к файлу',
                'param_placeholder' => '$STUDENT_HOME/1.txt',
                'param2_label' => 'Подстрока (grep)',
                'param2_placeholder' => 'ALT',
                'has_extra' => true,
                'default_file' => '$STUDENT_HOME/{n}.txt',
                'default_pattern' => 'ALT',
                'default_hint' => 'Запишите в {n}.txt требуемые данные (см. маркер в подсказке преподавателя).',
                'examples' => [
                    ['title' => 'Маркер ALT в 1.txt', 'file' => '$STUDENT_HOME/1.txt', 'pattern' => 'ALT', 'hint' => 'В 1.txt укажите дистрибутив (должно встретиться ALT).'],
                    ['title' => 'target в 3.txt', 'file' => '$STUDENT_HOME/3.txt', 'pattern' => 'multi-user\\.target', 'hint' => 'Добавьте в 3.txt имя systemd target.'],
                    ['title' => 'hostname', 'file' => '/etc/hostname', 'pattern' => 'lab-', 'hint' => 'Задайте hostname с префиксом lab-.'],
                ],
            ],
            [
                'id' => 'file_regex',
                'label' => 'Regex в файле',
                'description' => 'Расширенная проверка: grep -E по шаблону в «Доп.» (регулярное выражение).',
                'param_label' => 'Путь к файлу',
                'param_placeholder' => '$STUDENT_HOME/2.txt',
                'param2_label' => 'Шаблон (grep -E)',
                'param2_placeholder' => 'alt-server|alt-workstation',
                'has_extra' => true,
                'default_file' => '$STUDENT_HOME/{n}.txt',
                'default_pattern' => 'x86_64|aarch64',
                'default_hint' => 'Содержимое файла {n}.txt должно соответствовать шаблону.',
                'examples' => [
                    ['title' => 'Класс продукта', 'file' => '$STUDENT_HOME/2.txt', 'pattern' => 'alt-server|alt-workstation|altsp', 'hint' => 'Укажите класс продукта Alt Linux.'],
                ],
            ],
            [
                'id' => 'command',
                'label' => 'Команда успешна',
                'description' => 'Команда в параметре выполняется в bash; код выхода должен быть 0. Примеры: id, test -f …, systemctl is-active sshd.',
                'param_label' => 'Команда (bash)',
                'param_placeholder' => 'id',
                'has_extra' => false,
                'default_file' => 'id',
                'default_pattern' => '',
                'default_hint' => 'Выполните команду от пользователя student.',
                'examples' => [
                    ['title' => 'id', 'file' => 'id', 'pattern' => '', 'hint' => 'Выполните id и убедитесь, что uid student.'],
                    ['title' => 'Файл есть', 'file' => 'test -f $STUDENT_HOME/answer.txt', 'pattern' => '', 'hint' => 'Создайте answer.txt.'],
                    ['title' => 'systemctl', 'file' => 'systemctl is-active --quiet sshd', 'pattern' => '', 'hint' => 'Запустите службу sshd.'],
                ],
            ],
            [
                'id' => 'service_active',
                'label' => 'Служба systemd',
                'description' => 'Проверка через systemctl: выберите unit (sshd, nginx…) и нужное состояние — запущена, в автозагрузке или остановлена.',
                'param_label' => 'Служба (unit)',
                'param_placeholder' => 'sshd',
                'param_widget' => 'service',
                'param2_label' => 'Состояние',
                'param2_placeholder' => 'active',
                'param2_widget' => 'service_state',
                'has_extra' => true,
                'default_file' => 'sshd',
                'default_pattern' => 'active',
                'default_hint' => 'Запустите службу: systemctl enable --now sshd.',
                'examples' => [
                    ['title' => 'sshd запущен', 'file' => 'sshd', 'pattern' => 'active', 'hint' => 'Служба sshd должна быть active.'],
                    ['title' => 'sshd в автозагрузке', 'file' => 'sshd', 'pattern' => 'enabled', 'hint' => 'Включите sshd: systemctl enable sshd.'],
                    ['title' => 'nginx', 'file' => 'nginx', 'pattern' => 'active', 'hint' => 'Запустите nginx.'],
                ],
            ],
            [
                'id' => 'package_installed',
                'label' => 'Пакет установлен',
                'description' => 'Пакет установлен (rpm -q или dpkg -l). В параметре — имя или префикс пакета.',
                'param_label' => 'Имя пакета',
                'param_placeholder' => 'openssh-server',
                'has_extra' => false,
                'default_file' => 'openssh-server',
                'default_pattern' => '',
                'default_hint' => 'Установите пакет через apt-get/dnf.',
                'examples' => [
                    ['title' => 'openssh', 'file' => 'openssh-server', 'pattern' => '', 'hint' => 'Установите openssh-server.'],
                    ['title' => 'sudo', 'file' => 'sudo', 'pattern' => '', 'hint' => 'Установите sudo.'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{title: string, tasks: list<array<string, mixed>>}>
     */
    /**
     * @return list<string>
     */
    public static function checkCommonServices(): array
    {
        return [
            'sshd', 'sshd.service', 'nginx', 'httpd', 'docker', 'containerd',
            'auditd', 'firewalld', 'NetworkManager', 'postgresql', 'mariadb', 'crond',
        ];
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    public static function checkServiceStates(): array
    {
        return [
            ['id' => 'active', 'label' => 'Запущена'],
            ['id' => 'enabled', 'label' => 'Автозагрузка'],
            ['id' => 'inactive', 'label' => 'Остановлена'],
        ];
    }

    public static function checkExampleGrids(): array
    {
        return [
            [
                'title' => '4 файла ~/N.txt (модуль 1)',
                'tasks' => self::numberedFileTasks('file_exists', 4, 25, 'Создайте файл {n}.txt с ответом.'),
            ],
            [
                'title' => '4 файла с grep (Alt)',
                'tasks' => [
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/1.txt', 'pattern' => 'ALT', 'hint' => 'В 1.txt — данные о дистрибутиве (ALT).'],
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/2.txt', 'pattern' => 'alt-server|alt-workstation', 'hint' => 'В 2.txt — класс продукта.'],
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/3.txt', 'pattern' => 'multi-user\\.target', 'hint' => 'В 3.txt — target systemd.'],
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/4.txt', 'pattern' => 'x86_64|aarch64', 'hint' => 'В 4.txt — архитектура.'],
                ],
            ],
            [
                'title' => 'sshd: пакет + служба',
                'tasks' => [
                    ['points' => 50, 'type' => 'package_installed', 'file' => 'openssh-server', 'pattern' => '', 'hint' => 'Установите openssh-server.'],
                    ['points' => 50, 'type' => 'service_active', 'file' => 'sshd', 'pattern' => '', 'hint' => 'Запустите sshd.'],
                ],
            ],
        ];
    }

    /**
     * type: pack|helper|full — pack добавляет строки в конструктор заданий.
     *
     * @return list<array<string, mixed>>
     */
    public static function checkPresets(): array
    {
        $helpersBody = <<<'SH'
hint() { echo "HINT: $*"; }
ok() { echo "OK: $*"; }
fail_vis() { echo "FAIL: $*"; }
SH;

        return [
            [
                'id' => 'pack-4-files-exist',
                'category' => 'packs',
                'type' => 'pack',
                'title' => '4 файла — только наличие',
                'description' => 'Проверка ~/1.txt … ~/4.txt по 25 баллов.',
                'tasks' => self::numberedFileTasks('file_exists', 4, 25, ''),
            ],
            [
                'id' => 'pack-4-files-alt',
                'category' => 'packs',
                'type' => 'pack',
                'title' => '4 файла — как модуль 1 Alt',
                'description' => 'Наличие + grep-маркеры в каждом файле.',
                'tasks' => [
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/1.txt', 'pattern' => 'ALT', 'hint' => 'Запишите в 1.txt данные о дистрибутиве (маркер ALT).'],
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/2.txt', 'pattern' => 'alt-server|alt-workstation|alt-education|altsp', 'hint' => 'Укажите класс продукта в 2.txt.'],
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/3.txt', 'pattern' => 'multi-user\\.target|graphical\\.target', 'hint' => 'Добавьте target в 3.txt.'],
                    ['points' => 25, 'type' => 'file_contains', 'file' => '$STUDENT_HOME/4.txt', 'pattern' => 'x86_64|aarch64|e2k|ppc64', 'hint' => 'Укажите архитектууру в 4.txt.'],
                ],
            ],
            [
                'id' => 'pack-2-files',
                'category' => 'packs',
                'type' => 'pack',
                'title' => '2 файла ответа',
                'description' => 'answer.txt и lab/result.txt.',
                'tasks' => [
                    ['points' => 50, 'type' => 'file_exists', 'file' => '$STUDENT_HOME/answer.txt', 'pattern' => '', 'hint' => 'Создайте answer.txt в домашнем каталоге.'],
                    ['points' => 50, 'type' => 'file_exists', 'file' => '$STUDENT_HOME/lab/result.txt', 'pattern' => '', 'hint' => 'Создайте lab/result.txt.'],
                ],
            ],
            [
                'id' => 'pack-1-command',
                'category' => 'packs',
                'type' => 'pack',
                'title' => 'Одна команда',
                'description' => '100 баллов за успешный id или свою команду.',
                'tasks' => [
                    ['points' => 100, 'type' => 'command', 'file' => '', 'pattern' => 'id', 'hint' => 'Выполните команду id от student.'],
                ],
            ],
            [
                'id' => 'pack-service-sshd',
                'category' => 'packs',
                'type' => 'pack',
                'title' => 'Служба sshd',
                'description' => '50+50: пакет и active.',
                'tasks' => [
                    ['points' => 50, 'type' => 'package_installed', 'file' => '', 'pattern' => 'openssh', 'hint' => 'Установите openssh-server.'],
                    ['points' => 50, 'type' => 'service_active', 'file' => '', 'pattern' => 'sshd', 'hint' => 'Запустите sshd: systemctl start sshd.'],
                ],
            ],
            [
                'id' => 'helper-io',
                'category' => 'helpers',
                'type' => 'helper',
                'title' => 'hint / ok / fail',
                'description' => 'Как в check.sh курса Alt — дружелюбный вывод.',
                'body' => $helpersBody,
            ],
            [
                'id' => 'script-minimal',
                'category' => 'scripts',
                'type' => 'full',
                'title' => 'Заглушка (0 баллов)',
                'description' => 'Минимальный скрипт для ручной доработки.',
                'script' => <<<'SH'
#!/bin/bash
set -uo pipefail
MAX=100
score=0
echo "===PRACTICE_RESULT_JSON==="
echo '{"score":0,"max":100}'
exit 1
SH,
            ],
            [
                'id' => 'script-single-file',
                'category' => 'scripts',
                'type' => 'full',
                'title' => 'Один файл (100 баллов)',
                'description' => 'Классическая проверка одного answer.txt.',
                'script' => <<<'SH'
#!/bin/bash
set -uo pipefail
STUDENT_HOME="${STUDENT_HOME:-/home/student}"
FILE="${CHECK_FILE:-$STUDENT_HOME/answer.txt}"
MAX=100
score=0
if [[ -f "$FILE" ]]; then
  score=$MAX
  echo "OK: найден $FILE"
else
  echo "FAIL: нет $FILE"
  echo "HINT: создайте файл с ответом"
fi
echo "===PRACTICE_RESULT_JSON==="
echo "{\"score\":$score,\"max\":$MAX}"
[[ $score -ge 50 ]]
SH,
            ],
        ];
    }

    /**
     * @return list<array{points: int, type: string, file: string, pattern: string, hint: string}>
     */
    private static function numberedFileTasks(string $type, int $count, int $pointsEach, string $pattern): array
    {
        $tasks = [];
        for ($n = 1; $n <= $count; $n++) {
            $tasks[] = [
                'points' => $pointsEach,
                'type' => $type,
                'file' => '$STUDENT_HOME/'.$n.'.txt',
                'pattern' => $pattern,
                'hint' => 'Создайте файл '.$n.'.txt с ответом к заданию '.$n.'.',
            ];
        }

        return $tasks;
    }

    /**
     * @return list<array{step: string, title: string, lead: string}>
     */
    public static function wizardSteps(): array
    {
        return [
            ['step' => 'template', 'title' => 'Шаблон', 'headline' => 'Выберите основу образа', 'lead' => 'Готовый сценарий курса или копия из каталога'],
            ['step' => 'basics', 'title' => 'Имя', 'headline' => 'Название и тег Docker', 'lead' => 'Как образ увидят в библиотеке и при сборке'],
            ['step' => 'os', 'title' => 'ОС', 'headline' => 'Базовая система', 'lead' => 'Дистрибутив и образ FROM'],
            ['step' => 'packages', 'title' => 'Пакеты', 'headline' => 'Что установить', 'lead' => 'Пакеты попадут в Dockerfile при сборке'],
            ['step' => 'features', 'title' => 'Среда', 'headline' => 'Окружение лаборатории', 'lead' => 'Systemd, SSH, пользователь student'],
            ['step' => 'startup', 'title' => 'Старт', 'headline' => 'Конфигуратор startup.sh', 'lead' => 'Несколько сценариев сразу — поломки, файлы, репозитории'],
            ['step' => 'check', 'title' => 'Проверка', 'headline' => 'Конфигуратор check.sh', 'lead' => 'Задания с баллами, пакеты шаблонов и готовые скрипты'],
            ['step' => 'review', 'title' => 'Сборка', 'headline' => 'Готово к сборке', 'lead' => 'Проверьте рецепт и соберите на стенде'],
        ];
    }

    public static function wizardHelp(): array
    {
        return [
            'intro' => 'Мастер собирает Dockerfile, startup.sh и check.sh за вас. Ручное редактирование доступно в полях ниже на каждом шаге.',
            'build' => 'После сохранения нажмите «Собрать на стенде» — lab-daemon выполнит docker build из каталога storage/app/practice-images/{id}/.',
            'template_repo' => 'Шаблоны lab-m* также лежат в репозитории: docker/lab-m*/ и examples/practice-checks/.',
        ];
    }
}
