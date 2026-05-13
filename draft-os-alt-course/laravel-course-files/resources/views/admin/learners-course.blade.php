@extends('layouts.course')

@section('title', 'Админ: обучающиеся — курс')

@section('content')
    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['active' => 'learners_course'])
        <h1 style="margin:0 0 0.35rem">Обучающиеся курса</h1>
        <p class="muted" style="margin:0;line-height:1.5">Курс: <strong>{{ $course->title }}</strong>.</p>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:separate;border-spacing:0">
                <thead>
                <tr>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Email</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Начато</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Последняя активность</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Прогресс</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9"><strong>{{ $r['email'] }}</strong></td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9">{{ optional($r['started_at'])->format('d.m.Y H:i') ?: '—' }}</td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9">{{ optional($r['last_seen_at'])->format('d.m.Y H:i') ?: '—' }}</td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9"><strong>{{ (int) $r['progress_pct'] }}%</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

