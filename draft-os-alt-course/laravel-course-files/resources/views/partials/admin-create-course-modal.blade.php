{{-- Глобальная модалка «Создать курс» (кнопка на странице курсов и палитра команд). --}}
<div class="ap-modal" id="ap-create-course-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="ap-create-course-title">
    <div class="ap-modal__backdrop" data-ap-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel">
        <button type="button" class="ap-modal__close" data-ap-modal-close aria-label="Закрыть">&times;</button>
        <h2 id="ap-create-course-title" class="ap-modal__title">Создать курс</h2>
        <form method="post" action="{{ route('admin.courses.store') }}" class="ap-modal__form" id="ap-create-course-form">
            @csrf
            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-create-title">Название</label>
                <input id="ap-create-title" name="title" type="text" required maxlength="200" class="ap-modal__input"
                       value="{{ old('title') }}" autocomplete="off">
            </div>
            <div class="ap-modal__field">
                <label class="ap-modal__label" for="ap-create-slug">Slug</label>
                <input id="ap-create-slug" name="slug" type="text" required maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                       class="ap-modal__input ap-modal__input--mono" value="{{ old('slug') }}" autocomplete="off"
                       title="Латиница, цифры и дефис">
                <p class="ap-modal__hint">Генерируется из названия; можно изменить вручную.</p>
            </div>
            <div class="ap-modal__footer">
                <button type="button" class="btn btn-ghost" data-ap-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Создать курс</button>
            </div>
        </form>
    </div>
</div>
