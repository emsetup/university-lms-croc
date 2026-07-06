<div class="ap-modal" id="ap-audience-modal" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop"></div>
    <div class="ap-modal__panel ap-audience-modal__panel" role="dialog" aria-labelledby="ap-audience-modal-title">
        <header class="ap-modal__head">
            <h2 id="ap-audience-modal-title" class="ap-modal__title">Доступ к материалу</h2>
            <button type="button" class="btn btn-ghost ap-modal__close" id="ap-audience-modal-close" aria-label="Закрыть">@include('partials.ap-icon', ['name' => 'x', 'size' => 'sm'])</button>
        </header>
        <div class="ap-modal__body">
            <div id="ap-audience-picker-root"></div>
        </div>
        <footer class="ap-modal__footer">
            <button type="button" class="btn btn-ghost" id="ap-audience-modal-cancel">Отмена</button>
            <button type="button" class="btn btn-primary" id="ap-audience-modal-save">Сохранить</button>
        </footer>
    </div>
</div>
