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
        @php
            $quizColumns = is_array($quizColumns ?? null) ? $quizColumns : [];
        @endphp
        @if ($quizColumns !== [])
            <p class="admin-card__lead">Для каждого модуля доступны банки вопросов по включённым типам разделов.</p>
        @else
            <p class="admin-card__lead">В курсе нет разделов с тестами или экзаменами.</p>
        @endif

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
                                <th>Модуль</th>
                                <th>Название</th>
                                @foreach ($quizColumns as $col)
                                    <th>{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td class="mono">{{ $r['module_sequence'] ?? $r['module'] }}</td>
                                    <td>
                                        @if (! empty($r['label']))
                                            {{ $r['label'] }}
                                        @else
                                            Модуль {{ $r['module_sequence'] ?? $r['module'] }}
                                        @endif
                                        @if (($r['module'] ?? 0) !== ($r['module_sequence'] ?? $r['module']))
                                            <div class="admin-table__meta">пакет №{{ $r['module'] }}</div>
                                        @endif
                                    </td>
                                    @foreach ($quizColumns as $col)
                                        @php
                                            $count = 0;
                                            $moduleId = (int) ($r['course_module_id'] ?? 0);
                                            $slot = isset($col['slot']) ? (int) $col['slot'] : -1;
                                            if ($moduleId > 0 && $slot >= 0) {
                                                $cmCol = \App\Models\CourseModule::query()->find($moduleId);
                                                $sec = $cmCol
                                                    ? \App\Support\AdminCourseContentInspector::sectionAtSlot($cmCol, $slot)
                                                    : null;
                                                $colType = (string) ($col['type'] ?? '');
                                                $expectedKind = (string) ($col['kind'] ?? '');
                                                if ($sec && (
                                                    ($expectedKind === 'theory_quiz' && $colType === \App\Models\CourseSection::TYPE_QUIZ)
                                                    || ($expectedKind === 'module_exam' && $colType === \App\Models\CourseSection::TYPE_EXAM)
                                                )) {
                                                    $count = count(\App\Support\AdminCourseContentInspector::questionsForSection($sec));
                                                }
                                            } elseif ((int) ($col['section_id'] ?? 0) > 0
                                                && (int) ($col['course_module_id'] ?? 0) === $moduleId) {
                                                $sec = \App\Models\CourseSection::query()->find((int) $col['section_id']);
                                                $count = $sec ? count(\App\Support\AdminCourseContentInspector::questionsForSection($sec)) : 0;
                                            } else {
                                                $count = ($col['kind'] ?? '') === 'module_exam'
                                                    ? (int) ($r['module_exam_count'] ?? 0)
                                                    : (int) ($r['theory_quiz_count'] ?? 0);
                                            }
                                        @endphp
                                        <td>
                                            <div class="admin-table__actions">
                                                @php
                                                    $moduleId = (int) ($r['course_module_id'] ?? 0);
                                                    $canEditCol = empty($isReadOnly)
                                                        && $moduleId > 0
                                                        && ($portalStaffAccess ?? null)?->canEditModuleQuiz($moduleId, (string) ($col['kind'] ?? ''));
                                                @endphp
                                                @if ($canEditCol)
                                                    <a class="icon-btn" href="{{ route('admin.quiz.edit.module', array_merge($tp, ['module' => $r['module'], 'kind' => $col['kind']])) }}" data-tip="Редактировать" aria-label="Редактировать {{ $col['label'] }}">
                                                        @include('partials.ap-icon', ['name' => 'pencil', 'size' => 'md'])
                                                    </a>
                                                @endif
                                                <span class="badge badge-gray">{{ $count }} вопр.</span>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        @endif
    </div>
@endsection
