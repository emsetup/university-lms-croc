@extends('layouts.course')

@section('title', 'Админ: обучающиеся — все курсы')

@section('content')
    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['active' => 'learners_portal'])
        <h1 style="margin:0 0 0.35rem">Обучающиеся (все курсы)</h1>
        <p class="muted" style="margin:0;line-height:1.5">Сводка по программам: сколько участников и сколько завершили (сертификат).</p>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:separate;border-spacing:0">
                <thead>
                <tr>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Курс</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Участников</th>
                    <th style="text-align:left;padding:0.6rem 0.75rem;border-bottom:1px solid #e2e8f0">Завершили</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($courses as $c)
                    <tr>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9">
                            <div style="font-weight:700">
                                <a href="{{ route('admin.courses.enter', ['course' => (int) $c['id'], 'next' => 'learners']) }}"
                                   style="color:inherit;text-decoration:none">{{ $c['title'] }}</a>
                            </div>
                            <div class="muted small">slug: <code>{{ $c['slug'] }}</code></div>
                        </td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9"><strong>{{ (int) $c['enrolled'] }}</strong></td>
                        <td style="padding:0.6rem 0.75rem;border-bottom:1px solid #f1f5f9"><strong>{{ (int) $c['completed'] }}</strong></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

