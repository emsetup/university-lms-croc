@extends('layouts.admin')

@section('title', 'Сотрудники портала')

@php
    use App\Models\PortalStaff;
    use App\Models\Course;
    use App\Support\LearnerDisplay;
    $courses = Course::query()->orderBy('sort')->orderBy('id')->get(['id', 'title']);
    $__staffRouteToken = 999999999;
    $staffUpdateUrlTpl = str_replace((string) $__staffRouteToken, '__ID__', route('admin.staff.update', ['staff' => $__staffRouteToken]));
    $staffDestroyUrlTpl = str_replace((string) $__staffRouteToken, '__ID__', route('admin.staff.destroy', ['staff' => $__staffRouteToken]));
    $pickedOldCourses = $errors->any() ? collect(old('course_ids', []))->map(fn ($v) => (int) $v)->all() : [];
    $staffSort = $staffSort ?? 'id';
    $staffDir = $staffDir ?? 'asc';
    $staffSearch = $staffSearch ?? '';
    $staffTab = $staffTab ?? 'users';
    $__groupRouteToken = 999999998;
    $groupUpdateUrlTpl = str_replace((string) $__groupRouteToken, '__ID__', route('admin.staff.groups.update', ['group' => $__groupRouteToken]));
    $groupDestroyUrlTpl = str_replace((string) $__groupRouteToken, '__ID__', route('admin.staff.groups.destroy', ['group' => $__groupRouteToken]));
    $staffIndexParams = array_filter(['tab' => 'users', 'q' => $staffSearch !== '' ? $staffSearch : null, 'sort' => $staffSort !== 'id' ? $staffSort : null, 'dir' => ($staffSort !== 'id' && $staffDir !== 'asc') ? $staffDir : null]);
    $groupsIndexParams = array_filter(['tab' => 'groups', 'q' => $staffSearch !== '' ? $staffSearch : null]);
@endphp

