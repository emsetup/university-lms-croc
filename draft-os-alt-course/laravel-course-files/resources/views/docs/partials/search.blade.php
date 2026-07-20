@php
    $docsSearchIndex = $docsSearchIndex ?? [];
    $docsSearchVariant = $docsSearchVariant ?? 'sidebar'; // sidebar | hero
@endphp
<div
    class="docs-search docs-search--{{ $docsSearchVariant }}"
    data-docs-search
    data-docs-search-variant="{{ $docsSearchVariant }}"
>
    <label class="docs-search__label" for="docs-search-input-{{ $docsSearchVariant }}">
        @if ($docsSearchVariant === 'hero')
            Быстрый поиск по справке
        @else
            Поиск
        @endif
    </label>
    <div class="docs-search__field">
        <span class="docs-search__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        </span>
        <input
            id="docs-search-input-{{ $docsSearchVariant }}"
            type="search"
            class="docs-search__input"
            data-docs-search-input
            placeholder="{{ $docsSearchVariant === 'hero' ? 'Например: быстрая ссылка, опрос, плашки…' : 'Быстрая ссылка, опрос…' }}"
            autocomplete="off"
            spellcheck="false"
            enterkeyhint="search"
            aria-controls="docs-search-results-{{ $docsSearchVariant }}"
            aria-describedby="docs-search-hint-{{ $docsSearchVariant }}"
        >
        <button type="button" class="docs-search__clear" data-docs-search-clear hidden title="Очистить" aria-label="Очистить поиск">×</button>
        <kbd class="docs-search__kbd" title="Клавиша / или Ctrl+K">/</kbd>
    </div>
    <p id="docs-search-hint-{{ $docsSearchVariant }}" class="docs-search__hint">
        Ищем по названиям и тексту статей. Клавиша <kbd>/</kbd> или <kbd>Ctrl</kbd>+<kbd>K</kbd>.
    </p>
    <p class="docs-search__status" data-docs-search-status aria-live="polite"></p>
    <div
        id="docs-search-results-{{ $docsSearchVariant }}"
        class="docs-search__results"
        data-docs-search-results
        hidden
        role="region"
        aria-label="Результаты поиска"
    ></div>
    <p class="docs-search__empty" data-docs-search-empty hidden>Ничего не найдено. Попробуйте другие слова (например «быстрая ссылка», «опрос», «соавторы»).</p>
</div>
