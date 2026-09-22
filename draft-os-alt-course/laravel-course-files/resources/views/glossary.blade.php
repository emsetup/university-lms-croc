@extends('layouts.course')

@section('title', 'Словарь курса')

@section('content')
    <div class="page-container glossary-page">
        <a class="back-link" href="{{ route('course.dashboard') }}">
            @include('partials.ap-icon', ['name' => 'arrow-left'])
            <span>К курсу</span>
        </a>

        <header class="glossary-page__head">
            <p class="glossary-page__kicker">Словарь</p>
            <h1 class="glossary-page__title">{{ $course->title }}</h1>
            <p class="glossary-page__lead muted">Аббревиатуры и термины курса. В тексте теории и в вопросах они подсвечены — наведите курсор, чтобы увидеть пояснение.</p>
        </header>

        @if ($terms->isEmpty())
            <div class="card glossary-empty">
                <p class="muted" style="margin:0">Пока в словаре нет записей.</p>
            </div>
        @else
            <div class="glossary-list">
                @foreach ($terms as $term)
                    <article class="glossary-card" id="glossary-term-{{ (int) $term->id }}">
                        <div class="glossary-card__head">
                            <h2 class="glossary-card__term">{{ $term->term }}</h2>
                            @if (trim((string) ($term->abbreviation ?? '')) !== '')
                                <span class="glossary-card__abbr">{{ $term->abbreviation }}</span>
                            @endif
                        </div>
                        <p class="glossary-card__def">{{ $term->definition }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
