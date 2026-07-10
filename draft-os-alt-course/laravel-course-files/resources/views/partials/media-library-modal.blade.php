<div
    id="ap-media-lib-modal"
    class="ap-media-lib-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ap-media-lib-title"
    aria-hidden="true"
    data-ap-media-config
    data-ap-media-api="{{ route('admin.media.api') }}"
    data-ap-media-upload="{{ route('admin.media.upload') }}"
    data-ap-media-pin-tpl="{{ url('/adm/media') }}/__UUID__/pin"
    data-ap-media-csrf="{{ csrf_token() }}"
>
    <div class="ap-media-lib-panel" role="document">
        <header class="ap-media-lib-head">
            <h2 id="ap-media-lib-title">Библиотека картинок</h2>
            <div class="ap-media-lib-tabs">
                <button type="button" class="ap-media-lib-tab is-active" data-ap-media-tab="mine">Мои</button>
                <button type="button" class="ap-media-lib-tab" data-ap-media-tab="course" hidden>Этот курс</button>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" data-ap-media-close aria-label="Закрыть">×</button>
        </header>
        <div class="ap-media-lib-drop" data-ap-media-drop>
            <strong>Перетащите изображения сюда</strong><br>
            <span class="small">или нажмите для выбора · JPEG, PNG, GIF, WebP · до 10 МБ</span>
            <input type="file" data-ap-media-file accept="image/jpeg,image/png,image/gif,image/webp" multiple class="ap-media-lib-file-input">
        </div>
        <div class="ap-media-lib-uploads" data-ap-media-uploads></div>
        <div class="ap-media-lib-body">
            <div class="ap-media-lib-layout">
                <div class="ap-media-lib-grid" data-ap-media-grid></div>
                <aside class="ap-media-lib-details" data-ap-media-details hidden>
                    <h3 class="ap-media-lib-details__title">Параметры вставки</h3>
                    <div class="ap-media-lib-details__preview" data-ap-media-preview>
                        <img src="" alt="" data-ap-media-preview-img>
                    </div>
                    <p class="ap-media-lib-details__dims ap-muted small" data-ap-media-dims></p>
                    <label class="ap-media-lib-field">
                        <span class="ap-media-lib-field__label">Альтернативный текст</span>
                        <input type="text" class="form-input" data-ap-media-opt-alt placeholder="Описание для доступности" autocomplete="off">
                    </label>
                    <div class="ap-media-lib-field">
                        <span class="ap-media-lib-field__label">Размер</span>
                        <div class="ap-media-lib-btn-group" role="group" aria-label="Размер изображения" data-ap-media-size-group>
                            <button type="button" class="ap-media-lib-opt is-active" data-ap-media-size="full">Полный</button>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-size="large">Большой</button>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-size="medium">Средний</button>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-size="small">Малый</button>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-size="thumb">Мини</button>
                        </div>
                    </div>
                    <div class="ap-media-lib-field">
                        <span class="ap-media-lib-field__label">Выравнивание</span>
                        <div class="ap-media-lib-btn-group" role="group" aria-label="Выравнивание" data-ap-media-align-group>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-align="none" title="На всю ширину блока">Блок</button>
                            <button type="button" class="ap-media-lib-opt is-active" data-ap-media-align="center" title="По центру">Центр</button>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-align="left" title="Обтекание справа">Слева</button>
                            <button type="button" class="ap-media-lib-opt" data-ap-media-align="right" title="Обтекание слева">Справа</button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
        <footer class="ap-media-lib-foot">
            <p class="ap-media-lib-foot-hint ap-muted small" id="ap-media-lib-hint">Выберите картинку в сетке слева</p>
            <button type="button" class="btn btn-ghost" data-ap-media-cancel>Отмена</button>
            <button type="button" class="btn btn-primary" data-ap-media-insert disabled>Вставить</button>
        </footer>
    </div>
</div>
