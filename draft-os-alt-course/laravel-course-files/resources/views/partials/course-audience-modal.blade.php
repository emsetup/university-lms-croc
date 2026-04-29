<div class="course-modal" id="course-audience-modal-root" aria-hidden="true">
    <div class="course-modal__backdrop" data-modal-close tabindex="-1"></div>
    <div class="course-modal__panel" id="course-audience-modal" role="dialog" aria-modal="true" aria-labelledby="course-audience-modal-title">
        <header class="course-modal__header">
            <h2 id="course-audience-modal-title" class="course-modal__title">Для кого этот материал</h2>
            <button type="button" class="course-modal__close btn btn-ghost" data-modal-close aria-label="Закрыть окно">
                <span aria-hidden="true">×</span>
            </button>
        </header>
        <div class="course-modal__body">
            @include('partials.course-audience-full-text')
        </div>
    </div>
</div>
