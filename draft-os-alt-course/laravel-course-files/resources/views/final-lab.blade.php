@extends('layouts.course')

@section('title', 'Финальная лабораторная')

@section('content')
    @php
        $ps = $practiceSession ?? null;
        $hasLab = $ps && $ps->daemon_lab_id && ($ps->expires_at === null || $ps->expires_at->isFuture());
        $canAccept = $ps && $ps->last_check_score !== null;
        $attempts = (int) ($result->attempts ?? 0);
        $attemptsLeft = (int) ($attemptsLeft ?? max(0, 2 - $attempts));
        $bestScore = (int) ($result->best_score ?? 0);
    @endphp

    <div class="card">
        <h1 style="margin-top:0">Финальная лабораторная работа — практический экзамен</h1>
        <p class="muted">Формат: отдельный контейнер с базовой установкой ALT p10, без преднамеренных поломок. Время: <strong>90 минут</strong>. Порог: <strong>70/100</strong>.</p>
        @if ($result)
            <p class="muted">Попыток: <strong>{{ $result->attempts }}</strong>, лучший результат: <strong>{{ $result->best_score }}%</strong>
                @if ($result->passed)
                    <span class="badge">принято</span>
                @endif
            </p>
        @endif
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Перед запуском</h2>
        <ul class="muted" style="line-height:1.55">
            <li>Всего <strong>2 попытки</strong>.</li>
            <li>Вторая попытка идёт со штрафом <strong>−10 п.п.</strong> к результату проверки.</li>
            <li>Лимит времени каждой попытки: <strong>90 минут</strong>.</li>
            <li>Нужно: запустить контейнер, выполнить ТЗ, нажать <strong>Проверить результат</strong>, затем <strong>Зафиксировать попытку</strong>.</li>
        </ul>
        @if ($attemptsLeft <= 0)
            <p class="quiz-modal-warn">Попытки исчерпаны (2/2). Повторный запуск недоступен.</p>
        @endif
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Лабораторный стенд</h2>
        @if (! ($labEnabled ?? false))
            <p class="flash err">Автопрактика отключена в конфигурации (PRACTICE_LAB_ENABLED=false).</p>
        @elseif (! ($labConfigured ?? false))
            <p class="flash err">Lab-daemon не настроен. Проверьте PRACTICE_LAB_DAEMON_URL / PRACTICE_LAB_DAEMON_SECRET.</p>
        @elseif (! ($labImage ?? null))
            <p class="flash err">Для финальной лабы не задан образ PRACTICE_LAB_IMAGE_FINAL.</p>
        @elseif ($attemptsLeft > 0 && ! $hasLab)
            <form method="post" action="{{ route('final-lab.start') }}">
                @csrf
                <button type="submit" class="btn btn-primary">Запустить контейнер (90 минут)</button>
            </form>
        @endif

        @if ($hasLab)
            @if ($ps->terminal_url)
                <p style="margin-top:0.5rem">
                    <button type="button" class="btn btn-primary js-final-terminal-toggle" id="final-terminal-toggle" data-terminal-url="{{ $ps->terminal_url }}" aria-expanded="false" aria-controls="final-terminal-dock">
                        <span class="js-final-terminal-label-open">Открыть веб-терминал</span>
                        <span class="js-final-terminal-label-close" hidden>Скрыть терминал</span>
                    </button>
                </p>
            @endif
            <p class="muted small">Контейнер активен до {{ $ps->expires_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?? '—' }}.</p>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
                <form method="post" action="{{ route('final-lab.check') }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Проверить результат</button>
                </form>
                <form method="post" action="{{ route('final-lab.finish') }}" onsubmit="return confirm('Удалить контейнер финальной лабы? Несохранённая работа будет потеряна.');">
                    @csrf
                    <button type="submit" class="btn btn-ghost">Завершить работу со стендом</button>
                </form>
            </div>
        @endif

        @if ($ps && $ps->last_check_log)
            <div class="practice-score-banner" style="margin-top:1rem;padding:0.75rem 1rem;border-radius:8px;background:var(--card-border, #e8e8e8);border:1px solid #ccc">
                <strong>Результат проверки:</strong>
                {{ (int) ($ps->last_check_score ?? 0) }} / {{ (int) ($ps->last_check_max_score ?? 100) }}.
                @if ($attempts >= 1)
                    <span class="muted">Следующая фиксация будет со штрафом −10 п.п.</span>
                @endif
            </div>
            @if ($canAccept && $attemptsLeft > 0)
                <form method="post" action="{{ route('final-lab.accept') }}" style="margin-top:0.75rem" onsubmit="return confirm('Зафиксировать попытку? После фиксации будет списана одна из двух попыток.');">
                    @csrf
                    <button type="submit" class="btn btn-primary">Зафиксировать попытку</button>
                </form>
            @endif
            <div class="practice-check-log" style="margin-top:0.75rem">
                <div class="muted" style="font-size:0.8rem;margin-bottom:0.35rem">Журнал последней проверки</div>
                <pre class="check-log-pre">{{ $ps->last_check_log }}</pre>
            </div>
        @endif
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Легенда</h2>
        <p>Тебе передан новый сервер на ОС ALT Linux. Система только что установлена. Твоя задача — привести её к корпоративному стандарту безопасности перед вводом в эксплуатацию.</p>

        <h3>Задание 1. Инвентаризация системы</h3>
        <p class="muted">Тебе передали новый сервер. Прежде чем приступать к настройке — нужно разобраться что за система перед тобой. Это стандартная процедура при приёмке любого сервера: зафиксировать базовые сведения о системе чтобы понимать с чем работаешь и иметь отправную точку для дальнейшей настройки.</p>
        <p class="muted">Собери следующие сведения и запиши их в файл <code>/root/exam-report.txt</code>:</p>
        <ul class="muted" style="line-height:1.6">
            <li>Название и версия ОС</li>
            <li>Версия ядра и архитектура процессора</li>
            <li>Режим загрузки по умолчанию — серверный или графический</li>
            <li>Подключённые репозитории</li>
        </ul>

        <h3>Задание 2. Административный веб-интерфейс</h3>
        <p class="muted">Тебе передали сервер на ОС Альт. Предыдущий администратор установил систему и даже поставил нужные пакеты, но до настройки не дошёл — веб-интерфейс управления сервером недоступен. Служба эксплуатации требует чтобы администраторы могли управлять сервером удалённо через браузер без подключения по SSH.</p>
        <p class="muted">Обеспечь работу веб-интерфейса ЦУС и добавь его в автозагрузку — интерфейс должен подниматься автоматически при каждом старте сервера.</p>
        <p class="muted">После настройки веб-интерфейс должен быть доступен по адресу <code>https://&lt;IP-сервера&gt;:8080</code>.</p>

        <h3>Задание 3. Политика паролей (20)</h3>
        <p class="muted">Компания проводит аудит безопасности. Аудитор выявил два нарушения корпоративного стандарта на сервере:</p>
        <ul class="muted" style="line-height:1.6">
            <li>Система принимает пароли, состоящие только из одного класса символов — например только цифры или только строчные буквы. Это прямое нарушение парольной политики.</li>
            <li>Ограничения на сложность пароля не распространяются на суперпользователя — root может установить любой пароль в обход политики.</li>
        </ul>
        <p class="muted">Устрани оба нарушения. После исправления система не должна принимать пароли из одного класса символов ни от одного пользователя, включая root.</p>
        <p class="muted"><strong>Справочная информация:</strong> политика паролей в Альт Линукс настраивается в файле <code>/etc/passwdqc.conf</code>. Параметр <code>min</code> задаёт минимальную длину пароля в зависимости от количества классов символов — пять значений через запятую. Параметр <code>enforce</code> определяет, на кого распространяется политика.</p>
        <pre class="check-log-pre">cat /etc/passwdqc.conf</pre>

        <h3>Задание 4. Контроль целостности (20)</h3>
        <p class="muted">Служба безопасности сообщила о подозрительной активности на сервере. Есть основания полагать, что один или несколько системных файлов были модифицированы. Проверь целостность установленных пакетов, найди изменённые файлы, определи, каким пакетам они принадлежат, и восстанови исходное состояние. После восстановления повторная проверка не должна выявлять расхождений с эталоном.</p>
        <p class="muted"><strong>Справочная информация:</strong> RPM хранит эталонные метаданные всех установленных файлов. Команда проверки сравнивает текущее состояние с эталоном и выводит только изменённые файлы. Каждая строка содержит флаги и путь:</p>
        <pre class="check-log-pre">S.5....T.  /usr/bin/somefile   ← изменился хэш и размер
