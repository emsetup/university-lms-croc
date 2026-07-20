{{-- Лента обновлений (config/portal_changelog.php) — компактный блок над «Курсы» --}}
@php
    $changelogEntries = $changelogEntries ?? [];
    $changelogStorageKey = 'ap-dash-changelog-collapsed:'.(int) session('learner_id', 0);
@endphp

<section
    class="ap-card ap-dash-card ap-changelog-card"
    id="ap-dash-changelog"
    aria-labelledby="ap-changelog-heading"
    data-ap-changelog-panel
    data-ap-changelog-storage-key="{{ $changelogStorageKey }}"
>
    <div class="ap-dash-card__head ap-changelog-card__head">
        <h2 class="ap-card__title ap-dash-card__title ap-changelog-card__title" id="ap-changelog-heading">
            Что нового
        </h2>
        <button
            type="button"
            class="ap-changelog-toggle"
            data-ap-changelog-toggle
            aria-expanded="true"
            aria-controls="ap-changelog-body"
        >
            <span class="ap-changelog-toggle__label" data-ap-changelog-toggle-label>Свернуть</span>
            @include('partials.ap-icon', ['name' => 'chevron-right', 'size' => 'sm', 'class' => 'ap-changelog-toggle__icon'])
        </button>
    </div>

    <div class="ap-changelog-card__body" id="ap-changelog-body" data-ap-changelog-body>
        @if ($changelogEntries === [])
            <p class="ap-muted ap-small">Записей пока нет.</p>
        @else
            <div class="ap-changelog-scroll" tabindex="0" role="region" aria-label="Список обновлений портала">
                <ul class="ap-changelog-list">
                    @foreach ($changelogEntries as $entry)
                        @php
                            $items = $entry['items'] ?? [];
                            $itemsHtml = $entry['items_html'] ?? [];
                            $changelogBodyPayload = $items !== [] ? implode("\x1e", $items) : '';
                        @endphp
                        <li>
                            <button
                                type="button"
                                class="ap-changelog-list__item"
                                data-ap-changelog-open
                                data-ap-changelog-date="{{ $entry['date_label'] }}"
                                data-ap-changelog-date-short="{{ $entry['date_short'] }}"
                                data-ap-changelog-tag="{{ $entry['tag'] }}"
                                data-ap-changelog-tag-label="{{ $entry['tag_label'] }}"
                                data-ap-changelog-title="{{ $entry['title'] }}"
                                @if ($itemsHtml !== [])
                                    data-ap-changelog-items-html='@json($itemsHtml, JSON_UNESCAPED_UNICODE)'
                                @elseif ($changelogBodyPayload !== '')
                                    data-ap-changelog-body="{{ e($changelogBodyPayload) }}"
                                @endif
                                @if (! empty($entry['doc_url']))
                                    data-ap-changelog-doc-url="{{ $entry['doc_url'] }}"
                                    data-ap-changelog-doc-label="{{ $entry['doc_label'] ?? 'Документация' }}"
                                @endif
                                @if (! empty($entry['image_url']))
                                    data-ap-changelog-image-url="{{ $entry['image_url'] }}"
                                    data-ap-changelog-image-alt="{{ $entry['image_alt'] ?? $entry['title'] }}"
                                @endif
                                aria-label="Подробнее: {{ $entry['title'] }}"
                            >
                                <span class="ap-changelog-list__date">
                                    <time datetime="{{ $entry['date'] }}">{{ $entry['date_short'] }}</time>
                                </span>
                                <span class="ap-changelog-tag ap-changelog-tag--{{ $entry['tag'] }}">{{ $entry['tag_label'] }}</span>
                                <span class="ap-changelog-list__text">{!! $entry['summary_html'] ?? \App\Support\PortalChangelog::highlightQuotedHtml($entry['summary']) !!}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>

<div
    id="ap-changelog-modal"
    class="ap-modal ap-changelog-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ap-changelog-modal-title"
    aria-hidden="true"
    hidden
>
    <div class="ap-modal__backdrop" data-ap-changelog-modal-close tabindex="-1"></div>
    <div class="ap-modal__panel ap-changelog-modal__panel">
        <button type="button" class="ap-modal__close" data-ap-changelog-modal-close aria-label="Закрыть">&times;</button>
        <div class="ap-changelog-modal__meta">
            <time class="ap-changelog-modal__date" id="ap-changelog-modal-date" datetime=""></time>
            <span class="ap-changelog-tag ap-changelog-modal__tag" id="ap-changelog-modal-tag"></span>
        </div>
        <h2 class="ap-changelog-modal__title" id="ap-changelog-modal-title"></h2>
        <div class="ap-changelog-modal__body" id="ap-changelog-modal-body"></div>
        <div class="ap-changelog-modal__doc" id="ap-changelog-modal-doc" hidden></div>
        <div class="ap-changelog-modal__footer">
            <button type="button" class="ap-btn ap-btn--secondary ap-btn--sm" data-ap-changelog-modal-close>Закрыть</button>
        </div>
    </div>
</div>
