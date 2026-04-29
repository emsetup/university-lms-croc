@extends('layouts.course')

@section('title', 'Админ: содержимое курса')

@section('content')
    <div class="card" style="max-width: 1100px; margin: 0 auto">
        <h1 style="margin-top: 0">Содержимое курса (админ)</h1>
        <p class="muted small">Доступ по <code>?key=</code> — тот же ключ, что для отчёта преподавателя (<code>TEACHER_REPORT_TOKEN</code>), либо отдельный <code>COURSE_ADMIN_TOKEN</code> в <code>.env</code>, если задан.</p>
        <p class="muted small">Теория в виде <code>@snippet:module_N_theory.md</code> можно править в редакторе. Тесты и практика здесь только для <strong>просмотра</strong> (редактирование — в <code>config/course.php</code> и файлах в <code>config/snippets/</code>).</p>
        <p style="margin: 0.5rem 0 1rem">
            <a class="btn btn-primary" href="{{ route('admin.theory.zip', ['key' => $adminKey]) }}">Скачать все module_*_theory.md (ZIP)</a>
        </p>
        @if (session('err'))
            <p class="quiz-modal-warn" style="padding:0.65rem 0.85rem;border-radius:6px;margin:0 0 1rem">{{ session('err') }}</p>
        @endif
        @if (session('ok'))
            <p style="padding:0.65rem 0.85rem;border-radius:6px;margin:0 0 1rem;background:rgba(22,101,52,0.1)">{{ session('ok') }}</p>
        @endif
        <div style="overflow-x:auto">
            <table class="teacher-report-table" style="width:100%;min-width:1040px">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Модуль</th>
                        <th>Теория</th>
                        <th>Тест по теории</th>
                        <th>Практика</th>
                        <th>Итоговый тест</th>
                        <th>Docker-практика</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="teacher-report-nowrap">{{ $r['module'] }}</td>
                            <td>{{ $r['title'] }}</td>
                            <td style="font-size:0.85rem;vertical-align:top">
                                <span class="muted" style="word-break:break-all">{{ \Illuminate\Support\Str::limit($r['ref'], 48) }}</span>
                                <div class="muted" style="margin-top:0.25rem;font-size:0.8rem">
                                    Текст теории: <strong>{{ number_format($r['theory_chars'], 0, ',', ' ') }}</strong> симв.
                                </div>
                                <div style="margin-top:0.35rem">
                                    @if ($r['editable'])
                                        <a class="btn btn-ghost" style="padding:0.25rem 0.5rem;font-size:0.85rem" href="{{ route('admin.theory.edit', ['module' => $r['module'], 'key' => $adminKey]) }}">Редактор MD</a>
                                    @else
                                        <span class="muted">встроено в конфиг</span>
                                    @endif
                                </div>
                            </td>
                            <td class="teacher-report-nowrap" style="vertical-align:top;font-size:0.9rem">
                                @if ($r['theory_quiz_count'] > 0)
                                    {{ $r['theory_quiz_count'] }} вопр.
                                    @if ($r['theory_quiz_match'] > 0)
                                        <span class="muted">({{ $r['theory_quiz_match'] }} сопост.)</span>
                                    @endif
                                    <div style="margin-top:0.35rem">
                                        <a class="btn btn-ghost" style="padding:0.25rem 0.5rem;font-size:0.85rem" href="{{ route('admin.theory.preview-theory-quiz', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</a>
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="vertical-align:top;font-size:0.85rem">
                                @if ($r['has_practice'])
                                    <span class="muted">{{ $r['practice_summary'] }}</span>
                                    <div style="margin-top:0.35rem">
                                        <a class="btn btn-ghost" style="padding:0.25rem 0.5rem;font-size:0.85rem" href="{{ route('admin.theory.preview-practice', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</a>
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="teacher-report-nowrap" style="vertical-align:top;font-size:0.9rem">
                                @if ($r['exam_count'] > 0)
                                    {{ $r['exam_count'] }} вопр.
                                    <span class="muted">· {{ $r['exam_time_min'] }} мин</span>
                                    @if ($r['exam_match'] > 0)
                                        <span class="muted">({{ $r['exam_match'] }} сопост.)</span>
                                    @endif
                                    <div style="margin-top:0.35rem">
                                        <a class="btn btn-ghost" style="padding:0.25rem 0.5rem;font-size:0.85rem" href="{{ route('admin.theory.preview-module-exam', ['module' => $r['module'], 'key' => $adminKey]) }}">Просмотр</a>
                                    </div>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="vertical-align:top;font-size:0.82rem;max-width:14rem">
                                @if ($r['practice_lab_docker_image'])
                                    <code style="word-break:break-all;font-size:0.8rem">{{ $r['practice_lab_docker_image'] }}</code>
                                @else
                                    <span class="muted">Для этого модуля не задан Docker-образ в <code>config/practice_lab.php</code>.</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="muted small" style="margin-top: 1rem">Прямой адрес списка: <code>/adm/kurs-teoriya?key=…</code> (историческое имя пути сохранено).</p>
    </div>
@endsection
