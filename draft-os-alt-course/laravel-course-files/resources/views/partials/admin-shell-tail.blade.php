{{-- Палитра команд и модалка создания курса: подключается из layouts.admin и layouts.course (admin). --}}
@if (($portalStaffAccess ?? null) && $portalStaffAccess->canCreateCourses())
    @include('partials.admin-create-course-modal')
@endif

<div id="ap-command-palette" class="ap-cmd-palette" hidden aria-hidden="true">
    <div class="ap-cmd-palette__overlay" data-ap-cmd-close tabindex="-1" aria-hidden="true"></div>
    <div class="ap-cmd-palette__panel" role="dialog" aria-modal="true" aria-label="Палитра команд">
        <div class="ap-cmd-palette__search">
            @include('partials.ap-icon', ['name' => 'search', 'size' => 'md'])
            <input id="ap-cmd-palette-q" type="search" class="ap-cmd-palette__input" placeholder="Поиск курсов, модулей, email…" autocomplete="off">
        </div>
        <div id="ap-cmd-palette-results" class="ap-cmd-palette__results"></div>
        <div class="ap-cmd-palette__footer">
            <span><kbd>↑</kbd><kbd>↓</kbd> навигация</span>
            <span><kbd>Enter</kbd> выбрать</span>
            <span><kbd>Esc</kbd> закрыть</span>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin-create-course-modal.js') }}" defer></script>
<script src="{{ asset('js/admin-command-palette.js') }}" defer></script>
<script src="{{ asset('js/admin-settings-menu.js') }}" defer></script>
<script src="{{ asset('js/admin-topbar.js') }}" defer></script>
