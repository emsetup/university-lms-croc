{{-- Модалка «Поделиться» — современный UX с анимациями --}}
<div id="ap-modal-share-link" class="ap-modal ap-share-modal" role="dialog" aria-modal="true" aria-labelledby="ap-share-link-title" aria-hidden="true" hidden>
    <div class="ap-modal__backdrop ap-share-modal__backdrop" data-ap-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel ap-share-modal__panel" role="document">
        <button type="button" class="ap-share-modal__x" data-ap-modal-close aria-label="Закрыть">
            @include('partials.ap-icon', ['name' => 'x', 'size' => 'sm'])
        </button>

        <div class="ap-share-modal__hero">
            <div class="ap-share-modal__orb" aria-hidden="true"></div>
            <div class="ap-share-modal__icon-wrap" aria-hidden="true">
                <span class="ap-share-modal__icon">@include('partials.ap-icon', ['name' => 'share', 'size' => 'md'])</span>
            </div>
            <h2 id="ap-share-link-title" class="ap-share-modal__title">Поделиться</h2>
            <p class="ap-share-modal__target" id="ap-share-link-target"></p>
            <p class="ap-share-modal__hint">Постоянная ссылка · нужен вход · доступ к курсу сохраняется</p>
        </div>

        <div class="ap-share-modal__body">
            <div id="ap-share-link-off" class="ap-share-modal__pane" hidden>
                <div class="ap-share-modal__idle">
                    <div class="ap-share-modal__idle-ring" aria-hidden="true"></div>
                    <p class="ap-share-modal__idle-title">Ссылка выключена</p>
                    <p class="ap-share-modal__idle-text">Включите — получите постоянный адрес, которым можно делиться.</p>
                    <button type="button" class="ap-share-modal__btn-primary" id="ap-share-link-enable">
                        <span class="ap-share-modal__btn-label">Включить ссылку</span>
                        <span class="ap-share-modal__btn-spinner" aria-hidden="true"></span>
                    </button>
                </div>
            </div>

            <div id="ap-share-link-on" class="ap-share-modal__pane" hidden>
                <div class="ap-share-modal__live">
                    <div class="ap-share-modal__live-badge">
                        <span class="ap-share-modal__pulse" aria-hidden="true"></span>
                        Ссылка активна
                    </div>

                    <label class="ap-share-modal__label" for="ap-share-link-url">Адрес</label>
                    <div class="ap-share-modal__url-box" id="ap-share-link-url-box">
                        <input type="text" class="ap-share-modal__url" id="ap-share-link-url" readonly>
                        <button type="button" class="ap-share-modal__copy" id="ap-share-link-copy" title="Копировать">
                            <span class="ap-share-modal__copy-idle" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                            </span>
                            <span class="ap-share-modal__copy-ok" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25"><path d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span class="ap-share-modal__copy-text">Копировать</span>
                        </button>
                    </div>

                    <div class="ap-share-modal__secondary">
                        <button type="button" class="ap-share-modal__btn-ghost" id="ap-share-link-regen">Новая ссылка</button>
                        <button type="button" class="ap-share-modal__btn-ghost ap-share-modal__btn-ghost--danger" id="ap-share-link-disable">Выключить</button>
                    </div>
                </div>
            </div>

            <p class="ap-share-modal__toast" id="ap-share-link-msg" hidden></p>
        </div>
    </div>
</div>
