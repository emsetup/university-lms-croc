@if (! empty($learnerPreviewActive))
    @php
        $learner = $learnerPreviewTarget ?? ($currentLearner ?? null);
        $label = $learner
            ? (\App\Support\LearnerDisplay::portalDisplayName($learner) ?: $learner->email)
            : 'обучающийся';
    @endphp
    <div class="impersonation-banner" role="status">
        <span class="impersonation-banner__text">
            Просмотр в отдельном окне от лица: <strong>{{ $label }}</strong>
            <span class="muted">— в этой вкладке вы смотрите портал как обучающийся.</span>
        </span>
        <div class="impersonation-banner__actions">
            <a class="btn btn-primary impersonation-banner__btn" href="{{ route('portal.learner-preview.end') }}">
                Завершить просмотр
            </a>
            <button type="button" class="btn btn-ghost impersonation-banner__btn" onclick="window.close()">
                Закрыть окно
            </button>
            <a class="btn btn-ghost impersonation-banner__btn" href="{{ route('admin.settings') }}" target="_blank" rel="noopener">
                Настройки админки
            </a>
        </div>
    </div>
@endif
