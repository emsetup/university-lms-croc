@extends('layouts.admin')

@section('title', 'Админ: вопросы тестов')

@section('content')
    @php
        $__showFinalQuizIndex = true;
        if (($selectedCourse ?? null) && \Illuminate\Support\Facades\Schema::hasColumn('courses', 'final_lab_enabled')) {
            $__showFinalQuizIndex = (bool) $selectedCourse->final_lab_enabled;
        }
        $tp = $ap ?? \App\Support\AdminNavigation::adminCourseRouteParams();
    @endphp

    @if ($__showFinalQuizIndex)
        <div class="admin-card ap-wide-page">
            <h2 class="admin-card__title">Финальная лабораторная</h2>
            <p class="admin-card__lead">Небольшой список вопросов, используемый на финальной странице.</p>
            @if (! empty($isReadOnly))
                <p class="ap-muted small u-m0">Режим просмотра: редактирование недоступно.</p>
            @else
                <a class="btn btn-primary" href="{{ route('admin.quiz.edit.final', $tp) }}">Редактировать вопросы финальной страницы</a>
            @endif
            <div class="u-mt-1">
                <a class="btn btn-secondary" href="{{ route('admin.theory.index', $tp) }}">К содержимому курса</a>
            </div>
        </div>
    @else
        <div class="admin-card ap-wide-page">
            <p class="admin-card__lead u-m0">Финальная лаборатория отключена для курса.</p>
            <a class="btn btn-secondary" href="{{ route('admin.theory.index', $tp) }}">К содержимому курса</a>
        </div>
    @endif

    <div class="admin-card ap-wide-page">
        <h2 class="admin-card__title">Модули</h2>
        <p class="admin-card__lead">Для каждого модуля доступны два банка: тест по теории и итоговый экзамен.</p>

        @if (empty($rows) || count($rows) === 0)
            <div class="empty-state" role="status">
                <p class="empty-state__title">Вопросы не привязаны к курсу</p>
                <p class="empty-state__text">
                    Для этого курса пока нет модулей в базе данных, поэтому список банков вопросов не сформирован.
                    Сначала создайте модули в разделе «Модули».
                </p>
                <div class="u-mt-1">
                    <a class="btn btn-primary btn-sm" href="{{ route('admin.course.settings', \App\Support\AdminNavigation::adminCourseRouteParams()) }}">Открыть модули</a>
                </div>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Пакет / модуль</th>
                                <th>Тест по теории</th>
                                <th>Итоговый экзамен</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>
                                        <strong>{{ $r['module'] }}</strong>
                                        @if (! empty($r['label']))
                                            <div class="admin-table__meta">{{ $r['label'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="admin-table__actions">
                                            <a class="icon-btn" href="{{ route('admin.quiz.edit.module', ['module' => $r['module'], 'kind' => 'theory_quiz']) }}" data-tip="Редактировать" aria-label="Редактировать тест по теории">
                                                @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                                            </a>
                                            <span class="badge badge-gray">{{ (int) $r['theory_quiz_count'] }} вопр.</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="admin-table__actions">
                                            <a class="icon-btn" href="{{ route('admin.quiz.edit.module', ['module' => $r['module'], 'kind' => 'module_exam']) }}" data-tip="Редактировать" aria-label="Редактировать итоговый экзамен">
                                                @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                                            </a>
                                            <span class="badge badge-gray">{{ (int) $r['module_exam_count'] }} вопр.</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        @endif
    </div>
@endsection
