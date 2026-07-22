@php
    /** @var list<array<string, mixed>> $mailTemplates */
    $mailTemplates = $mailTemplates ?? [];
@endphp

<div class="ap-mail-templates">
    <p class="ap-mail-templates__intro">
        Все письма портала идут через один визуальный шаблон. Ниже — типы уведомлений, текст и события, после которых письмо уходит автоматически.
    </p>

    <div class="ap-mail-templates__list">
        @foreach ($mailTemplates as $tpl)
            <article class="ap-mail-template" id="mail-tpl-{{ $tpl['id'] }}">
                <header class="ap-mail-template__head">
                    <div>
                        <p class="ap-mail-template__eyebrow">{{ $tpl['eyebrow'] }}</p>
                        <h2 class="ap-mail-template__title">{{ $tpl['title'] }}</h2>
                        <p class="ap-mail-template__subject"><span>Тема:</span> {{ $tpl['subject'] }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" data-ap-mail-tpl-toggle aria-expanded="false">
                        Предпросмотр
                    </button>
                </header>

                <div class="ap-mail-template__body">
                    <section class="ap-mail-template__block">
                        <h3>Текст</h3>
                        <ul class="ap-mail-template__copy">
                            <li><strong>Приветствие:</strong> {{ $tpl['greeting'] }}</li>
                            <li><strong>Сообщение:</strong> {{ $tpl['lead'] }}</li>
                            <li><strong>Кнопка:</strong> {{ $tpl['cta_label'] }}</li>
                            @foreach ($tpl['details'] as $label => $value)
                                <li><strong>{{ $label }}:</strong> {{ $value }} <span class="ap-muted">(пример)</span></li>
                            @endforeach
                        </ul>
                    </section>

                    <section class="ap-mail-template__block">
                        <h3>Триггеры</h3>
                        <ul class="ap-mail-template__triggers">
                            @foreach ($tpl['triggers'] as $trigger)
                                <li>{{ $trigger }}</li>
                            @endforeach
                        </ul>
                    </section>
                </div>

                <div class="ap-mail-template__preview" data-ap-mail-tpl-preview hidden>
                    <iframe title="Предпросмотр: {{ $tpl['title'] }}" sandbox="" srcdoc="{{ e($tpl['preview_html']) }}"></iframe>
                </div>
            </article>
        @endforeach
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-ap-mail-tpl-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.ap-mail-template');
            if (!card) return;
            var box = card.querySelector('[data-ap-mail-tpl-preview]');
            if (!box) return;
            var open = box.hidden;
            box.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.textContent = open ? 'Скрыть предпросмотр' : 'Предпросмотр';
        });
    });
})();
</script>