@section('content')
    <div class="ap-page ap-fade ap-staff">
        <div class="ap-staff__head">
            <div>
                <h1 class="ap-page-title ap-staff__title">Сотрудники и доступ</h1>
                <p class="ap-page-lead ap-staff__lead">
                    Учётные записи с доступом в <code>/adm</code> после SSO. Роли задают базовые права; <strong>группы</strong> дополняют их для команд и проектов.
                </p>
            </div>
            <div class="ap-staff__head-actions">
                <button type="button" class="btn btn-primary ap-staff__add @if ($staffTab !== 'users') is-hidden @endif" id="ap-staff-open-create">
                    @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                    Добавить сотрудника
                </button>
                <button type="button" class="btn btn-primary ap-staff__add @if ($staffTab !== 'groups') is-hidden @endif" id="ap-staff-open-create-group">
                    @include('partials.ap-icon', ['name' => 'plus', 'size' => 'sm'])
                    Создать группу
                </button>
            </div>
        </div>

        <nav class="ap-staff-tabs" aria-label="Разделы сотрудников">
            <a href="{{ route('admin.staff.index', $staffIndexParams) }}"
               class="ap-staff-tabs__a @if ($staffTab === 'users') ap-staff-tabs__a--active @endif">Пользователи</a>
            <a href="{{ route('admin.staff.index', $groupsIndexParams) }}"
               class="ap-staff-tabs__a @if ($staffTab === 'groups') ap-staff-tabs__a--active @endif">Группы</a>
        </nav>

        @if ($staffTab === 'users' && ! empty($staffSearchEnabled))
            <form method="get" action="{{ route('admin.staff.index') }}" class="ap-docker__search ap-staff__search" role="search" style="margin-top:0.75rem">
                <label class="ap-docker__search-label" for="ap-staff-q">Поиск по email</label>
                <input id="ap-staff-q" name="q" type="search" value="{{ $staffSearch }}" placeholder="Часть адреса…" class="ap-modal__input ap-docker__search-input" autocomplete="off">
                <input type="hidden" name="tab" value="users">
                @if ($staffSort !== 'id')
                    <input type="hidden" name="sort" value="{{ $staffSort }}">
                    <input type="hidden" name="dir" value="{{ $staffDir }}">
                @endif
                <button type="submit" class="btn btn-primary">Найти</button>
                @if (($staffSearch ?? '') !== '')
                    <a href="{{ route('admin.staff.index', ['tab' => 'users']) }}" class="btn btn-ghost">Сбросить</a>
                @endif
            </form>
        @endif

        @if (session('ok'))
            <div class="admin-flash admin-flash--ok ap-staff__flash">{{ session('ok') }}</div>
        @endif
        @if (session('err'))
            <div class="admin-flash admin-flash--err ap-staff__flash">{{ session('err') }}</div>
        @endif
        @if ($errors->any())
            <div class="admin-flash admin-flash--err ap-staff__flash" role="alert">
                <strong>Проверьте поля формы.</strong>
                <ul style="margin:0.35rem 0 0;padding-left:1.1rem">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($staffTab === 'groups')
            @include('admin.partials.staff-groups-tab')
        @endif

        @if ($staffTab === 'users')
        <div class="ap-card ap-staff__table-card">
            <div class="ap-table-wrap">
                <table class="ap-table ap-staff-table">
                    <thead>
                    <tr>
                        @include('admin.partials.staff-table-sort-th', ['column' => 'email', 'label' => 'Email', 'staffSort' => $staffSort, 'staffDir' => $staffDir, 'staffSearch' => $staffSearch])
                        @include('admin.partials.staff-table-sort-th', ['column' => 'name', 'label' => 'ФИО', 'staffSort' => $staffSort, 'staffDir' => $staffDir, 'staffSearch' => $staffSearch])
                        @include('admin.partials.staff-table-sort-th', ['column' => 'role', 'label' => 'Роль', 'staffSort' => $staffSort, 'staffDir' => $staffDir, 'staffSearch' => $staffSearch])
                        <th scope="col">Группы</th>
                        <th scope="col">Курсы</th>
                        <th scope="col" class="ap-staff-table__comment-col">Комментарий</th>
                        @include('admin.partials.staff-table-sort-th', ['column' => 'login', 'label' => 'Последний вход', 'staffSort' => $staffSort, 'staffDir' => $staffDir, 'staffSearch' => $staffSearch, 'class' => 'ap-staff-table__login-col'])
                        <th class="ap-staff-table__actions-col" scope="col">Действия</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($items as $row)
                        @php
                            $email = (string) ($row->learner?->email ?? '');
                            $roleLabel = match ($row->role) {
                                PortalStaff::ROLE_PORTAL_ADMIN => 'Администратор портала',
                                PortalStaff::ROLE_COURSE_MODERATOR => 'Модератор',
                                PortalStaff::ROLE_COURSE_CREATOR => 'Создатель курсов',
                                PortalStaff::ROLE_COURSE_EDITOR => 'Редактор курсов',
                                PortalStaff::ROLE_INSTRUCTOR => 'Преподаватель курса',
                                PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
                                default => $row->role,
                            };
                            $badgeClass = match ($row->role) {
                                PortalStaff::ROLE_PORTAL_ADMIN => 'ap-staff-badge ap-staff-badge--admin',
                                PortalStaff::ROLE_INSTRUCTOR => 'ap-staff-badge ap-staff-badge--instructor',
                                PortalStaff::ROLE_COURSE_MODERATOR => 'ap-staff-badge ap-staff-badge--moderator',
                                PortalStaff::ROLE_COURSE_CREATOR => 'ap-staff-badge ap-staff-badge--creator',
                                PortalStaff::ROLE_COURSE_EDITOR => 'ap-staff-badge ap-staff-badge--editor',
                                PortalStaff::ROLE_COURSE_TESTER => 'ap-staff-badge ap-staff-badge--tester',
                                default => 'ap-staff-badge ap-staff-badge--muted',
                            };
                            $courseIds = $row->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
                            $fullName = $row->learner ? LearnerDisplay::portalDisplayName($row->learner) : '';
                            $accessComment = trim((string) ($row->access_comment ?? ''));
                            if (in_array($accessComment, ['&quot;&quot;', '""'], true)) {
                                $accessComment = '';
                            }
                        @endphp
                        <tr class="ap-staff-row"
                            data-staff-id="{{ (int) $row->id }}"
                            data-email="{{ e($email) }}"
                            data-role="{{ e($row->role) }}"
                            data-course-ids="{{ e(implode(',', $courseIds)) }}"
                            data-access-comment="{{ e($accessComment) }}">
                            <td><strong class="ap-staff-table__email">{{ $email !== '' ? $email : '—' }}</strong></td>
                            <td class="ap-staff-table__name">
                                @if ($fullName !== '')
                                    {{ $fullName }}
                                @else
                                    <span class="ap-muted" title="Появится после входа через SSO, когда сохранится имя из учётной записи">—</span>
                                @endif
                            </td>
                            <td><span class="{{ $badgeClass }}">{{ $roleLabel }}</span></td>
                            <td class="ap-staff-table__groups">
                                @if ($row->groups->isEmpty())
                                    <span class="ap-muted">—</span>
                                @else
                                    <div class="ap-staff-group-tags">
                                        @foreach ($row->groups as $grp)
                                            <span class="ap-staff-group-tag">{{ $grp->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td class="ap-staff-table__courses">
                                @if ($row->courses->isEmpty())
                                    <span class="ap-muted">—</span>
                                @else
                                    <ul class="ap-staff-course-list">
                                        @foreach ($row->courses as $c)
                                            <li>{{ $c->title }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td class="ap-staff-table__comment">
                                @if ($accessComment !== '')
                                    <span class="ap-staff-table__comment-text" title="{{ e($accessComment) }}">{{ $accessComment }}</span>
                                @else
                                    <span class="ap-muted">—</span>
                                @endif
                            </td>
                            <td class="ap-staff-table__login">
                                @php
                                    $lastLogin = $row->learner?->last_login_at;
                                @endphp
                                @if ($lastLogin instanceof \DateTimeInterface)
                                    <time datetime="{{ $lastLogin->timezone(config('app.timezone'))->format('Y-m-d\TH:i') }}">
                                        {{ $lastLogin->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                    </time>
                                @else
                                    <span class="ap-muted" title="Пока не зафиксирован вход после обновления портала">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="ap-staff-icon-actions">
                                    <button type="button" class="ap-icon-btn ap-staff-edit-btn" data-ap-staff-edit aria-label="Изменить" title="Изменить">
                                        @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                                    </button>
                                    <button type="button" class="ap-icon-btn ap-icon-btn--danger ap-staff-delete-open" data-ap-staff-delete-open aria-label="Удалить" title="Удалить">
                                        @include('partials.ap-icon', ['name' => 'trash', 'size' => 'md'])
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if ($items->isEmpty())
                    <p class="ap-muted ap-staff__empty">
                        @if (($staffSearch ?? '') !== '')
                            По запросу ничего не найдено.
                        @else
                            Пока никого не добавили.
                        @endif
                    </p>
                @endif
            </div>
        </div>
        @endif
    </div>

    @if ($staffTab === 'users')
    {{-- Модалка: добавить / изменить --}}
    <div class="ap-modal" id="ap-staff-form-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-staff-form-title">
        <div class="ap-modal__backdrop" data-ap-staff-modal-close tabindex="-1"></div>
        <div class="ap-modal__panel ap-modal__panel--wide ap-modal__panel--staff-guide">
            <button type="button" class="ap-modal__close" data-ap-staff-modal-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-staff-form-title" class="ap-modal__title">Сотрудник</h2>
            <form method="post" action="{{ route('admin.staff.store') }}" class="ap-modal__form" id="ap-staff-form">
                @csrf
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-staff-email">Email</label>
                    <input id="ap-staff-email" name="email" type="email" required maxlength="190" class="ap-modal__input"
                           value="{{ old('email') }}" autocomplete="off">
                </div>
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-staff-role">Роль</label>
                    <select id="ap-staff-role" name="role" class="ap-modal__input">
                        @php $r = old('role', PortalStaff::ROLE_PORTAL_ADMIN); @endphp
                        <option value="{{ PortalStaff::ROLE_PORTAL_ADMIN }}" @if ($r === PortalStaff::ROLE_PORTAL_ADMIN) selected @endif>Администратор портала</option>
                        <option value="{{ PortalStaff::ROLE_COURSE_MODERATOR }}" @if ($r === PortalStaff::ROLE_COURSE_MODERATOR) selected @endif>Модератор</option>
                        <option value="{{ PortalStaff::ROLE_COURSE_CREATOR }}" @if ($r === PortalStaff::ROLE_COURSE_CREATOR) selected @endif>Создатель курсов</option>
                        <option value="{{ PortalStaff::ROLE_COURSE_EDITOR }}" @if ($r === PortalStaff::ROLE_COURSE_EDITOR) selected @endif>Редактор курсов</option>
                        <option value="{{ PortalStaff::ROLE_INSTRUCTOR }}" @if ($r === PortalStaff::ROLE_INSTRUCTOR) selected @endif>Преподаватель курса</option>
                        <option value="{{ PortalStaff::ROLE_COURSE_TESTER }}" @if ($r === PortalStaff::ROLE_COURSE_TESTER) selected @endif>Тестировщик курса</option>
                    </select>
                </div>
                @include('partials.admin-staff-role-guide')
                <div class="ap-modal__field is-hidden" id="ap-staff-courses-wrap">
                    <span class="ap-modal__label">Курсы</span>
                    <p class="ap-staff-courses-hint ap-muted" id="ap-staff-courses-hint"></p>
                    <div class="ap-staff-courses-box">
                        @foreach ($courses as $c)
                            <label class="ap-staff-course-line">
                                <input type="checkbox" name="course_ids[]" value="{{ (int) $c->id }}" class="ap-staff-course-cb">
                                <span>{{ $c->title }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="ap-modal__field">
                    <label class="ap-modal__label" for="ap-staff-access-comment">Комментарий</label>
                    <textarea id="ap-staff-access-comment" name="access_comment" class="ap-modal__input ap-staff-access-comment-input" rows="2" maxlength="500" placeholder="Номер заявки, ссылка на запрос, инициатор…">@if ($errors->any()){{ old('access_comment') }}@endif</textarea>
                    <p class="ap-staff-access-comment-hint ap-muted">Необязательно. Для учёта, по какому запросу выдан доступ (видно только администраторам в таблице сотрудников).</p>
                </div>
                <div class="ap-modal__footer">
                    <button type="button" class="btn btn-ghost" data-ap-staff-modal-close>Отмена</button>
                    <button type="submit" class="btn btn-primary" id="ap-staff-form-submit">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Модалка: удаление --}}
    <div class="ap-modal" id="ap-staff-delete-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-staff-delete-title">
        <div class="ap-modal__backdrop" data-ap-staff-delete-close tabindex="-1"></div>
        <div class="ap-modal__panel">
            <button type="button" class="ap-modal__close" data-ap-staff-delete-close aria-label="Закрыть">&times;</button>
            <h2 id="ap-staff-delete-title" class="ap-modal__title">Удалить сотрудника?</h2>
            <p class="ap-staff-delete-text" id="ap-staff-delete-text"></p>
            <form method="post" class="ap-modal__footer ap-staff-delete-footer" id="ap-staff-delete-form" action="#">
                @csrf
                <button type="button" class="btn btn-ghost" data-ap-staff-delete-close>Отмена</button>
                <button type="submit" class="btn btn-primary ap-btn-danger-solid">Удалить</button>
            </form>
        </div>
    </div>
    @endif

    @if ($staffTab === 'groups')
        @include('admin.partials.staff-group-modals')
    @endif

    @if ($staffTab === 'users')
    <script>
        (function () {
            var formModal = document.getElementById('ap-staff-form-modal');
            var deleteModal = document.getElementById('ap-staff-delete-modal');
            var form = document.getElementById('ap-staff-form');
            var emailIn = document.getElementById('ap-staff-email');
            var roleSel = document.getElementById('ap-staff-role');
            var commentIn = document.getElementById('ap-staff-access-comment');
            var coursesWrap = document.getElementById('ap-staff-courses-wrap');
            var coursesHint = document.getElementById('ap-staff-courses-hint');
            var formTitle = document.getElementById('ap-staff-form-title');
            var formSubmit = document.getElementById('ap-staff-form-submit');
            var storeUrl = @json(route('admin.staff.store'));
            var updateUrlTpl = @json($staffUpdateUrlTpl);
            var destroyUrlTpl = @json($staffDestroyUrlTpl);
            var deleteText = document.getElementById('ap-staff-delete-text');
            var deleteForm = document.getElementById('ap-staff-delete-form');
            var roleGuideData = {};
            var roleGuideEl = document.getElementById('ap-staff-role-guide-data');
            if (roleGuideEl) {
                try { roleGuideData = JSON.parse(roleGuideEl.textContent || '{}'); } catch (e) { roleGuideData = {}; }
            }
            var roleDetail = document.getElementById('ap-staff-role-detail');
            var roleDetailBadge = document.getElementById('ap-staff-role-detail-badge');
            var roleDetailAdmin = document.getElementById('ap-staff-role-detail-admin');
            var roleDetailSummary = document.getElementById('ap-staff-role-detail-summary');
            var roleDetailPerms = document.getElementById('ap-staff-role-detail-perms');
            var permSectionLabels = {
                admin: 'Админка /adm',
                courses: 'Курсы',
                content: 'Контент курса',
                learners: 'Обучающиеся',
                people: 'Люди (портал)',
                docker: 'Docker',
                staff: 'Сотрудники',
                settings: 'Настройки портала'
            };
            var permOrder = ['admin', 'courses', 'content', 'learners', 'people', 'docker', 'staff', 'settings'];

            function syncRoleGuide() {
                if (!roleSel) return;
                var role = roleSel.value;
                var info = roleGuideData[role];
                document.querySelectorAll('[data-ap-staff-role-row]').forEach(function (tr) {
                    tr.classList.toggle('is-selected', tr.getAttribute('data-ap-staff-role-row') === role);
                });
                if (!info || !roleDetail) return;
                roleDetail.hidden = false;
                if (roleDetailBadge) {
                    roleDetailBadge.className = 'ap-staff-badge ap-staff-badge--' + (info.badge || 'muted');
                    roleDetailBadge.textContent = info.label || role;
                }
                if (roleDetailAdmin) roleDetailAdmin.textContent = info.admin_note || '';
                if (roleDetailSummary) roleDetailSummary.textContent = info.summary || '';
                if (roleDetailPerms) {
                    roleDetailPerms.innerHTML = '';
                    permOrder.forEach(function (key) {
                        var cap = info.capabilities && info.capabilities[key];
                        if (!cap) return;
                        var li = document.createElement('li');
                        li.className = 'ap-staff-role-guide__perm-item';
                        var access = document.createElement('span');
                        access.className = 'ap-staff-access ap-staff-access--' + (cap.level || 'no');
                        access.title = cap.label || '';
                        var icon = document.createElement('span');
                        icon.className = 'ap-staff-access__icon';
                        icon.setAttribute('aria-hidden', 'true');
                        var text = document.createElement('span');
                        text.className = 'ap-staff-access__text';
                        text.textContent = cap.label || '—';
                        access.appendChild(icon);
                        access.appendChild(text);
                        var section = document.createElement('span');
                        section.className = 'ap-staff-role-guide__perm-section';
                        section.textContent = permSectionLabels[key] || key;
                        li.appendChild(section);
                        li.appendChild(access);
                        roleDetailPerms.appendChild(li);
                    });
                }
            }

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

            function syncCoursesVisibility() {
                if (!roleSel || !coursesWrap) return;
                var v = roleSel.value;
                var show = v === 'instructor' || v === 'course_tester' || v === 'course_editor';
                coursesWrap.classList.toggle('is-hidden', !show);
                if (coursesHint) {
                    if (v === 'course_editor') {
                        coursesHint.textContent = 'Отметьте курсы, которые редактор может править. Свои новые курсы он создаёт сам — их список дополняется автоматически.';
                    } else if (v === 'instructor' || v === 'course_tester') {
                        coursesHint.textContent = 'Выберите хотя бы один курс, к которому привязан сотрудник.';
                    } else {
                        coursesHint.textContent = '';
                    }
                }
            }

            function uncheckAllCourses() {
                document.querySelectorAll('.ap-staff-course-cb').forEach(function (cb) { cb.checked = false; });
            }

            function setCourseSelection(idsCsv) {
                uncheckAllCourses();
                if (!idsCsv) return;
                idsCsv.split(',').map(function (x) { return parseInt(x, 10); }).filter(function (n) { return n > 0; }).forEach(function (id) {
                    var cb = form.querySelector('.ap-staff-course-cb[value="' + id + '"]');
                    if (cb) cb.checked = true;
                });
            }

            function parseAccessComment(tr) {
                if (!tr) return '';
                return tr.getAttribute('data-access-comment') || '';
            }

            function openCreateModal() {
                if (!form) return;
                form.setAttribute('action', storeUrl);
                formTitle.textContent = 'Добавить сотрудника';
                formSubmit.textContent = 'Сохранить';
                emailIn.value = '';
                roleSel.value = 'portal_admin';
                if (commentIn) commentIn.value = '';
                uncheckAllCourses();
                syncCoursesVisibility();
                syncRoleGuide();
                openFormModal();
                setTimeout(function () { emailIn.focus(); }, 50);
            }

            function openEditModal(tr) {
                if (!form || !tr) return;
                var id = tr.getAttribute('data-staff-id');
                form.setAttribute('action', updateUrlTpl.replace('__ID__', id));
                formTitle.textContent = 'Изменить сотрудника';
                formSubmit.textContent = 'Сохранить';
                emailIn.value = tr.getAttribute('data-email') || '';
                roleSel.value = tr.getAttribute('data-role') || 'portal_admin';
                if (commentIn) commentIn.value = parseAccessComment(tr);
                setCourseSelection(tr.getAttribute('data-course-ids') || '');
                syncCoursesVisibility();
                syncRoleGuide();
                openFormModal();
                setTimeout(function () { emailIn.focus(); }, 50);
            }

            document.getElementById('ap-staff-open-create') && document.getElementById('ap-staff-open-create').addEventListener('click', openCreateModal);

            document.querySelectorAll('[data-ap-staff-modal-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeFormModal();
                });
            });
            document.querySelectorAll('[data-ap-staff-delete-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    if (el.classList.contains('ap-modal__backdrop')) e.preventDefault();
                    closeDeleteModal();
                });
            });

            document.querySelectorAll('.ap-staff-edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tr = btn.closest('.ap-staff-row');
                    openEditModal(tr);
                });
            });

            document.querySelectorAll('.ap-staff-delete-open').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var tr = btn.closest('.ap-staff-row');
                    if (!tr || !deleteForm || !deleteText) return;
                    var id = tr.getAttribute('data-staff-id');
                    var em = tr.getAttribute('data-email') || '—';
                    deleteForm.setAttribute('action', destroyUrlTpl.replace('__ID__', id));
                    deleteText.textContent = em + ' потеряет доступ к админ-панели.';
                    openDeleteModal();
                });
            });

            if (roleSel) {
                roleSel.addEventListener('change', function () {
                    syncCoursesVisibility();
                    syncRoleGuide();
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeFormModal();
                    closeDeleteModal();
                }
            });

            @if (request()->query('add'))
            openCreateModal();
            @endif
            @if (request()->query('edit'))
            (function () {
                var id = @json((string) request()->query('edit'));
                var tr = document.querySelector('.ap-staff-row[data-staff-id="' + id + '"]');
                if (tr) openEditModal(tr);
            })();
            @endif

            @if ($errors->any())
            (function () {
                var want = @json(old('email'));
                if (!want) return;
                var rows = document.querySelectorAll('.ap-staff-row');
                var found = null;
                rows.forEach(function (tr) {
                    if ((tr.getAttribute('data-email') || '') === want) found = tr;
                });
                if (found) openEditModal(found);
                else openCreateModal();
                if (emailIn) emailIn.value = want;
                if (roleSel) roleSel.value = @json(old('role', 'portal_admin'));
                if (commentIn) commentIn.value = @json(old('access_comment', ''));
                syncCoursesVisibility();
                syncRoleGuide();
                @foreach ($pickedOldCourses as $pid)
                (function () {
                    var cb = document.querySelector('.ap-staff-course-cb[value="{{ (int) $pid }}"]');
                    if (cb) cb.checked = true;
                })();
                @endforeach
            })();
            @endif
        })();
    </script>
    @endif
@endsection
