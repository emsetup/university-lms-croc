@extends('layouts.course')

@section('title', 'Админ: вопросы тестов')

@section('content')
    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'quiz'])
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Вопросы тестов и экзаменов</h1>
                <p class="muted" style="margin:0;max-width:60rem;line-height:1.5">
                    Редактирование выполняется через JSON (безопасно для PHP). Здесь можно менять порядок вопросов/ответов, тип (один/несколько/сопоставление) и содержимое.
                    Для выбранного в панели курса набора модулей список строится из БД; колонка «пакет» — номер файлов вопросов (как в <code>config/course.php</code>).
                </p>
            </div>
            <a class="btn btn-ghost" href="{{ route('admin.theory.index', ['key' => $adminKey]) }}">К содержимому курса</a>
        </div>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        <h2 style="margin-top:0">Финальная лабораторная</h2>
        <p class="muted" style="margin:0 0 0.75rem">Небольшой список вопросов, используемый на финальной странице.</p>
        <a class="btn btn-primary" href="{{ route('admin.quiz.edit.final', ['key' => $adminKey]) }}">Редактировать вопросы финальной страницы</a>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto">
        <h2 style="margin-top:0">Модули</h2>
        <div class="muted small" style="margin:0 0 0.75rem">Для каждого модуля доступны два банка: тест по теории и итоговый экзамен.</div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:separate;border-spacing:0">
                <thead>
                <tr>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Пакет / модуль курса</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Тест по теории</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Итоговый экзамен</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9;vertical-align:top">
                            <strong>{{ $r['module'] }}</strong>
                            @if (! empty($r['label']))
                                <div class="muted small" style="margin-top:0.25rem;line-height:1.35">{{ $r['label'] }}</div>
                            @endif
                        </td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9">
                            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                                <a class="btn btn-ghost" href="{{ route('admin.quiz.edit.module', ['module' => $r['module'], 'kind' => 'theory_quiz', 'key' => $adminKey]) }}">Редактировать</a>
                                <span class="muted small">{{ (int) $r['theory_quiz_count'] }} вопр.</span>
                            </div>
                        </td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9">
                            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center">
                                <a class="btn btn-ghost" href="{{ route('admin.quiz.edit.module', ['module' => $r['module'], 'kind' => 'module_exam', 'key' => $adminKey]) }}">Редактировать</a>
                                <span class="muted small">{{ (int) $r['module_exam_count'] }} вопр.</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

