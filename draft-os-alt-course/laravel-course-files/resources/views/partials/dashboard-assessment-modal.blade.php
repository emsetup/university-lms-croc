{{-- Модальное окно «оценка по курсу» перед переходом на /assessment и финальную лабу --}}
<div class="course-modal dash-assess-modal" id="dash-assessment-modal-root" aria-hidden="true">
    <div class="course-modal__backdrop" data-modal-close tabindex="-1"></div>
    <div class="course-modal__panel dash-assess-modal__panel" id="dash-assessment-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dash-assess-modal-title">
        <button type="button" class="course-modal__close" data-modal-close aria-label="Закрыть">&times;</button>
        <h2 id="dash-assess-modal-title" class="dash-assess-modal__title">Статистика по пройденным модулям</h2>
        <p class="dash-assess-modal__sub muted">Наглядная разбивка по этапам и баллам. После просмотра можно перейти к формальной странице оценки или к финальной лабораторной.</p>
        <div class="dash-assess-modal__scroll">
            @include('partials.assessment-snapshot-inner', ['assessmentSnapshot' => $assessmentSnapshot, 'allDone' => $allDone])
        </div>
        <div class="dash-assess-modal__footer">
            @if ($allDone)
                <a class="btn btn-primary" href="{{ route('assessment') }}">Открыть страницу оценки</a>
                <a class="btn btn-ghost" href="{{ route('final-lab') }}">К финальной лабе</a>
            @else
                <span class="btn btn-primary" style="opacity:0.55;cursor:not-allowed" aria-disabled="true">Страница оценки недоступна</span>
                <a class="btn btn-ghost" href="{{ route('dashboard') }}">Обновить дашборд</a>
            @endif
            <button type="button" class="btn btn-ghost" data-modal-close>Закрыть</button>
        </div>
    </div>
</div>

<style>
    .dash-assess-modal {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.22s ease, visibility 0.22s ease;
        pointer-events: none;
    }
    .dash-assess-modal.is-open {
        visibility: visible;
        opacity: 1;
        pointer-events: auto;
    }
    .dash-assess-modal .course-modal__backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(3px);
    }
    .dash-assess-modal .course-modal__panel {
        position: relative;
        z-index: 1;
        max-width: min(920px, 96vw);
        width: 100%;
        max-height: 92vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: #fff;
        border-radius: 16px;
        padding: 1.2rem 1.35rem 1.1rem;
        box-shadow: 0 24px 64px rgba(0, 0, 0, 0.2);
    }
    .dash-assess-modal .course-modal__close {
        position: absolute;
        top: 0.65rem;
        right: 0.65rem;
        z-index: 2;
        border: none;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        cursor: pointer;
        color: var(--muted, #64748b);
        padding: 0.25rem 0.45rem;
        border-radius: 8px;
    }
    .dash-assess-modal .course-modal__close:hover {
        background: rgba(15, 23, 42, 0.06);
        color: var(--text, #0f172a);
    }
    .dash-assess-modal__title { margin: 0 0 0.35rem; font-size: 1.25rem; padding-right: 2rem; }
    .dash-assess-modal__sub { margin: 0 0 0.75rem; font-size: 0.88rem; line-height: 1.45; }
    .dash-assess-modal__scroll { max-height: min(70vh, 640px); overflow-y: auto; padding-right: 0.25rem; margin: 0 -0.25rem 0 0; }
    .dash-assess-modal__footer { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; padding-top: 0.85rem; border-top: 1px solid var(--line, #e5e7eb); }
</style>
