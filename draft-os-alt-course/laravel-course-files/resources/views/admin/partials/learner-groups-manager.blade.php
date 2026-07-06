@php
    use App\Support\LearnerDisplay;
    $isCourse = ($groupScope ?? 'portal') === 'course';
    $storeRoute = $isCourse
        ? route('admin.course.learner-groups.store', $ap ?? ['adminCourse' => $course->slug ?? ''])
        : route('admin.learner-groups.portal.store');
    $groupUpdateUrl = static function (int $groupId) use ($isCourse, $ap, $course): string {
        if ($isCourse) {
            return route('admin.course.learner-groups.update', array_merge($ap ?? ['adminCourse' => $course->slug], ['group' => $groupId]));
        }

        return route('admin.learner-groups.portal.update', ['group' => $groupId]);
    };
    $groupDestroyUrl = static function (int $groupId) use ($isCourse, $ap, $course): string {
        if ($isCourse) {
            return route('admin.course.learner-groups.destroy', array_merge($ap ?? ['adminCourse' => $course->slug], ['group' => $groupId]));
        }

        return route('admin.learner-groups.portal.destroy', ['group' => $groupId]);
    };
@endphp

<div class="ap-learner-groups-page">
    <header class="ap-learner-groups-page__head">
        <div>
            <h1 class="ap-page-title">{{ $isCourse ? 'Группы курса' : 'Глобальные группы обучающихся' }}</h1>
            <p class="ap-page-lead ap-muted">
                @if ($isCourse)
                    Локальные группы только для «{{ $course->title }}». Используйте их при ограничении доступа к модулям и разделам.
                @else
                    Группы доступны во всех курсах. Удобно для потоков, пилотов и команд.
                @endif
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="ap-learner-group-add">+ Группа</button>
    </header>

  @if ($groups->isEmpty())
        <div class="ap-card ap-staff-groups__empty">
            <p class="ap-muted ap-m0">Пока нет групп. Создайте первую и добавьте участников.</p>
        </div>
    @else
        <div class="ap-staff-groups__grid">
            @foreach ($groups as $group)
                @php
                    $memberIds = $group->members->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                @endphp
                <article class="ap-staff-group-card ap-learner-group-card"
                         data-group-id="{{ (int) $group->id }}"
                         data-update-url="{{ $groupUpdateUrl((int) $group->id) }}"
                         data-destroy-url="{{ $groupDestroyUrl((int) $group->id) }}"
                         data-name="{{ e($group->name) }}"
                         data-description="{{ e((string) ($group->description ?? '')) }}"
                         data-color="{{ e((string) ($group->color ?? '#6366f1')) }}"
                         data-sort="{{ (int) $group->sort }}"
                         data-member-ids="{{ e(implode(',', $memberIds)) }}">
                    <div class="ap-staff-group-card__head">
                        <h2 class="ap-staff-group-card__title">
                            <span class="ap-audience-chip__dot" style="background:{{ $group->color ?? '#6366f1' }}"></span>
                            {{ $group->name }}
                        </h2>
                        <div class="ap-staff-icon-actions">
                            <button type="button" class="ap-icon-btn ap-learner-group-edit" aria-label="Изменить" title="Изменить">
                                @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                            </button>
                            <button type="button" class="ap-icon-btn ap-icon-btn--danger ap-learner-group-delete-open" aria-label="Удалить" title="Удалить">
                                @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                            </button>
                        </div>
                    </div>
                    @if (trim((string) ($group->description ?? '')) !== '')
                        <p class="ap-staff-group-card__desc">{{ $group->description }}</p>
                    @endif
                    <p class="ap-staff-group-card__meta">
                        <span>{{ (int) ($group->members_count ?? $group->members->count()) }} @choice('участник|участника|участников', (int) ($group->members_count ?? $group->members->count()))</span>
                    </p>
                </article>
            @endforeach
        </div>
    @endif
</div>

