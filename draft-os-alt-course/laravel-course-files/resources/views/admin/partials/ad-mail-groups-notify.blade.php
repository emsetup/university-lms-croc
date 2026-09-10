{{-- Модалка: уведомить AD-группы рассылок о курсе. Ожидает $course / $adminCourseSlug. --}}
@php
    $adCourse = $course ?? null;
    $adSlug = $adCourse?->slug ?? ($adminCourseSlug ?? session('admin_course_slug'));
    $canNotifyAdGroups = ! empty($canNotifyAdMailGroups)
        || (is_string($adSlug) && $adSlug !== '' && app(\App\Services\PortalStaffAccess::class)->canEditCourseMeta((int) ($adCourse?->id ?? session('admin_course_id') ?? 0)));
@endphp
@if ($canNotifyAdGroups && is_string($adSlug) && $adSlug !== '')
    @php
        $adSearchUrl = route('admin.learners.ad-mail-groups.search', ['adminCourse' => $adSlug]);
        $adNotifyUrl = route('admin.learners.ad-mail-groups.notify', ['adminCourse' => $adSlug]);
        $adGroupCount = \Illuminate\Support\Facades\Schema::hasTable('ad_mail_groups')
            ? (int) \App\Models\AdMailGroup::query()->active()->count()
            : 0;
    @endphp
    <div class="ap-ad-mail-notify-bar" style="margin:0.75rem 0 0;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center">
        <button
            type="button"
            class="btn btn-secondary btn-sm"
            id="ap-ad-mail-open"
            data-ap-ad-mail-open
            data-search-url="{{ $adSearchUrl }}"
            data-notify-url="{{ $adNotifyUrl }}"
            data-csrf="{{ csrf_token() }}"
            data-course-title="{{ e((string) ($adCourse?->title ?? $courseTitle ?? '')) }}"
        >
            Уведомить группы рассылок
        </button>
        <span class="ap-muted small">Каталог AD: {{ $adGroupCount }} {{ $adGroupCount === 1 ? 'группа' : 'групп' }}</span>
    </div>

    <div id="ap-ad-mail-modal" class="ap-modal" role="dialog" aria-modal="true" aria-hidden="true" hidden>
        <div class="ap-modal__backdrop" data-ap-ad-mail-close tabindex="-1"></div>
        <div class="ap-modal__panel" style="max-width:34rem">
            <div class="ap-modal__head">
                <h2 class="ap-modal__title">Уведомить группы рассылок</h2>
                <button type="button" class="btn btn-ghost" data-ap-ad-mail-close>Закрыть</button>
            </div>
            <p class="ap-muted" style="margin:0 0 0.75rem">
                Выберите группы из каталога AD или найдите по названию/email. Письмо уйдёт на адрес группы; запись на курс не создаётся.
            </p>
            <label class="ap-learners-split__search-label" for="ap-ad-mail-search">Быстрый поиск</label>
            <input id="ap-ad-mail-search" type="search" class="ap-modal__input" placeholder="Начните вводить название или email…" autocomplete="off">
            <div id="ap-ad-mail-results" class="ap-ad-mail-results" style="margin-top:0.5rem;max-height:14rem;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;padding:0.35rem" role="listbox" aria-label="Список групп"></div>
            <div id="ap-ad-mail-chips" class="ap-ad-mail-chips" style="display:flex;flex-wrap:wrap;gap:0.35rem;margin-top:0.75rem" aria-label="Выбранные группы"></div>
            <p id="ap-ad-mail-msg" class="ap-muted small" style="margin:0.75rem 0 0" hidden></p>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-ad-mail-close>Отмена</button>
                <button type="button" class="btn btn-primary" id="ap-ad-mail-send" disabled>Отправить оповещение</button>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/admin-ad-mail-groups.js') }}?v={{ @filemtime(public_path('js/admin-ad-mail-groups.js')) ?: time() }}"></script>
@endif