.M.......  /bin/otherfile      ← изменились права доступа</pre>
        <p class="muted">Чтобы узнать, какому пакету принадлежит файл:</p>
        <pre class="check-log-pre">rpm -qf /путь/к/файлу</pre>
        <p class="muted">Для восстановления файлов пакета:</p>
        <pre class="check-log-pre">apt-get install --reinstall &lt;имя_пакета&gt;</pre>
        <div class="muted" style="margin:0.35rem 0 0.8rem;padding:0.65rem 0.75rem;border:1px solid #d7e3df;border-radius:8px;background:#f8fbfa">
            <strong>Подсказка:</strong> имеет смысл начать с <code>rpm -Va</code> (или выборочно <code>rpm -V имя_пакета</code> для подозрительных пакетов), расшифровать флаги в выводе, затем для каждого изменённого пути выяснить пакет через <code>rpm -qf</code> и восстановить файлы переустановкой пакета.
        </div>

        <h3>Задание 5. Делегирование прав через Polkit (12)</h3>
        <p class="muted">В компании есть группа сетевых администраторов. По корпоративному регламенту они должны иметь возможность управлять системными службами через systemd не зная пароля root.</p>
        <p class="muted"><strong>Условия:</strong></p>
        <ul class="muted" style="line-height:1.6">
            <li>Группа называется <code>netops</code></li>
            <li>Пользователь <code>student</code> должен входить в эту группу</li>
            <li>Члены группы <code>netops</code> должны иметь право на действие <code>org.freedesktop.systemd1.manage-units</code> без запроса пароля</li>
            <li>Правило должно быть оформлено через механизм Polkit и сохраняться после перезапуска службы</li>
        </ul>
        <p class="muted"><strong>Справочная информация:</strong></p>
        <p class="muted">Правила Polkit хранятся в <code>/etc/polkit-1/rules.d/</code> в виде файлов с расширением <code>.rules</code>. Файлы применяются в алфавитном порядке по имени.</p>
        <p class="muted">Каждое правило — это JavaScript-функция вида:</p>
        <pre class="check-log-pre">polkit.addRule(function(action, subject) {
    if (/* условие на действие */ &&
        /* условие на пользователя */) {
        return polkit.Result.YES;  // разрешить
    }
    // если условия не совпали — передать следующему правилу
});</pre>
        <p class="muted">Что можно проверять в условиях:</p>
        <ul class="muted" style="line-height:1.6">
            <li><code>action.id</code> — идентификатор действия (строка)</li>
            <li><code>subject.isInGroup("название")</code> — входит ли пользователь в группу</li>
            <li><code>subject.user</code> — имя пользователя</li>
            <li><code>subject.local</code> — локальная сессия или нет</li>
        </ul>
        <p class="muted">После создания файла правил необходимо перезапустить службу Polkit чтобы изменения вступили в силу.</p>
        <p class="muted">Проверить что правило загружено можно командой:</p>
        <pre class="check-log-pre">journalctl -u polkit --since "1 minute ago"</pre>

        <h3>Задание 6. Ограничение доступа к sudo</h3>
        <p class="muted">Политика безопасности компании требует чтобы выполнение команд с правами суперпользователя через <code>sudo</code> было доступно только системным администраторам входящим в специальную группу. Рядовые сотрудники не должны иметь возможности использовать <code>sudo</code> даже если они указаны в <code>/etc/sudoers</code>.</p>
        <p class="muted">Текущее состояние: механизм ограничения sudo по группе отключён — любой пользователь у которого есть запись в sudoers может выполнять команды от root.</p>
        <p class="muted">Включи механизм при котором <code>sudo</code> доступен только членам группы <code>wheel</code>. Используй для этого инструмент управления доступом к системным утилитам уникальный для Альт Линукс.</p>

        <h3>Как оценивается</h3>
        <p class="muted">Автопроверка выполняется скриптом <code>/opt/lab-check/check.sh</code>. Итог в формате <code>SCORE:x:100</code>, зачёт от <strong>70</strong>.</p>
        <p class="muted">Скрипт проверяет статусы сервисов, параметры конфигов, эталонную целостность по <code>rpm -V</code> для двух пакетов (какие именно — выявляются в ходе задания 4), правило Polkit и режим <code>control sudowheel</code>, а также содержимое <code>exam-report.txt</code> для задания 1.</p>
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Технические требования контейнера</h2>
        <ul class="muted" style="line-height:1.6">
            <li>База: ALT p10, <code>systemd</code> как PID1.</li>
            <li>Запуск контейнера без обязательного <code>--privileged</code>.</li>
            <li>Предустановлено: <code>systemd</code>, <code>dbus</code>, <code>control</code>, <code>libnss-role</code>, <code>polkit</code>, <code>pam_passwdqc</code> и базовые утилиты.</li>
            <li>Пользователь <code>student</code>: <code>NOPASSWD sudo</code>; тестовая учётка <code>testuser</code> для проверки passwdqc.</li>
            <li>Для задания 4 в образе намеренно нарушена целостность отдельных файлов у части установленных пакетов; какие именно — нужно найти и исправить самостоятельно.</li>
        </ul>
    </div>

    @if ($hasLab && $ps->terminal_url)
        <style>
            .terminal-dock-backdrop { position: fixed; inset: 0; background: rgba(26,35,50,0.38); z-index: 10040; opacity: 0; pointer-events: none; transition: opacity .2s ease; }
            .terminal-dock-backdrop.is-visible { opacity: 1; pointer-events: auto; }
            .terminal-dock-panel { position: fixed; right: 0; top: 0; bottom: 0; width: min(50vw, 880px); min-width: 280px; background:#0b1220; z-index:10050; display:flex; flex-direction:column; transform: translateX(100%); transition: transform .25s ease; }
            .terminal-dock-panel .terminal-dock-toolbar { display:flex; justify-content:space-between; align-items:center; padding:.5rem .6rem; border-bottom:1px solid rgba(255,255,255,.12); }
            .terminal-dock-panel .terminal-dock-title { color:#e2e8f0; font-weight:700; }
            .terminal-dock-panel .terminal-dock-toolbar-actions { display:flex; gap:.35rem; }
            .terminal-dock-panel .terminal-dock-link { font-size:.82rem; padding:.22rem .48rem; }
            .terminal-dock-panel .terminal-dock-frame { flex:1 1 auto; width:100%; border:0; min-height:0; background:#0b1220; }
            @media (max-width: 768px) { .terminal-dock-panel { width: 100vw; min-width: 0; } }
        </style>
        <div class="terminal-dock-backdrop" id="final-terminal-backdrop" aria-hidden="true"></div>
        <aside class="terminal-dock-panel" id="final-terminal-dock" role="dialog" aria-label="Веб-терминал финальной лабораторной" aria-hidden="true" data-terminal-url="{{ $ps->terminal_url }}">
            <div class="terminal-dock-toolbar">
                <span class="terminal-dock-title">Терминал финальной лабы</span>
                <div class="terminal-dock-toolbar-actions">
                    <a class="btn btn-ghost terminal-dock-link" href="{{ $ps->terminal_url }}" target="_blank" rel="noopener">Новая вкладка</a>
                    <button type="button" class="btn btn-ghost terminal-dock-link js-final-terminal-close" aria-label="Скрыть терминал">Закрыть</button>
                </div>
            </div>
            <iframe class="terminal-dock-frame" id="final-terminal-iframe" title="Веб-терминал финальной лабы" loading="lazy" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-modals" allow="clipboard-read; clipboard-write"></iframe>
        </aside>
        <script>
            (function () {
                var dock = document.getElementById('final-terminal-dock');
                var backdrop = document.getElementById('final-terminal-backdrop');
                var iframe = document.getElementById('final-terminal-iframe');
                var btn = document.getElementById('final-terminal-toggle');
                if (!dock || !backdrop || !iframe || !btn) return;
                var url = (dock.getAttribute('data-terminal-url') || '').trim();
                var open = false;
                var openLabel = document.querySelector('.js-final-terminal-label-open');
                var closeLabel = document.querySelector('.js-final-terminal-label-close');
                function apply(v) {
                    open = v;
                    dock.classList.toggle('is-visible', v);
                    backdrop.classList.toggle('is-visible', v);
                    dock.setAttribute('aria-hidden', v ? 'false' : 'true');
                    backdrop.setAttribute('aria-hidden', v ? 'false' : 'true');
                    btn.setAttribute('aria-expanded', v ? 'true' : 'false');
                    dock.style.transform = v ? 'translateX(0)' : 'translateX(100%)';
                    if (openLabel) openLabel.hidden = v;
                    if (closeLabel) closeLabel.hidden = !v;
                    if (v && !iframe.getAttribute('src') && url) iframe.setAttribute('src', url);
                    if (!v) iframe.removeAttribute('src');
                }
                btn.addEventListener('click', function () { apply(!open); });
                document.querySelectorAll('.js-final-terminal-close').forEach(function (el) { el.addEventListener('click', function () { apply(false); }); });
                backdrop.addEventListener('click', function () { apply(false); });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) apply(false); });
                document.querySelectorAll('form').forEach(function (f) {
                    f.addEventListener('submit', function () { apply(false); iframe.removeAttribute('src'); });
                });
                apply(false);
            })();
        </script>
    @endif
@endsection
