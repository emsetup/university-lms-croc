@extends('layouts.course')

@section('title', $pageTitle)

@push('head')
    <link rel="stylesheet" href="{{ asset('css/docs.css') }}?v={{ @filemtime(public_path('css/docs.css')) ?: 1 }}">
@endpush

@section('content')
    <div class="docs-page">
        <header class="docs-banner">
            <p class="docs-banner__eyebrow">КРОК · Образовательный портал</p>
            <h1>{{ $pageTitle }}</h1>
            @if (! empty($pageSubtitle))
                <p>{{ $pageSubtitle }}</p>
            @endif
        </header>

        <div class="docs-layout">
            @include('docs.partials.sidebar', [
                'grouped' => $grouped,
                'currentSlug' => null,
                'sectionIntro' => $sectionIntro ?? [],
            ])

            <div class="docs-main">
                <div class="docs-start-panel">
                    <p>
                        Здесь собраны пошаговые инструкции: не только «что за кнопка», но и <strong>зачем нужен раздел</strong>,
                        <strong>в какой последовательности</strong> действовать и <strong>какой результат</strong> вы получите.
                        Набор статей зависит от вашей роли — администраторы видят блоки про настройку курсов и портала.
                    </p>
                    @if ($firstSlug)
                        <a class="btn btn-primary" href="{{ route('documentation.show', ['slug' => $firstSlug]) }}">Начать с введения</a>
                    @endif
                    <ol class="docs-steps" aria-label="Рекомендуемый порядок для обучающегося">
                        <li>1. Вход</li>
                        <li>2. Каталог</li>
                        <li>3. Курс</li>
                        <li>4. Модуль</li>
                        <li>5. Сертификат</li>
                    </ol>
                </div>

                <div class="docs-index-grid">
                    @foreach ($grouped as $section => $articles)
                        <section class="docs-index-section-card">
                            <h2>{{ $section }}</h2>
                            @if (! empty(($sectionIntro ?? [])[$section]))
                                <p class="docs-index-section-desc">{{ $sectionIntro[$section] }}</p>
                            @endif
                            <div class="docs-article-cards">
                                @foreach ($articles as $item)
                                    <a class="docs-article-card" href="{{ route('documentation.show', ['slug' => $item['slug']]) }}">
                                        <p class="docs-article-card__title">{{ $item['title'] }}</p>
                                        @if (! empty($item['summary']))
                                            <p class="docs-article-card__summary">{{ $item['summary'] }}</p>
                                        @endif
                                        <span class="docs-article-card__arrow" aria-hidden="true">→</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
