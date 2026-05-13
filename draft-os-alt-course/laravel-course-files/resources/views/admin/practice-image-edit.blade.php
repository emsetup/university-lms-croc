@extends('layouts.course')

@section('title', ($isNew ? 'Создать образ практики' : 'Образ: '.$row->title))

@section('content')
    @include('partials.admin-instructor-nav', ['active' => 'practice'])

    <div class="card">
        <p class="muted"><a href="{{ route('admin.practice.images.index') }}">← К списку образов</a></p>

        <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-start;justify-content:space-between">
            <div>
                <h1 style="margin:0">{{ $isNew ? 'Создать Docker-образ практики' : 'Docker-образ практики' }}</h1>
                <p class="muted" style="margin:0.25rem 0 0">Dockerfile + <code>check.sh</code> хранятся в рецепте на стенде; сборка выполняется через lab-daemon.</p>
            </div>
            @if (! $isNew)
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                    <form method="post" action="{{ route('admin.practice.images.build', ['id' => $row->id]) }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-primary">Собрать</button>
                    </form>
                    <form method="post" action="{{ route('admin.practice.images.stats.refresh') }}" style="margin:0">
                        @csrf
                        <input type="hidden" name="tag" value="{{ $row->docker_tag }}">
                        <input type="hidden" name="back" value="{{ request()->getRequestUri() }}">
                        <button type="submit" class="btn btn-ghost">Проверить образ</button>
                    </form>
                    <form method="post" action="{{ route('admin.practice.images.export', ['id' => $row->id]) }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Экспортировать (tar)</button>
                    </form>
                </div>
            @endif
        </div>

        @if (! $isNew && $row->export_path)
            <div class="muted small" style="margin-top:0.75rem">Последний экспорт: <code>{{ $row->export_path }}</code></div>
        @endif

        <form method="post" action="{{ $isNew ? route('admin.practice.images.store') : route('admin.practice.images.update', ['id' => $row->id]) }}" style="margin-top:1rem">
            @csrf

            <div style="margin:0 0 0.75rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                <button type="button" class="btn btn-ghost js-pi-tab" data-tab="base">1) ОС и база</button>
                <button type="button" class="btn btn-ghost js-pi-tab" data-tab="packages">2) Пакеты</button>
                <button type="button" class="btn btn-ghost js-pi-tab" data-tab="startup">3) Startup</button>
                <button type="button" class="btn btn-ghost js-pi-tab" data-tab="check">4) Check</button>
                <span class="muted small">Конструктор (guided). Dockerfile генерируется автоматически.</span>
            </div>

            <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:0.75rem;align-items:start">
                <div style="grid-column:span 6">
                    <label class="muted small">Название</label>
                    <input class="input" name="title" value="{{ old('title', $row->title) }}" required>
                </div>
                <div style="grid-column:span 3">
                    <label class="muted small">Slug (уник.)</label>
                    <input class="input" name="slug" value="{{ old('slug', $row->slug) }}" placeholder="auto" @if($isNew) @endif>
                </div>
                <div style="grid-column:span 3">
                    <label class="muted small">Docker tag</label>
                    <input class="input" name="docker_tag" value="{{ old('docker_tag', $row->docker_tag) }}" placeholder="repo/name:tag" required>
                </div>

                <div style="grid-column:span 4">
                    <label class="muted small">Шаблон</label>
                    <select class="input" name="base_template" required>
                        @foreach ($templates as $k => $label)
                            <option value="{{ $k }}" @selected(old('base_template', $row->base_template) === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($isNew)
                        <label class="muted small" style="display:block;margin-top:0.5rem">
                            <input type="hidden" name="init_from_template" value="0">
                            <input type="checkbox" name="init_from_template" value="1" checked>
                            Инициализировать рецептом из шаблона (Dockerfile + ассеты + check.sh)
                        </label>
                    @endif
                </div>
                <div style="grid-column:span 8">
                    @if (! $isNew)
                        <div class="muted small">Статус сборки:
                            @if ($row->last_build_status === 'ok')
                                <strong>OK</strong>
                            @elseif ($row->last_build_status === 'fail')
                                <strong>FAIL</strong>
                            @else
                                <span class="muted">—</span>
                            @endif
                            @if ($row->last_built_at)
                                <span class="muted">· {{ $row->last_built_at->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <section class="js-pi-panel" data-panel="base" style="margin-top:1rem">
                <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:0.75rem;align-items:start">
                    <div style="grid-column:span 4">
                        <label class="muted small">Базовая ОС</label>
                        <select class="input" name="base_os" required>
                            @foreach (['alt' => 'ALT', 'redos' => 'РЕДОС', 'astra' => 'Астра', 'alma' => 'AlmaLinux', 'centos' => 'CentOS'] as $k => $label)
                                <option value="{{ $k }}" @selected(old('base_os', $row->base_os ?? 'alt') === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="muted small" style="margin-top:0.35rem">Определяет менеджер пакетов и дефолтный base image.</div>
                    </div>
                    <div style="grid-column:span 8">
                        <label class="muted small">Base image (опционально)</label>
                        <input class="input" name="base_image_ref" value="{{ old('base_image_ref', $row->base_image_ref ?? '') }}" placeholder="например registry.altlinux.org/alt/alt:p10 или almalinux:9">
                        <div class="muted small" style="margin-top:0.35rem">Если не задано — возьмём дефолт для выбранной ОС.</div>
                    </div>
                </div>
            </section>

            <section class="js-pi-panel" data-panel="packages" style="margin-top:1rem;display:none">
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:end;justify-content:space-between;margin-bottom:0.5rem">
                    <div style="flex:1;min-width:260px">
                        <label class="muted small">Поиск пакетов</label>
                        <input class="input" id="pkg-q" placeholder="например vim, openssh, audit">
                    </div>
                    <button type="button" class="btn btn-ghost" id="pkg-search-btn">Найти</button>
                </div>
                <div id="pkg-results" class="muted small" style="margin:0 0 0.75rem;line-height:1.55"></div>
                <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:0.75rem;align-items:start">
                    <div style="grid-column:span 6">
                        <label class="muted small">Добавить пакеты (по одному в строке)</label>
                        <textarea class="input" id="pkg-add" name="package_add_text" rows="10" spellcheck="false">{{ old('package_add_text', is_array($row->package_add ?? null) ? implode("\n", $row->package_add) : '') }}</textarea>
                    </div>
                    <div style="grid-column:span 6">
                        <label class="muted small">Удалить пакеты (по одному в строке)</label>
                        <textarea class="input" id="pkg-rm" name="package_remove_text" rows="10" spellcheck="false">{{ old('package_remove_text', is_array($row->package_remove ?? null) ? implode("\n", $row->package_remove) : '') }}</textarea>
                    </div>
                </div>
                <div class="muted small" style="margin-top:0.35rem">Клик по пакету добавит его в список «Добавить».</div>
            </section>

            <section class="js-pi-panel" data-panel="startup" style="margin-top:1rem;display:none">
                <label class="muted small">startup.sh (подготовка практики)</label>
                <textarea class="input" name="startup_script_text" rows="14" spellcheck="false" style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace">{{ old('startup_script_text', $row->startup_script_text ?? '') }}</textarea>
            </section>

            <section class="js-pi-panel" data-panel="check" style="margin-top:1rem;display:none">
                <label class="muted small">check.sh (автопроверка)</label>
                <textarea class="input" name="check_script_text" rows="14" spellcheck="false" style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace">{{ old('check_script_text', $row->check_script_text) }}</textarea>
                <div class="muted small" style="margin-top:0.35rem">Важно: для баллов используйте маркер <code>===PRACTICE_RESULT_JSON===</code> и JSON <code>{\"score\":X,\"max\":Y}</code>.</div>
            </section>

            <section style="margin-top:1rem">
                @php($f = is_array($row->features ?? null) ? $row->features : [])
                <h2 style="margin:0.25rem 0 0.5rem;font-size:1.05rem">Фичи</h2>
                <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:0.75rem;align-items:start">
                    <div style="grid-column:span 3">
                        <label class="muted small" style="display:block;margin-bottom:0.25rem">systemd режим</label>
                        <label class="muted small"><input type="hidden" name="features[systemd_mode]" value="0"><input type="checkbox" name="features[systemd_mode]" value="1" @checked(!empty($f['systemd_mode']))> включить</label>
                        <div class="muted small" style="margin-top:0.25rem">Совет: tag должен содержать <code>-systemd</code>, иначе daemon может не дать нужные флаги.</div>
                    </div>
                    <div style="grid-column:span 3">
                        <label class="muted small" style="display:block;margin-bottom:0.25rem">SSHD</label>
                        <label class="muted small"><input type="hidden" name="features[sshd]" value="0"><input type="checkbox" name="features[sshd]" value="1" @checked(!empty($f['sshd']))> установить openssh-server</label>
                    </div>
                    <div style="grid-column:span 3">
                        <label class="muted small">Locale (LANG/LC_ALL)</label>
                        <input class="input" name="features[locale]" value="{{ old('features.locale', (string) ($f['locale'] ?? '')) }}" placeholder="например C.UTF-8 или ru_RU.UTF-8">
                    </div>
                    @php($cu = is_array($f['create_user'] ?? null) ? $f['create_user'] : [])
                    <div style="grid-column:span 3">
                        <label class="muted small" style="display:block;margin-bottom:0.25rem">Пользователь</label>
                        <label class="muted small"><input type="hidden" name="features[create_user][enabled]" value="0"><input type="checkbox" name="features[create_user][enabled]" value="1" @checked(!empty($cu['enabled']))> создать user</label>
                        <div style="margin-top:0.35rem">
                            <input class="input" name="features[create_user][name]" value="{{ old('features.create_user.name', (string) ($cu['name'] ?? 'student')) }}" placeholder="student">
                        </div>
                        <div style="margin-top:0.35rem">
                            <input class="input" name="features[create_user][password]" value="{{ old('features.create_user.password', (string) ($cu['password'] ?? 'labstudy')) }}" placeholder="пароль">
                        </div>
                        <label class="muted small" style="display:block;margin-top:0.35rem"><input type="hidden" name="features[create_user][sudo]" value="0"><input type="checkbox" name="features[create_user][sudo]" value="1" @checked(!isset($cu['sudo']) || !empty($cu['sudo']))> sudo NOPASSWD</label>
                    </div>
                </div>
            </section>

            <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between">
                <button type="submit" class="btn btn-primary">Сохранить</button>
                @if (! $isNew)
                    <form method="post" action="{{ route('admin.practice.images.destroy', ['id' => $row->id]) }}" style="margin:0" onsubmit="return confirm('Удалить образ? Рецепт на диске останется, но запись исчезнет.');">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Удалить</button>
                    </form>
                @endif
            </div>
        </form>

        @if (! $isNew && $row->last_build_log)
            <div style="margin-top:1rem">
                <div class="muted small" style="margin-bottom:0.35rem">Лог последней сборки</div>
                <pre class="check-log-pre" style="max-height:420px;overflow:auto">{{ $row->last_build_log }}</pre>
            </div>
        @endif
    </div>

    <script>
        (function () {
            function show(tab) {
                document.querySelectorAll('.js-pi-panel').forEach(function (el) {
                    el.style.display = (el.getAttribute('data-panel') === tab) ? '' : 'none';
                });
            }
            document.querySelectorAll('.js-pi-tab').forEach(function (btn) {
                btn.addEventListener('click', function () { show(btn.getAttribute('data-tab')); });
            });
            show('base');

            var btn = document.getElementById('pkg-search-btn');
            var q = document.getElementById('pkg-q');
            var out = document.getElementById('pkg-results');
            var add = document.getElementById('pkg-add');
            if (btn && q && out && add) {
                function appendPkg(name) {
                    var cur = (add.value || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
                    if (cur.indexOf(name) === -1) cur.push(name);
                    add.value = cur.join("\n") + "\n";
                }
                btn.addEventListener('click', function () {
                    var qq = (q.value || '').trim();
                    if (!qq) return;
                    out.textContent = 'Ищем...';
                    var osSel = document.querySelector('select[name=\"base_os\"]');
                    var baseInp = document.querySelector('input[name=\"base_image_ref\"]');
                    var os = osSel ? osSel.value : 'alt';
                    var base = baseInp ? (baseInp.value || '') : '';
                    var url = @json(route('admin.practice.images.pkg.search')) + '&os=' + encodeURIComponent(os) + '&q=' + encodeURIComponent(qq) + '&base_image=' + encodeURIComponent(base) + '&limit=20';
                    fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (x) {
                            if (!x.ok || !x.j || !x.j.ok) {
                                out.textContent = (x.j && x.j.error) ? x.j.error : 'Ошибка поиска';
                                return;
                            }
                            var lines = (x.j.data && x.j.data.lines) ? x.j.data.lines : [];
                            if (!lines.length) {
                                out.textContent = 'Ничего не найдено.';
                                return;
                            }
                            out.innerHTML = '';
                            lines.forEach(function (ln) {
                                var name = (ln.split(' - ')[0] || ln).trim();
                                if (!name || name.indexOf('Pulling') !== -1 || name.indexOf('Unable to find image') !== -1) return;
                                var a = document.createElement('button');
                                a.type = 'button';
                                a.className = 'btn btn-ghost';
                                a.style.padding = '0.25rem 0.55rem';
                                a.style.fontSize = '0.82rem';
                                a.textContent = '+ ' + name;
                                a.addEventListener('click', function () { appendPkg(name); });
                                out.appendChild(a);
                            });
                        })
                        .catch(function (e) { out.textContent = String(e || 'Ошибка'); });
                });
            }
        })();
    </script>
@endsection

