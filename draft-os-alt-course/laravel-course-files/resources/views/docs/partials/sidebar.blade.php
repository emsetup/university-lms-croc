@php
    $currentSlug = $currentSlug ?? null;
    $sectionIntro = $sectionIntro ?? config('documentation.section_intro', []);
@endphp
<aside class="docs-sidebar" aria-label="Оглавление документации">
    <div class="docs-sidebar__head">
        <p class="docs-sidebar__title">Содержание</p>
        <a class="docs-sidebar__home" href="{{ route('documentation.index') }}">Главная</a>
    </div>
    @include('docs.partials.search', ['docsSearchVariant' => 'sidebar', 'docsSearchIndex' => $docsSearchIndex ?? []])
    @forelse ($grouped as $section => $articles)
        <div class="docs-sidebar__section" data-docs-section>
            <div class="docs-sidebar__section-label">{{ $section }}</div>
            @if (! empty($sectionIntro[$section]))
                <p class="docs-sidebar__section-hint">{{ $sectionIntro[$section] }}</p>
            @endif
            @foreach ($articles as $item)
                <a
                    class="docs-nav-link @if ($currentSlug === $item['slug']) docs-nav-link--active @endif"
                    href="{{ route('documentation.show', ['slug' => $item['slug']]) }}"
                    data-docs-slug="{{ $item['slug'] }}"
                >{{ $item['title'] }}</a>
            @endforeach
        </div>
    @empty
        <p class="muted" style="margin:0.5rem;font-size:0.88rem">Статьи пока не опубликованы.</p>
    @endforelse
</aside>
