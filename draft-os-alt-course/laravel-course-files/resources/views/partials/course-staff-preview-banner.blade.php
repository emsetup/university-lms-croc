@if (! empty($courseStaffPreviewActive))
    @php
        $previewCourse = $courseStaffPreviewCourse ?? null;
        $title = $previewCourse ? (string) $previewCourse->title : (\App\Support\CourseStaffPreview::courseTitleFromSession() ?? 'Курс');
        $status = 'курс';
        if ($previewCourse) {
            if ($previewCourse->is_archived) {
                $status = 'архив';
            } elseif ($previewCourse->is_published) {
                $status = 'опубликован';
            } else {
                $status = 'черновик';
            }
        }
        $adminSlug = $previewCourse ? (string) $previewCourse->slug : null;
    @endphp
    <div class="impersonation-banner impersonation-banner--course-preview" role="status">
        <span class="impersonation-banner__text">
            Предпросмотр курса «<strong>{{ $title }}</strong>»
            <span class="muted">({{ $status }}) — навигация без блокировок. Прогресс сохраняется на вашу учётную запись.</span>
        </span>
        <div class="impersonation-banner__actions">
            <a class="btn btn-primary impersonation-banner__btn" href="{{ route('portal.course-preview.end') }}">
                Завершить предпросмотр
            </a>
            @if ($adminSlug)
                <a class="btn btn-ghost impersonation-banner__btn" href="{{ route('admin.theory.index', ['adminCourse' => $adminSlug]) }}" target="_blank" rel="noopener">
                    Вернуться в админку
                </a>
            @endif
            <button type="button" class="btn btn-ghost impersonation-banner__btn" onclick="window.close()">
                Закрыть окно
            </button>
        </div>
    </div>
@endif
