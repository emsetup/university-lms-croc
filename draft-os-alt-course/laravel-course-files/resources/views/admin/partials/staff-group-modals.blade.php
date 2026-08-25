@php
    use App\Models\PortalStaff;
    use App\Support\LearnerDisplay;
    $assignedKeys = $assignedPermissionKeys ?? [];
    $groupRoleOld = old('role', PortalStaff::ROLE_INSTRUCTOR);
    $rolesNeedCourses = [
        PortalStaff::ROLE_INSTRUCTOR,
        PortalStaff::ROLE_COURSE_TESTER,
        PortalStaff::ROLE_COURSE_EDITOR,
    ];
@endphp

<div class="ap-modal" id="ap-staff-group-form-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-staff-group-form-title">
    <div class="ap-modal__backdrop" data-ap-group-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel ap-modal__panel--wide ap-modal__panel--staff-group">
        <button type="button" class="ap-modal__close" data-ap-group-modal-close aria-label="Закрыть">&times;</button>
        <h2 id="ap-staff-group-form-title" class="ap-modal__title">Группа</h2>
        <p class="ap-muted ap-staff-group-form-lead">
            У группы задаётся <strong>роль</strong> (как у сотрудника): все участники получают её права.
            В <strong>нескольких группах</strong> — права <strong>складываются</strong> с личной ролью и доп. галочками ниже.
            Новых по email создаём с ролью этой группы.
        </p>
        <form method="post" action="{{ route('admin.staff.groups.store') }}" class="ap-modal__form" id="ap-staff-group-form">
            @csrf
            <input type="hidden" name="tab" value="groups">
            <input type="hidden" name="_return_q" value="{{ $staffSearch ?? '' }}" id="ap-staff-group-return-q">
            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-staff-group-name">Название</label>
                <input id="ap-staff-group-name" name="name" type="text" required maxlength="120" class="ap-modal__input" value="{{ old('name') }}" autocomplete="off">
            </div>
            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-staff-group-desc">Описание <span class="ap-muted">(необязательно)</span></label>
                <textarea id="ap-staff-group-desc" name="description" rows="2" maxlength="2000" class="ap-modal__input">{{ old('description') }}</textarea>
            </div>
            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-staff-group-sort">Порядок в списке</label>
                <input id="ap-staff-group-sort" name="sort" type="number" min="0" max="100000" class="ap-modal__input" value="{{ old('sort', 0) }}" style="max-width:8rem">
            </div>
            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-staff-group-role">Роль группы</label>
                <select id="ap-staff-group-role" name="role" class="ap-modal__input" required>
                    <option value="{{ PortalStaff::ROLE_PORTAL_ADMIN }}" @selected($groupRoleOld === PortalStaff::ROLE_PORTAL_ADMIN)>Администратор портала</option>
                    <option value="{{ PortalStaff::ROLE_COURSE_MODERATOR }}" @selected($groupRoleOld === PortalStaff::ROLE_COURSE_MODERATOR)>Модератор</option>
                    <option value="{{ PortalStaff::ROLE_PORTAL_AUDITOR }}" @selected($groupRoleOld === PortalStaff::ROLE_PORTAL_AUDITOR)>Аудитор портала</option>
                    <option value="{{ PortalStaff::ROLE_COURSE_CREATOR }}" @selected($groupRoleOld === PortalStaff::ROLE_COURSE_CREATOR)>Создатель курсов</option>
                    <option value="{{ PortalStaff::ROLE_COURSE_EDITOR }}" @selected($groupRoleOld === PortalStaff::ROLE_COURSE_EDITOR)>Редактор курсов</option>
                    <option value="{{ PortalStaff::ROLE_INSTRUCTOR }}" @selected($groupRoleOld === PortalStaff::ROLE_INSTRUCTOR)>Преподаватель курса</option>
                    <option value="{{ PortalStaff::ROLE_COURSE_TESTER }}" @selected($groupRoleOld === PortalStaff::ROLE_COURSE_TESTER)>Тестировщик курса</option>
                </select>
            </div>

            <div class="ap-staff-group-perms">
                <span class="ap-modal__label">Дополнительные права <span class="ap-muted">(поверх роли группы)</span></span>
                @foreach ($permissionSections as $section)
                    <div class="ap-staff-group-perms__section">
                        <h3 class="ap-staff-group-perms__section-title">{{ $section['title'] }}</h3>
                        <div class="ap-staff-group-perms__grid">
                            @foreach ($section['items'] as $item)
                                <label class="ap-staff-group-perm-line">
                                    <input type="checkbox" name="permissions[]" value="{{ $item['key'] }}" class="ap-staff-group-perm-cb" data-ap-assigned-scope="{{ in_array($item['key'], $assignedKeys, true) ? '1' : '0' }}">
                                    <span class="ap-staff-group-perm-line__text">
                                        <span class="ap-staff-group-perm-line__title">{{ $item['title'] }}</span>
                                        <span class="ap-staff-group-perm-line__hint">{{ $item['hint'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="ap-modal__field is-hidden" id="ap-staff-group-courses-wrap">
                <span class="ap-modal__label">Курсы группы</span>
                <p class="ap-muted" style="margin:0 0 0.5rem;font-size:0.85rem">Для участников с правами «назначенные» — вместе с курсами, привязанными к сотруднику в карточке пользователя.</p>
                <div class="ap-staff-courses-box">
                    @foreach ($courses as $c)
                        <label class="ap-staff-course-line">
                            <input type="checkbox" name="course_ids[]" value="{{ (int) $c->id }}" class="ap-staff-group-course-cb">
                            <span>{{ $c->title }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-staff-group-invite-emails">Добавить по email</label>
                <p class="ap-muted ap-staff-group-invite-hint">По одному адресу на строку (можно через запятую). Домен @{{ config('course.email_domain') }}. Новый сотрудник получит <strong>роль группы</strong> выше; если он уже есть — в группу добавится без смены личной роли (права группы всё равно применятся).</p>
                <textarea id="ap-staff-group-invite-emails" name="invite_emails" rows="4" maxlength="8000" class="ap-modal__input ap-staff-group-invite-textarea" placeholder="colleague@croc.ru&#10;another@croc.ru">{{ old('invite_emails') }}</textarea>
            </div>

            <div class="ap-modal__field">
                <span class="ap-modal__label">Участники из списка</span>
                <p class="ap-muted" style="margin:0 0 0.45rem;font-size:0.85rem">Уже заведённые сотрудники — отметьте галочками (можно вместе с полем email выше).</p>
                <div class="ap-staff-group-members-box">
                    @forelse ($allStaffForGroups as $m)
                        @php
                            $email = (string) ($m->learner?->email ?? '');
                            $name = $m->learner ? LearnerDisplay::portalDisplayName($m->learner) : '';
                        @endphp
                        <label class="ap-staff-course-line">
                            <input type="checkbox" name="member_ids[]" value="{{ (int) $m->id }}" class="ap-staff-group-member-cb">
                            <span>
                                @if ($name !== '')
                                    <strong>{{ $name }}</strong>
                                    <span class="ap-muted"> · {{ $email }}</span>
                                @else
                                    {{ $email !== '' ? $email : '—' }}
                                @endif
                            </span>
                        </label>
                    @empty
                        <p class="ap-muted">Список пуст — добавьте адреса в поле «Добавить по email» выше.</p>
                    @endforelse
                </div>
            </div>

            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-group-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary" id="ap-staff-group-form-submit">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<div class="ap-modal" id="ap-staff-group-delete-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-staff-group-delete-title">
    <div class="ap-modal__backdrop" data-ap-group-delete-close tabindex="-1"></div>
    <div class="ap-modal__panel">
        <button type="button" class="ap-modal__close" data-ap-group-delete-close aria-label="Закрыть">&times;</button>
        <h2 id="ap-staff-group-delete-title" class="ap-modal__title">Удалить группу?</h2>
        <p class="ap-staff-delete-text" id="ap-staff-group-delete-text"></p>
        <form method="post" class="ap-modal__footer ap-staff-delete-footer" id="ap-staff-group-delete-form" action="#">
            @csrf
            <button type="button" class="btn btn-ghost" data-ap-group-delete-close>Отмена</button>
            <button type="submit" class="btn btn-primary ap-btn-danger-solid">Удалить</button>
        </form>
    </div>
</div>

<script>
(function () {
    var formModal = document.getElementById('ap-staff-group-form-modal');
    var deleteModal = document.getElementById('ap-staff-group-delete-modal');
    var form = document.getElementById('ap-staff-group-form');
    var storeUrl = @json(route('admin.staff.groups.store'));
    var updateUrlTpl = @json($groupUpdateUrlTpl);
    var destroyUrlTpl = @json($groupDestroyUrlTpl);
    var assignedKeys = @json($assignedKeys);
    var roleSel = document.getElementById('ap-staff-group-role');
    var rolesNeedCourses = @json($rolesNeedCourses);

    function openFormModal() {
        if (!formModal) return;
        closeDeleteModal();
        formModal.classList.add('is-open');
        formModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');
    }
    function closeFormModal() {
        if (!formModal) return;
        formModal.classList.remove('is-open');
        formModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ap-modal-open');
    }
    function openDeleteModal() {
        if (!deleteModal) return;
        closeFormModal();
        deleteModal.classList.add('is-open');
        deleteModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('ap-modal-open');
    }
    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.classList.remove('is-open');
        deleteModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('ap-modal-open');
    }

    function syncCoursesWrap() {
        var wrap = document.getElementById('ap-staff-group-courses-wrap');
        if (!wrap) return;
        var show = false;
        if (roleSel && rolesNeedCourses.indexOf(roleSel.value) !== -1) show = true;
        document.querySelectorAll('.ap-staff-group-perm-cb').forEach(function (cb) {
            if (!cb.checked) return;
            if (cb.getAttribute('data-ap-assigned-scope') === '1') show = true;
        });
        wrap.classList.toggle('is-hidden', !show);
    }

    function setChecks(selector, csv) {
        document.querySelectorAll(selector).forEach(function (cb) { cb.checked = false; });
        if (!csv) return;
        csv.split(',').filter(Boolean).forEach(function (id) {
            var cb = form && form.querySelector(selector + '[value="' + id + '"]');
            if (cb) cb.checked = true;
        });
    }

    function openCreateGroup() {
        if (!form) return;
        form.setAttribute('action', storeUrl);
        document.getElementById('ap-staff-group-form-title').textContent = 'Создать группу';
        document.getElementById('ap-staff-group-form-submit').textContent = 'Сохранить';
        document.getElementById('ap-staff-group-name').value = '';
        document.getElementById('ap-staff-group-desc').value = '';
        document.getElementById('ap-staff-group-sort').value = '0';
        if (roleSel) roleSel.value = @json(PortalStaff::ROLE_INSTRUCTOR);
        var inv = document.getElementById('ap-staff-group-invite-emails');
        if (inv) inv.value = '';
        setChecks('.ap-staff-group-perm-cb', '');
        setChecks('.ap-staff-group-course-cb', '');
        setChecks('.ap-staff-group-member-cb', '');
        syncCoursesWrap();
        openFormModal();
    }

    function openEditGroup(card) {
        if (!form || !card) return;
        var id = card.getAttribute('data-group-id');
        form.setAttribute('action', updateUrlTpl.replace('__ID__', id));
        document.getElementById('ap-staff-group-form-title').textContent = 'Изменить группу';
        document.getElementById('ap-staff-group-form-submit').textContent = 'Сохранить';
        document.getElementById('ap-staff-group-name').value = card.getAttribute('data-name') || '';
        document.getElementById('ap-staff-group-desc').value = card.getAttribute('data-description') || '';
        document.getElementById('ap-staff-group-sort').value = card.getAttribute('data-sort') || '0';
        if (roleSel) roleSel.value = card.getAttribute('data-role') || @json(PortalStaff::ROLE_INSTRUCTOR);
        setChecks('.ap-staff-group-perm-cb', card.getAttribute('data-permissions') || '');
        setChecks('.ap-staff-group-course-cb', card.getAttribute('data-course-ids') || '');
        setChecks('.ap-staff-group-member-cb', card.getAttribute('data-member-ids') || '');
        syncCoursesWrap();
        openFormModal();
    }

    document.getElementById('ap-staff-open-create-group') && document.getElementById('ap-staff-open-create-group').addEventListener('click', openCreateGroup);
    document.querySelectorAll('[data-ap-group-modal-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
            closeFormModal();
        });
    });
    document.querySelectorAll('[data-ap-group-delete-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
            closeDeleteModal();
        });
    });
    if (roleSel) roleSel.addEventListener('change', syncCoursesWrap);
    document.querySelectorAll('.ap-staff-group-perm-cb').forEach(function (cb) {
        cb.addEventListener('change', syncCoursesWrap);
    });
    document.querySelectorAll('.ap-staff-group-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openEditGroup(btn.closest('.ap-staff-group-row'));
        });
    });
    document.querySelectorAll('.ap-staff-group-delete-open').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.ap-staff-group-row');
            if (!card) return;
            var df = document.getElementById('ap-staff-group-delete-form');
            var dt = document.getElementById('ap-staff-group-delete-text');
            if (df) df.setAttribute('action', destroyUrlTpl.replace('__ID__', card.getAttribute('data-group-id')));
            if (dt) dt.textContent = 'Группа «' + (card.getAttribute('data-name') || '') + '» будет удалена. Права участников от группы пропадут.';
            openDeleteModal();
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeFormModal(); closeDeleteModal(); }
    });

    @if (request()->query('add_group'))
    openCreateGroup();
    @endif
    @if (request()->query('edit_group'))
    (function () {
        var id = @json((string) request()->query('edit_group'));
        var card = document.querySelector('.ap-staff-group-row[data-group-id="' + id + '"]');
        if (card) openEditGroup(card);
    })();
    @endif

    @if ($errors->any() && old('name') !== null)
    (function () {
        openCreateGroup();
        var n = document.getElementById('ap-staff-group-name');
        if (n) n.value = @json(old('name', ''));
        var d = document.getElementById('ap-staff-group-desc');
        if (d) d.value = @json(old('description', ''));
        var inv = document.getElementById('ap-staff-group-invite-emails');
        if (inv) inv.value = @json(old('invite_emails', ''));
        var s = document.getElementById('ap-staff-group-sort');
        if (s) s.value = @json(old('sort', 0));
        if (roleSel) roleSel.value = @json(old('role', PortalStaff::ROLE_INSTRUCTOR));
        @foreach (old('permissions', []) as $perm)
        (function () {
            var cb = document.querySelector('.ap-staff-group-perm-cb[value="{{ $perm }}"]');
            if (cb) cb.checked = true;
        })();
        @endforeach
        @foreach (old('course_ids', []) as $cid)
        (function () {
            var cb = document.querySelector('.ap-staff-group-course-cb[value="{{ (int) $cid }}"]');
            if (cb) cb.checked = true;
        })();
        @endforeach
        @foreach (old('member_ids', []) as $mid)
        (function () {
            var cb = document.querySelector('.ap-staff-group-member-cb[value="{{ (int) $mid }}"]');
            if (cb) cb.checked = true;
        })();
        @endforeach
        syncCoursesWrap();
    })();
    @endif
})();
</script>
