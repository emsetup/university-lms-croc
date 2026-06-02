{{-- Форма «Сертификат» (вкладка ?tab=sertifikat). Ожидает: $course, $courseStatus, $tp --}}
@php
    $certEnabledDefault = \Illuminate\Support\Facades\Schema::hasColumn('courses', 'certificate_enabled')
        ? ($course->certificate_enabled ?? true)
        : true;
    $certOn = (string) old('certificate_enabled', $certEnabledDefault ? '1' : '0') === '1';
    $tiers = old('certificate_tiers');
    if (! is_string($tiers) || trim($tiers) === '') {
        $raw = $course->certificate_tiers;
        if (is_array($raw) && $raw !== []) {
            $tiers = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $tiers = json_encode([
                ['key' => 'advanced', 'min_percent' => 85, 'label' => 'Продвинутый уровень'],
                ['key' => 'standard', 'min_percent' => 70, 'label' => 'Базовый уровень'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
@endphp

<form id="course-certificate-form" method="post" action="{{ route('admin.course.settings.save', $tp) }}" class="ap-course-settings-form">
    @csrf
    <input type="hidden" name="redirect_tab" value="sertifikat">
    <input type="hidden" name="title" value="{{ old('title', $course->title) }}">
    <input type="hidden" name="slug" value="{{ old('slug', $course->slug) }}">
    <input type="hidden" name="summary" value="{{ old('summary', $course->summary) }}">
    <input type="hidden" name="course_status" value="{{ old('course_status', $courseStatus) }}">
    <input type="hidden" name="certificate_tiers" id="certificate-tiers-json" value="{{ $tiers }}">

    <div class="ap-course-settings-grid">
        <div class="ap-settings-col">
            <section class="ap-settings-card" aria-labelledby="ap-settings-cert-main-h">
                <h2 id="ap-settings-cert-main-h" class="ap-settings-card__title">Сертификат</h2>
                <p class="ap-settings-sub ap-muted">Включите сертификат для курса и настройте уровни. Если сертификат выключен — страница сертификата будет недоступна.</p>

                <div class="ap-toggle-row">
                    <label class="ap-toggle">
                        <input type="hidden" name="certificate_enabled" value="0">
                        <input type="checkbox" name="certificate_enabled" value="1" class="ap-toggle__input" id="certificate-enabled" @if ($certOn) checked @endif>
                        <span class="ap-toggle__track" aria-hidden="true"></span>
                        <span class="ap-toggle__label" id="certificate-toggle-label">{{ $certOn ? 'Сертификат включён' : 'Сертификат выключен' }}</span>
                    </label>
                </div>

                <div id="certificate-details" class="ap-cert-details" @if (! $certOn) hidden @endif>
                    <label class="ap-settings-label" for="certificate-title">
                        Заголовок на странице
                        <span class="ap-info" title="Это заголовок итоговой страницы. Сам PNG сертификата остаётся «чистым»: только ФИО и уровень.">i</span>
                    </label>
                    <input
                        id="certificate-title"
                        class="ap-modal__input ap-settings-input"
                        type="text"
                        name="certificate_title"
                        maxlength="200"
                        value="{{ old('certificate_title', $course->certificate_title) }}"
                        placeholder="Сертификат по курсу «…»"
                    >
                    <p class="ap-settings-hint ap-muted">Показывается на итоговой странице, сам PNG остаётся «чистым» (только ФИО и уровень).</p>

                    <label class="ap-settings-label" for="certificate-body" style="margin-top:0.85rem">
                        Текст на бланке
                        <span class="ap-info" title="Можно настроить под предмет курса. Используйте плейсхолдер {course}, чтобы автоматически подставлялось название курса.">i</span>
                    </label>
                    <textarea
                        id="certificate-body"
                        class="ap-modal__input ap-settings-textarea"
                        name="certificate_body"
                        rows="3"
                        maxlength="500"
                        placeholder="успешно завершил(а) курс {course} и подтвердил(а) практические навыки."
                    >{{ old('certificate_body', $course->certificate_body) }}</textarea>
                    <p class="ap-settings-hint ap-muted">Например: «успешно завершил(а) курс {course} и подтвердил(а) навыки работы с Linux». Если не задано — используется текст по умолчанию.</p>
                </div>
            </section>
        </div>

        <div class="ap-settings-col ap-settings-col--stack">
            <section class="ap-settings-card" aria-labelledby="ap-settings-cert-tiers-h">
                <div class="ap-settings-row-between" style="align-items:flex-start">
                    <div>
                        <h2 id="ap-settings-cert-tiers-h" class="ap-settings-card__title" style="margin-bottom:0.25rem">Уровни</h2>
                        <p class="ap-settings-sub ap-muted" style="margin:0">
                            Уровень выбирается по проценту итогового результата по курсу
                            <span class="ap-info" title="Процент считается от максимума: баллы за модули + финальная лаборатория. Если результат ниже минимального уровня — сертификат не выдаётся.">i</span>
                        </p>
                    </div>
                    <div class="ap-cert-head-actions">
                        <button type="button" class="btn btn-ghost" id="cert-add-tier" @if (! $certOn) disabled @endif>Добавить уровень</button>
                    </div>
                </div>

                <div id="cert-tiers-list" class="ap-cert-tiers" @if (! $certOn) aria-disabled="true" @endif></div>
                <div class="ap-muted" style="margin-top:0.75rem;font-size:0.85rem">
                    Как это работает: если процент \(\ge\) «От, %» — выдаётся соответствующий уровень. Если процент ниже всех уровней — сертификат не выдаётся.
                </div>
            </section>
        </div>
    </div>

    <section class="ap-settings-card ap-cert-preview-panel" id="cert-preview-panel" @if (! $certOn) hidden @endif aria-labelledby="ap-cert-preview-panel-h">
        <h2 id="ap-cert-preview-panel-h" class="ap-settings-card__title">Предпросмотр сертификата</h2>
        <p class="ap-settings-sub ap-muted">Посмотрите, как будет выглядеть бланк при разных процентах и уровнях. ФИО и номер — демонстрационные.</p>

        <div class="ap-cert-preview" id="cert-preview">
            <div class="ap-cert-preview__toolbar">
                <div class="ap-cert-preview__row">
                    <label class="ap-settings-label" for="cert-preview-percent" style="margin:0">Процент результата</label>
                    <div class="ap-cert-preview__controls">
                        <input id="cert-preview-range" type="range" min="0" max="100" value="78" class="ap-cert-preview__range">
                        <input id="cert-preview-percent" type="number" min="0" max="100" value="78" class="ap-modal__input ap-settings-input ap-settings-input--num">
                        <span class="ap-settings-suffix">%</span>
                    </div>
                </div>
                <div id="cert-preview-chips" class="ap-cert-preview__chips" role="group" aria-label="Быстрый выбор уровня"></div>
            </div>
            <div id="cert-preview-result" class="ap-cert-preview__result"></div>

            @include('partials.certificate-design-css')
            <div class="ap-cert-paper-viewport" id="cert-paper-viewport">
                <div class="ap-cert-paper-viewport__empty" id="cert-paper-empty" hidden>
                    <strong>Сертификат не выдаётся</strong>
                    <p class="ap-muted" style="margin:0.35rem 0 0">При таком проценте ни один уровень не достигнут. Обучающийся не получает бланк.</p>
                </div>
                <div class="ap-cert-paper-viewport__scale" id="cert-paper-scale">
                    @include('partials.certificate-paper', [
                        'serial' => 'PREVIEW-0001',
                        'issueDate' => now()->format('d.m.Y'),
                        'recipientName' => 'Иванов Иван Иванович',
                        'nameExtraClass' => 'js-cert-preview-name',
                        'certTier' => ['key' => 'preview', 'label' => 'Базовый уровень'],
                        'courseTitle' => old('certificate_title', $course->certificate_title ?: $course->title),
                        'certificateBody' => old('certificate_body', $course->certificate_body),
                    ])
                </div>
            </div>
        </div>
    </section>
</form>

<div id="course-settings-save-bar" class="ap-settings-save-bar" hidden>
    <div class="ap-settings-save-bar__inner">
        <span class="ap-muted">Есть несохранённые изменения</span>
        <button type="submit" form="course-certificate-form" class="btn btn-primary">Сохранить настройки</button>
    </div>
</div>

<script>
    (function () {
        var form = document.getElementById('course-certificate-form');
        var bar = document.getElementById('course-settings-save-bar');
        var toggle = document.getElementById('certificate-enabled');
        var toggleLabel = document.getElementById('certificate-toggle-label');
        var details = document.getElementById('certificate-details');
        var tiersInput = document.getElementById('certificate-tiers-json');
        var list = document.getElementById('cert-tiers-list');
        var addBtn = document.getElementById('cert-add-tier');
        var previewPanel = document.getElementById('cert-preview-panel');
        var previewRange = document.getElementById('cert-preview-range');
        var previewPercent = document.getElementById('cert-preview-percent');
        var previewResult = document.getElementById('cert-preview-result');
        var previewChips = document.getElementById('cert-preview-chips');
        var paperViewport = document.getElementById('cert-paper-viewport');
        var paperScale = document.getElementById('cert-paper-scale');
        var paperEmpty = document.getElementById('cert-paper-empty');
        var titleInput = document.getElementById('certificate-title');
        var bodyInput = document.getElementById('certificate-body');
        if (!form || !bar || !tiersInput || !list) return;

        function safeParseJson(s) {
            try {
                var v = JSON.parse(s || '[]');
                return Array.isArray(v) ? v : [];
            } catch (e) {
                return [];
            }
        }

        function sanitizeKey(s) {
            return String(s || '').trim().toLowerCase().replace(/[^a-z0-9\-_]/g, '').slice(0, 40);
        }

        function clampInt(v, min, max) {
            var n = parseInt(v, 10);
            if (!isFinite(n)) n = min;
            n = Math.max(min, Math.min(max, n));
            return n;
        }

        var tiers = safeParseJson(tiersInput.value);
        if (!tiers.length) {
            tiers = [
                { key: 'advanced', min_percent: 85, label: 'Продвинутый уровень' },
                { key: 'standard', min_percent: 70, label: 'Базовый уровень' }
            ];
        }

        function syncInput() {
            tiersInput.value = JSON.stringify(tiers.map(function (t) {
                return {
                    key: sanitizeKey(t.key),
                    min_percent: clampInt(t.min_percent, 0, 100),
                    label: String(t.label || '').trim().slice(0, 120)
                };
            }), null, 0);
        }

        function mkRow(tier, idx) {
            var row = document.createElement('div');
            row.className = 'ap-cert-tier';
            row.setAttribute('data-idx', String(idx));

            var left = document.createElement('div');
            left.className = 'ap-cert-tier__grid';

            var head = document.createElement('div');
            head.className = 'ap-cert-tier__head';
            head.style.gridColumn = '1 / -1';
            var title = document.createElement('div');
            title.className = 'ap-cert-tier__title';
            title.textContent = 'Уровень ' + (idx + 1);
            var hint = document.createElement('div');
            hint.className = 'ap-cert-tier__hint ap-muted';
            hint.textContent = 'Выдаётся, если процент по курсу не ниже порога.';
            head.appendChild(title);
            head.appendChild(hint);

            var minWrap = document.createElement('div');
            var minLabel = document.createElement('label');
            minLabel.className = 'ap-settings-label';
            minLabel.textContent = 'От, %';
            var min = document.createElement('input');
            min.type = 'number';
            min.min = '0';
            min.max = '100';
            min.className = 'ap-modal__input ap-settings-input ap-settings-input--num';
            min.value = tier.min_percent;
            min.addEventListener('input', function () {
                tiers[idx].min_percent = clampInt(min.value, 0, 100);
                syncInput();
                renderPreview();
                refreshDirty();
            });
            minWrap.appendChild(minLabel);
            minWrap.appendChild(min);

            var keyWrap = document.createElement('div');
            var keyLabel = document.createElement('label');
            keyLabel.className = 'ap-settings-label';
            keyLabel.textContent = 'Код (опционально)';
            var key = document.createElement('input');
            key.type = 'text';
            key.maxLength = 40;
            key.className = 'ap-modal__input ap-settings-input';
            key.placeholder = 'например: expert';
            key.value = tier.key || '';
            key.addEventListener('input', function () {
                tiers[idx].key = sanitizeKey(key.value);
                syncInput();
                refreshDirty();
            });
            keyWrap.appendChild(keyLabel);
            keyWrap.appendChild(key);

            var labelWrap = document.createElement('div');
            labelWrap.style.gridColumn = '1 / -1';
            var lbl = document.createElement('label');
            lbl.className = 'ap-settings-label';
            lbl.textContent = 'Текст уровня на сертификате';
            var label = document.createElement('input');
            label.type = 'text';
            label.maxLength = 120;
            label.className = 'ap-modal__input ap-settings-input';
            label.placeholder = 'Например: ALT Linux Administrator';
            label.value = tier.label || '';
            label.addEventListener('input', function () {
                tiers[idx].label = String(label.value || '').slice(0, 120);
                syncInput();
                renderPreview();
                refreshDirty();
            });
            labelWrap.appendChild(lbl);
            labelWrap.appendChild(label);

            left.appendChild(head);
            left.appendChild(minWrap);
            left.appendChild(keyWrap);
            left.appendChild(labelWrap);

            var actions = document.createElement('div');
            actions.className = 'ap-cert-tier__actions';

            function mkBtn(text, title, onClick) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'btn btn-ghost';
                b.textContent = text;
                b.title = title || '';
                b.addEventListener('click', onClick);
                return b;
            }

            actions.appendChild(mkBtn('↑', 'Выше', function () {
                if (idx <= 0) return;
                var tmp = tiers[idx - 1];
                tiers[idx - 1] = tiers[idx];
                tiers[idx] = tmp;
                render();
                syncInput();
                refreshDirty();
            }));
            actions.appendChild(mkBtn('↓', 'Ниже', function () {
                if (idx >= tiers.length - 1) return;
                var tmp = tiers[idx + 1];
                tiers[idx + 1] = tiers[idx];
                tiers[idx] = tmp;
                render();
                syncInput();
                refreshDirty();
            }));
            actions.appendChild(mkBtn('Удалить', 'Удалить уровень', function () {
                if (tiers.length <= 1) return;
                tiers.splice(idx, 1);
                render();
                syncInput();
                refreshDirty();
            }));

            row.appendChild(left);
            row.appendChild(actions);
            return row;
        }

        function cleanedTiers() {
            return tiers.map(function (t) {
                return {
                    key: sanitizeKey(t.key),
                    min_percent: clampInt(t.min_percent, 0, 100),
                    label: String(t.label || '').trim()
                };
            }).filter(function (t) { return t.label; }).sort(function (a, b) {
                return b.min_percent - a.min_percent;
            });
        }

        function computeTierForPercent(pct) {
            var p = clampInt(pct, 0, 100);
            var cleaned = cleanedTiers();
            for (var i = 0; i < cleaned.length; i++) {
                if (p >= cleaned[i].min_percent) return cleaned[i];
            }
            return null;
        }

        function minTierPercent() {
            var cleaned = cleanedTiers();
            if (!cleaned.length) return 100;
            var min = cleaned[cleaned.length - 1].min_percent;
            return min;
        }

        function escapeHtml(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function formatCourseTitle(raw) {
            var t = String(raw || '').trim();
            if (!t) return '«Название курса»';
            if (t.charAt(0) === '«') return t;
            return '«' + t + '»';
        }

        function formatBodyText(raw, courseTitleQuoted) {
            var t = String(raw || '').trim();
            if (!t) {
                t = 'успешно завершил(а) курс {course} и подтвердил(а) практические навыки.';
            }
            return t.replace(/\{course\}/g, courseTitleQuoted);
        }

        function scalePaperToFit() {
            if (!paperViewport || !paperScale) return;
            var paper = paperScale.querySelector('.cert-paper');
            if (!paper) return;
            var vw = paperViewport.clientWidth || 600;
            var scale = Math.min(1, vw / 1240);
            paperScale.style.transform = 'scale(' + scale + ')';
            paperScale.style.height = (877 * scale) + 'px';
        }

        function renderPreviewChips() {
            if (!previewChips) return;
            previewChips.innerHTML = '';
            var cleaned = cleanedTiers();
            cleaned.forEach(function (t) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ap-cert-preview__chip';
                btn.textContent = t.min_percent + '% — ' + t.label;
                btn.addEventListener('click', function () {
                    if (previewRange) previewRange.value = String(t.min_percent);
                    if (previewPercent) previewPercent.value = String(t.min_percent);
                    renderPreview();
                });
                previewChips.appendChild(btn);
            });
            var below = Math.max(0, minTierPercent() - 1);
            var btnNo = document.createElement('button');
            btnNo.type = 'button';
            btnNo.className = 'ap-cert-preview__chip ap-cert-preview__chip--muted';
            btnNo.textContent = below + '% — без сертификата';
            btnNo.addEventListener('click', function () {
                if (previewRange) previewRange.value = String(below);
                if (previewPercent) previewPercent.value = String(below);
                renderPreview();
            });
            previewChips.appendChild(btnNo);
        }

        function renderPreview() {
            if (!toggle) return;
            if (!toggle.checked) {
                if (previewPanel) previewPanel.hidden = true;
                return;
            }
            if (previewPanel) previewPanel.hidden = false;

            var pct = previewPercent ? previewPercent.value : (previewRange ? previewRange.value : 0);
            var p = clampInt(pct, 0, 100);
            if (previewRange) previewRange.value = String(p);
            if (previewPercent) previewPercent.value = String(p);

            renderPreviewChips();

            var tier = computeTierForPercent(p);
            if (previewResult) {
                if (tier) {
                    previewResult.innerHTML = '<span class="ap-cert-preview__ok">Будет выдан уровень:</span> <strong>' + escapeHtml(tier.label) + '</strong> <span class="ap-muted">(от ' + tier.min_percent + '%)</span>';
                } else {
                    previewResult.innerHTML = '<span class="ap-cert-preview__no">Сертификат не будет выдан</span><div class="ap-muted" style="margin-top:0.2rem;font-size:0.85rem">Результат ниже минимального порога (' + minTierPercent() + '%).</div>';
                }
            }

            if (paperScale && paperEmpty) {
                if (tier) {
                    paperEmpty.hidden = true;
                    paperScale.hidden = false;
                    var tierNode = paperScale.querySelector('.js-cert-tier-label');
                    if (tierNode) tierNode.textContent = tier.label;
                    var courseNode = paperScale.querySelector('.js-cert-course-title');
                    if (courseNode && titleInput) {
                        courseNode.textContent = formatCourseTitle(titleInput.value);
                    }
                    var bodyNode = paperScale.querySelector('.js-cert-body-text');
                    if (bodyNode) {
                        var ct = titleInput ? formatCourseTitle(titleInput.value) : '«Название курса»';
                        var bodyText = formatBodyText(bodyInput ? bodyInput.value : '', ct);
                        bodyNode.textContent = bodyText;
                    }
                } else {
                    paperEmpty.hidden = false;
                    paperScale.hidden = true;
                }
            }
            scalePaperToFit();
        }

        function render() {
            list.innerHTML = '';
            tiers.forEach(function (t, i) {
                list.appendChild(mkRow(t, i));
            });
            if (addBtn) addBtn.disabled = !toggle || !toggle.checked;
            renderPreview();
        }

        function snapshot() {
            var o = {};
            var els = form.querySelectorAll('input, textarea, select');
            els.forEach(function (el) {
                if (!el.name || el.name === '_token') return;
                if (el.type === 'radio' && !el.checked) return;
                if (el.type === 'checkbox') {
                    o[el.name] = el.checked ? el.value : '0';
                    return;
                }
                o[el.name] = el.value;
            });
            return JSON.stringify(o);
        }

        var initial = snapshot();
        function refreshDirty() {
            var dirty = snapshot() !== initial;
            bar.hidden = !dirty;
            window.onbeforeunload = dirty ? function () { return ''; } : null;
        }

        form.addEventListener('input', refreshDirty);
        form.addEventListener('change', refreshDirty);
        form.addEventListener('submit', function () {
            window.onbeforeunload = null;
            initial = snapshot();
            bar.hidden = true;
        });

        function syncDetails() {
            if (!toggle) return;
            var on = !!toggle.checked;
            if (details) details.hidden = !on;
            if (addBtn) addBtn.disabled = !on;
            if (list) list.setAttribute('aria-disabled', on ? 'false' : 'true');
            if (toggleLabel) {
                toggleLabel.textContent = on ? 'Сертификат включён' : 'Сертификат выключен';
            }
            if (previewPanel) previewPanel.hidden = !on;
            renderPreview();
        }

        if (toggle) {
            toggle.addEventListener('change', function () {
                syncDetails();
                refreshDirty();
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', function () {
                tiers.push({ key: '', min_percent: 70, label: '' });
                render();
                syncInput();
                refreshDirty();
            });
        }

        if (previewRange) {
            previewRange.addEventListener('input', function () {
                if (previewPercent) previewPercent.value = previewRange.value;
                renderPreview();
            });
        }
        if (previewPercent) {
            previewPercent.addEventListener('input', function () {
                if (previewRange) previewRange.value = previewPercent.value;
                renderPreview();
            });
        }
        if (titleInput) {
            titleInput.addEventListener('input', renderPreview);
        }
        if (bodyInput) {
            bodyInput.addEventListener('input', renderPreview);
        }
        window.addEventListener('resize', scalePaperToFit);

        syncInput();
        render();
        syncDetails();
        refreshDirty();
    })();
</script>
