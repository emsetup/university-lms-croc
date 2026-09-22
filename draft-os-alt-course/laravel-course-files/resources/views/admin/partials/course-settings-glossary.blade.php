@php
    $storeRoute = route('admin.course.glossary.store', $ap ?? ['adminCourse' => $course->slug ?? '']);
    $termUpdateUrl = static function (int $termId) use ($ap, $course): string {
        return route('admin.course.glossary.update', array_merge($ap ?? ['adminCourse' => $course->slug], ['term' => $termId]));
    };
    $termDestroyUrl = static function (int $termId) use ($ap, $course): string {
        return route('admin.course.glossary.destroy', array_merge($ap ?? ['adminCourse' => $course->slug], ['term' => $termId]));
    };
@endphp

<div class="ap-glossary-page">
    <header class="ap-glossary-page__head">
        <div>
            <h1 class="ap-page-title">Глоссарий курса</h1>
            <p class="ap-page-lead ap-muted">
                Аббревиатуры и термины для «{{ $course->title }}». В теории и вопросах они подсвечиваются автоматически — при наведении показывается описание.
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="ap-glossary-add">+ Термин</button>
    </header>

    @if ($terms->isEmpty())
        <div class="ap-glossary-empty ap-card">
            <div class="ap-glossary-empty__icon" aria-hidden="true">Aa</div>
            <p class="ap-glossary-empty__title">Пока пусто</p>
            <p class="ap-muted ap-m0">Добавьте первую аббревиатуру — например ЭДО или СБИС — и краткое пояснение. Обучающиеся увидят подсказку прямо в тексте.</p>
        </div>
    @else
        <div class="ap-glossary-table-wrap">
            <table class="ap-glossary-table">
                <thead>
                    <tr>
                        <th scope="col">Термин</th>
                        <th scope="col">Аббр.</th>
                        <th scope="col">Описание</th>
                        <th scope="col" class="ap-glossary-table__sort">№</th>
                        <th scope="col" class="ap-glossary-table__actions"><span class="sr-only">Действия</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($terms as $term)
                        <tr class="ap-glossary-row"
                            data-term-id="{{ (int) $term->id }}"
                            data-update-url="{{ $termUpdateUrl((int) $term->id) }}"
                            data-destroy-url="{{ $termDestroyUrl((int) $term->id) }}"
                            data-term="{{ e($term->term) }}"
                            data-abbreviation="{{ e((string) ($term->abbreviation ?? '')) }}"
                            data-definition="{{ e($term->definition) }}"
                            data-sort="{{ (int) $term->sort }}">
                            <td>
                                <span class="ap-glossary-term-chip">{{ $term->term }}</span>
                            </td>
                            <td>
                                @if (trim((string) ($term->abbreviation ?? '')) !== '')
                                    <code class="ap-glossary-abbr">{{ $term->abbreviation }}</code>
                                @else
                                    <span class="ap-muted">—</span>
                                @endif
                            </td>
                            <td class="ap-glossary-def">{{ $term->definition }}</td>
                            <td class="ap-glossary-table__sort ap-muted">{{ (int) $term->sort }}</td>
                            <td class="ap-glossary-table__actions">
                                <div class="ap-staff-icon-actions">
                                    <button type="button" class="ap-icon-btn ap-glossary-edit" aria-label="Изменить" title="Изменить">
                                        @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                                    </button>
                                    <button type="button" class="ap-icon-btn ap-icon-btn--danger ap-glossary-delete" aria-label="Удалить" title="Удалить">
                                        @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="ap-glossary-hint ap-muted">В тексте матчится и термин, и аббревиатура (если указана). Более длинные фразы имеют приоритет.</p>
    @endif
</div>

<div class="ap-modal" id="ap-glossary-modal" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop"></div>
    <div class="ap-modal__panel ap-glossary-modal__panel" role="dialog" aria-labelledby="ap-glossary-modal-title">
        <header class="ap-modal__head">
            <h2 id="ap-glossary-modal-title" class="ap-modal__title">Термин глоссария</h2>
            <button type="button" class="btn btn-ghost ap-modal__close" id="ap-glossary-modal-close" aria-label="Закрыть">@include('partials.ap-icon', ['name' => 'x', 'size' => 'sm'])</button>
        </header>
        <form id="ap-glossary-form" method="post" action="{{ $storeRoute }}" class="ap-modal__body">
            @csrf
            <label class="ap-modal__label" for="ap-gl-term">Термин или аббревиатура</label>
            <input type="text" id="ap-gl-term" name="term" class="ap-modal__input" required maxlength="120" placeholder="например ЭДО">
            <label class="ap-modal__label" for="ap-gl-abbr">Короткий вариант (необязательно)</label>
            <input type="text" id="ap-gl-abbr" name="abbreviation" class="ap-modal__input" maxlength="64" placeholder="если термин длинный — короткая форма для поиска в тексте">
            <label class="ap-modal__label" for="ap-gl-def">Описание</label>
            <textarea id="ap-gl-def" name="definition" class="ap-modal__input" rows="4" required maxlength="4000" placeholder="Кратко, что это значит для обучающегося"></textarea>
            <label class="ap-modal__label" for="ap-gl-sort">Порядок в списке</label>
            <input type="number" id="ap-gl-sort" name="sort" class="ap-modal__input" min="0" max="100000" value="100">
            <footer class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" id="ap-glossary-cancel">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </footer>
        </form>
    </div>
</div>

<form id="ap-glossary-delete-form" method="post" action="" hidden>
    @csrf
</form>

<script>
(function () {
    var modal = document.getElementById('ap-glossary-modal');
    var form = document.getElementById('ap-glossary-form');
    var delForm = document.getElementById('ap-glossary-delete-form');
    var storeUrl = @json($storeRoute);

    function openModal() {
        if (!modal) return;
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');
        setTimeout(function () {
            document.getElementById('ap-gl-term')?.focus();
        }, 30);
    }
    function closeModal() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        document.body.classList.remove('ap-modal-open');
    }
    function resetForm() {
        form.action = storeUrl;
        form.reset();
        document.getElementById('ap-gl-sort').value = '100';
    }
    document.getElementById('ap-glossary-add')?.addEventListener('click', function () {
        resetForm();
        document.getElementById('ap-glossary-modal-title').textContent = 'Новый термин';
        openModal();
    });
    document.querySelectorAll('.ap-glossary-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.ap-glossary-row');
            if (!row) return;
            form.action = row.getAttribute('data-update-url') || storeUrl;
            document.getElementById('ap-gl-term').value = row.getAttribute('data-term') || '';
            document.getElementById('ap-gl-abbr').value = row.getAttribute('data-abbreviation') || '';
            document.getElementById('ap-gl-def').value = row.getAttribute('data-definition') || '';
            document.getElementById('ap-gl-sort').value = row.getAttribute('data-sort') || '100';
            document.getElementById('ap-glossary-modal-title').textContent = 'Редактировать термин';
            openModal();
        });
    });
    document.querySelectorAll('.ap-glossary-delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.ap-glossary-row');
            if (!row || !confirm('Удалить «' + (row.getAttribute('data-term') || '') + '» из глоссария?')) return;
            delForm.action = row.getAttribute('data-destroy-url') || '';
            delForm.submit();
        });
    });
    document.getElementById('ap-glossary-modal-close')?.addEventListener('click', closeModal);
    document.getElementById('ap-glossary-cancel')?.addEventListener('click', closeModal);
    modal?.querySelector('.ap-modal__backdrop')?.addEventListener('click', closeModal);
})();
</script>