<div class="ap-modal" id="ap-learner-group-modal" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop"></div>
    <div class="ap-modal__panel" role="dialog" aria-labelledby="ap-learner-group-modal-title">
        <header class="ap-modal__head">
            <h2 id="ap-learner-group-modal-title" class="ap-modal__title">Группа</h2>
            <button type="button" class="btn btn-ghost ap-modal__close" id="ap-learner-group-modal-close" aria-label="Закрыть">@include('partials.ap-icon', ['name' => 'x', 'size' => 'sm'])</button>
        </header>
        <form id="ap-learner-group-form" method="post" action="{{ $storeRoute }}" class="ap-modal__body">
            @csrf
            <label class="ap-modal__label" for="ap-lg-name">Название</label>
            <input type="text" id="ap-lg-name" name="name" class="ap-modal__input" required maxlength="120">
            <label class="ap-modal__label" for="ap-lg-desc">Описание</label>
            <textarea id="ap-lg-desc" name="description" class="ap-modal__input" rows="2" maxlength="2000"></textarea>
            <label class="ap-modal__label" for="ap-lg-color">Цвет</label>
            <input type="color" id="ap-lg-color" name="color" class="ap-modal__input ap-learner-group-color" value="#6366f1">
            <label class="ap-modal__label" for="ap-lg-sort">Порядок</label>
            <input type="number" id="ap-lg-sort" name="sort" class="ap-modal__input" min="0" max="100000" value="0">
            <fieldset class="ap-staff-group-members-box">
                <legend class="ap-modal__label">Участники</legend>
                <div class="ap-staff-courses-box ap-learner-group-members-list">
                    @foreach ($learners as $learner)
                        @php
                            $label = LearnerDisplay::portalDisplayName($learner) ?: $learner->email;
                        @endphp
                        <label class="ap-staff-course-line">
                            <input type="checkbox" name="member_ids[]" value="{{ (int) $learner->id }}">
                            <span>{{ $label }} <span class="ap-muted">· {{ $learner->email }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <footer class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" id="ap-learner-group-cancel">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </footer>
        </form>
    </div>
</div>

<form id="ap-learner-group-delete-form" method="post" action="" hidden>
    @csrf
</form>

<script>
(function () {
    var modal = document.getElementById('ap-learner-group-modal');
    var form = document.getElementById('ap-learner-group-form');
    var delForm = document.getElementById('ap-learner-group-delete-form');
    var storeUrl = @json($storeRoute);

    function openModal() {
        if (!modal) return;
        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');
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
        form.querySelectorAll('input[name="member_ids[]"]').forEach(function (cb) { cb.checked = false; });
        document.getElementById('ap-lg-color').value = '#6366f1';
    }
    document.getElementById('ap-learner-group-add')?.addEventListener('click', function () {
        resetForm();
        document.getElementById('ap-learner-group-modal-title').textContent = 'Новая группа';
        openModal();
    });
    document.querySelectorAll('.ap-learner-group-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.ap-learner-group-card');
            if (!card) return;
            form.action = card.getAttribute('data-update-url') || storeUrl;
            document.getElementById('ap-lg-name').value = card.getAttribute('data-name') || '';
            document.getElementById('ap-lg-desc').value = card.getAttribute('data-description') || '';
            document.getElementById('ap-lg-color').value = card.getAttribute('data-color') || '#6366f1';
            document.getElementById('ap-lg-sort').value = card.getAttribute('data-sort') || '0';
            var ids = (card.getAttribute('data-member-ids') || '').split(',').filter(Boolean);
            form.querySelectorAll('input[name="member_ids[]"]').forEach(function (cb) {
                cb.checked = ids.indexOf(cb.value) !== -1;
            });
            document.getElementById('ap-learner-group-modal-title').textContent = 'Редактировать группу';
            openModal();
        });
    });
    document.querySelectorAll('.ap-learner-group-delete-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.ap-learner-group-card');
            if (!card || !confirm('Удалить группу «' + (card.getAttribute('data-name') || '') + '»?')) return;
            delForm.action = card.getAttribute('data-destroy-url') || '';
            delForm.submit();
        });
    });
    document.getElementById('ap-learner-group-modal-close')?.addEventListener('click', closeModal);
    document.getElementById('ap-learner-group-cancel')?.addEventListener('click', closeModal);
    modal.querySelector('.ap-modal__backdrop')?.addEventListener('click', closeModal);
})();
</script>
