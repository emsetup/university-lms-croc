@extends('layouts.course')

@section('title', $pageTitle . ' — Документация')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ @filemtime(public_path('css/docs.css')) ?: 1 }}">
@endpush

@section('content')
    <div class="docs-page">
        <nav class="docs-breadcrumb" aria-label="Навигация">
            <a href="{{ route('documentation.index') }}">Документация</a>
            <span class="docs-breadcrumb__sep">/</span>
            <span>{{ $article['section'] }}</span>
            <span class="docs-breadcrumb__sep">/</span>
            <span aria-current="page">{{ $article['title'] }}</span>
        </nav>

        <header class="docs-banner docs-banner--article">
            <p class="docs-banner__eyebrow">{{ $article['section'] }}</p>
            <h1>{{ $article['title'] }}</h1>
            @if (! empty($article['summary']))
                <p>{{ $article['summary'] }}</p>
            @elseif (! empty($sectionIntro))
                <p>{{ $sectionIntro }}</p>
            @endif
            <div class="docs-banner__meta">
                <span class="docs-badge">Справка портала</span>
            </div>
        </header>

        <div class="docs-layout docs-layout--article">
            @include('docs.partials.sidebar', [
                'grouped' => $grouped,
                'currentSlug' => $article['slug'],
                'docsSearchIndex' => $docsSearchIndex ?? [],
            ])

            <div class="docs-main">
                <article class="docs-article">
                    @if (empty($article['summary']))
                        <div class="docs-article__head">
                            <h1 class="docs-article__title">{{ $article['title'] }}</h1>
                        </div>
                    @endif
                    <div class="docs-article__body">
                        <div class="docs-prose">
                            {!! $html !!}
                        </div>

                        <nav class="docs-pager" aria-label="Соседние статьи">
                            @if (! empty($navPrev))
                                <a class="docs-pager__link" href="{{ route('documentation.show', ['slug' => $navPrev['slug']]) }}">
                                    <span class="docs-pager__dir">← Назад</span>
                                    <span class="docs-pager__title">{{ $navPrev['title'] }}</span>
                                </a>
                            @else
                                <span></span>
                            @endif
                            @if (! empty($navNext))
                                <a class="docs-pager__link docs-pager__link--next" href="{{ route('documentation.show', ['slug' => $navNext['slug']]) }}">
                                    <span class="docs-pager__dir">Далее →</span>
                                    <span class="docs-pager__title">{{ $navNext['title'] }}</span>
                                </a>
                            @endif
                        </nav>
                    </div>
                </article>
            </div>
        </div>
    </div>

    @if ($headings !== [])
        <aside class="docs-aside-toc" aria-label="На этой странице">
            <nav class="docs-toc" data-docs-toc>
                <p class="docs-toc__label">На странице</p>
                @foreach ($headings as $h)
                    <a href="#{{ $h['id'] }}" @class(['docs-toc__h3' => $h['level'] === 3])>{{ $h['text'] }}</a>
                @endforeach
            </nav>
        </aside>
    @endif
    <div id="docs-lightbox" class="docs-lightbox" role="dialog" aria-modal="true" aria-label="Просмотр скриншота" aria-hidden="true">
        <div class="docs-lightbox__backdrop" data-docs-lightbox-close tabindex="-1"></div>
        <div class="docs-lightbox__panel">
            <button type="button" class="docs-lightbox__close" data-docs-lightbox-close aria-label="Закрыть">×</button>
            <img class="docs-lightbox__img" src="" alt="">
            <p class="docs-lightbox__caption"></p>
        </div>
    </div>
    <script src="{{ asset('js/docs-lightbox.js') }}" defer></script>
    @include('docs.partials.search-boot', ['docsSearchIndex' => $docsSearchIndex ?? []])
    @if ($headings !== [])
        <script>
            (function () {
                var links = document.querySelectorAll('[data-docs-toc] a[href^="#"]');
                if (!links.length || !('IntersectionObserver' in window)) return;
                var obs = new IntersectionObserver(function (entries) {
                    entries.forEach(function (e) {
                        if (!e.isIntersecting) return;
                        links.forEach(function (a) { a.classList.remove('is-active'); });
                        var id = e.target.getAttribute('id');
                        var active = document.querySelector('[data-docs-toc] a[href="#' + id + '"]');
                        if (active) active.classList.add('is-active');
                    });
                }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });
                links.forEach(function (a) {
                    var el = document.getElementById((a.getAttribute('href') || '').slice(1));
                    if (el) obs.observe(el);
                });
            })();
        </script>
    @endif
@endsection
