@extends('layouts.admin')

@section('title', 'Опросы — '.$course->title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/course-surveys-admin.css') }}">
@endpush

@section('content')
    @php
        $tp = ['adminCourse' => $course->slug];
        $selectedMeta = null;
        foreach ($surveys as $sv) {
            if ((int) $sv['section_id'] === $selectedId) {
                $selectedMeta = $sv;
                break;
            }
        }
    @endphp

    <div class="ap-surveys-split">
        <aside class="ap-surveys-split__left" aria-label="Опросы курса">
            <div class="ap-surveys-split__left-head">
                <h1 class="ap-surveys-split__title">Опросы</h1>
                <p class="ap-muted ap-surveys-split__lead">{{ $course->title }}</p>
                <p class="ap-muted small ap-surveys-split__hint">Выберите опрос слева — справа сводка ответов и выгрузка в Excel.</p>
            </div>
            <ul class="ap-surveys-list" role="list">
                @php $lastModSeq = null; @endphp
                @foreach ($surveys as $sv)
                    @if ($lastModSeq !== (int) $sv['module_sequence'])
                        @php $lastModSeq = (int) $sv['module_sequence']; @endphp
                        <li class="ap-surveys-list__group" aria-hidden="true">
                            Модуль {{ $lastModSeq }} · {{ $sv['module_title'] }}
                        </li>
                    @endif
                    <li>
                        <a
                            class="ap-surveys-list__item @if((int)$sv['section_id'] === $selectedId) is-active @endif"
                            href="{{ route('admin.course.surveys', array_merge($tp, ['section' => $sv['section_id']])) }}"
                        >
                            <span class="ap-surveys-list__item-title">{{ $sv['section_title'] }}</span>
                            <span class="ap-surveys-list__item-meta">
                                {{ (int) $sv['response_count'] }} отв.
                                @if ($sv['anonymous'])
                                    · анонимный
                                @endif
                                @if (! empty($sv['quick_link_url']))
                                    · <span class="ap-badge ap-badge--published">быстрая ссылка</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </aside>

        <div class="ap-surveys-split__right">
            @if (! $selectedSection || ! $selectedMeta)
                <div class="ap-surveys-empty">
                    <p class="ap-muted">В курсе пока нет опросов для просмотра.</p>
                </div>
            @else
                <header class="ap-surveys-detail__head card">
                    <div class="ap-surveys-detail__head-main">
                        <p class="ap-surveys-detail__kicker">
                            Модуль {{ (int) $selectedMeta['module_sequence'] }} · {{ $selectedMeta['module_title'] }}
                        </p>
                        <h2 class="ap-surveys-detail__title">{{ $selectedSection->title }}</h2>
                        <p class="ap-surveys-detail__meta ap-muted">
                            {{ (int) $selectedMeta['question_count'] }} вопросов
                            · {{ (int) $selectedMeta['response_count'] }} ответов
                            @if ($selectedMeta['anonymous'])
                                · <span class="ap-badge ap-badge--draft">Анонимный</span>
                            @else
                                · с привязкой к обучающимся
                            @endif
                        </p>
                    </div>
                    <div class="ap-surveys-detail__actions">
                        @if (! empty($selectedMeta['quick_link_url']))
                            <div class="ap-surveys-quick-link">
                                <label class="ap-muted small" for="ap-surveys-quick-link-url">Быстрая ссылка</label>
                                <div class="ap-surveys-quick-link__row">
                                    <input type="text" class="ap-modal__input" id="ap-surveys-quick-link-url" readonly value="{{ $selectedMeta['quick_link_url'] }}">
                                    <button type="button" class="btn btn-ghost btn-sm" id="ap-surveys-quick-link-copy">Копировать</button>
                                </div>
                            </div>
                        @endif
                        <div class="ap-surveys-detail__export">
                            <a class="btn btn-ghost btn-sm"
                               href="{{ route('admin.course.module.section.participants', array_merge($tp, ['courseModule' => $selectedMeta['module_id'], 'section' => $selectedSection->id])) }}">
                                Участники
                            </a>
                            <a class="btn btn-primary btn-sm"
                               href="{{ route('admin.course.surveys.export.wide', array_merge($tp, ['section' => $selectedSection->id])) }}">
                                Excel: сводная
                            </a>
                            <a class="btn btn-ghost btn-sm"
                               href="{{ route('admin.course.surveys.export.long', array_merge($tp, ['section' => $selectedSection->id])) }}">
                                Excel: по строкам
                            </a>
                        </div>
                    </div>
                </header>

                @if ((int) $selectedMeta['response_count'] === 0)
                    <div class="card ap-surveys-empty-block">
                        <p class="ap-muted" style="margin:0">Пока нет отправленных ответов на этот опрос.</p>
                    </div>
                @else
                    <section class="card ap-surveys-panel">
                        <div class="ap-surveys-panel__tabs" role="tablist" aria-label="Формат таблицы">
                            <button type="button" class="ap-surveys-panel__tab is-active" data-ap-survey-tab="long" role="tab" aria-selected="true">
                                По ответам
                            </button>
                            <button type="button" class="ap-surveys-panel__tab" data-ap-survey-tab="wide" role="tab" aria-selected="false">
                                Сводная
                            </button>
                        </div>

                        <div class="ap-surveys-panel__pane is-active" data-ap-survey-pane="long">
                            <p class="ap-muted small ap-surveys-panel__lead">Каждая строка — один ответ на вопрос: удобно читать и анализировать текстовые ответы.</p>
                            <div class="ap-table-wrap ap-surveys-table-wrap">
                                <table class="ap-table ap-surveys-table ap-surveys-table--long">
                                    <thead>
                                        <tr>
                                            @foreach ($longTable['columns'] as $col)
                                                <th>{{ $col }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($longTable['rows'] as $row)
                                            <tr>
                                                @foreach ($longTable['columns'] as $col)
                                                    <td @if($col === 'Вопрос') class="ap-surveys-cell-q" @elseif($col === 'Ответ') class="ap-surveys-cell-a" @endif>{{ $row[$col] ?? '' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ap-surveys-panel__pane" data-ap-survey-pane="wide" hidden>
                            <p class="ap-muted small ap-surveys-panel__lead">Одна строка — один респондент, столбцы — вопросы. Удобно для фильтров в Excel.</p>
                            <div class="ap-table-wrap ap-surveys-table-wrap">
                                <table class="ap-table ap-surveys-table ap-surveys-table--wide">
                                    <thead>
                                        <tr>
                                            @foreach ($wideTable['columns'] as $col)
                                                <th>{{ $col }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($wideTable['rows'] as $row)
                                            <tr>
                                                @foreach ($wideTable['columns'] as $col)
                                                    <td>{{ $row[$col] ?? '' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @endif
            @endif
        </div>
    </div>

    <script src="{{ asset('js/course-surveys-admin.js') }}" defer></script>
@endsection
