@if (! empty($staffAdminPreviewActive))
    @php
        $learner = $staffAdminPreviewTarget ?? null;
        $label = $learner
            ? (\App\Support\LearnerDisplay::portalDisplayName($learner) ?: $learner->email)
            : 'сотрудник';
        $role = $staffAdminPreviewRoleLabel ?? '';
    @endphp
    <div class="impersonation-banner impersonation-banner--staff-admin" role="status">
        <span class="impersonation-banner__text">
            Просмотр админки от лица: <strong>{{ $label }}</strong>
            @if ($role !== '')
                <span class="muted">({{ $role }})</span>
            @endif
            <span class="muted">— только просмотр, без сохранения изменений.</span>
        </span>
        <div class="impersonation-banner__actions">
            <a class="btn btn-primary impersonation-banner__btn" href="{{ route('admin.settings.staff-preview.end') }}">
                Выйти из просмотра
            </a>
            <button type="button" class="btn btn-ghost impersonation-banner__btn" onclick="window.close()">
                Закрыть окно
            </button>
            <a class="btn btn-ghost impersonation-banner__btn" href="{{ route('admin.settings') }}#prosmotr-sotrudnik" target="_blank" rel="noopener">
                Настройки просмотра
            </a>
        </div>
    </div>
@endif
