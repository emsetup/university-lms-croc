@extends('layouts.course')

@section('title', 'Админ: курсы')

@section('content')
    <div class="card" style="max-width:1200px;margin:0 auto 1rem">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'courses'])
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Курсы портала</h1>
                <p class="muted" style="margin:0;max-width:62rem;line-height:1.5">
                    Здесь видны курсы, доступные в портале. Сейчас содержимое модулей, тестов и практики относится к курсу
                    <strong>«Особенности ОС «Альт»»</strong>.
                </p>
            </div>
            <a class="btn btn-ghost" href="{{ route('admin.panel', ['key' => $adminKey]) }}">К панели</a>
        </div>
    </div>

    <div class="card" style="max-width:1200px;margin:0 auto">
        <h2 style="margin-top:0">Список</h2>
        <div class="module-grid" style="margin-top:0.85rem">
            @foreach ($courses as $c)
                <div class="module-card">
                    <div class="tag">Курс</div>
                    <div style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c['title'] }}</div>
                    <div class="muted small" style="margin-top:0.25rem">slug: <code>{{ $c['slug'] }}</code></div>
                    <div class="muted" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c['summary'] }}</div>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.85rem;align-items:center">
                        <span class="muted small">Зачислено: <strong>{{ (int) $c['enrolled'] }}</strong></span>
                        <span class="muted small">Начали: <strong>{{ (int) $c['started'] }}</strong></span>
                        @if (!empty($c['is_published']))
                            <span class="muted small">статус: <strong>опубликован</strong></span>
                        @else
                            <span class="muted small">статус: <strong>черновик</strong></span>
                        @endif
                    </div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.85rem">
                        <form method="post" action="{{ route('admin.courses.select', ['course' => (int) $c['id'], 'key' => $adminKey]) }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="next" value="content">
                            <button type="submit" class="btn btn-primary">Содержимое</button>
                        </form>
                        <form method="post" action="{{ route('admin.courses.select', ['course' => (int) $c['id'], 'key' => $adminKey]) }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="next" value="quiz">
                            <button type="submit" class="btn btn-ghost">Вопросы</button>
                        </form>
                        <form method="post" action="{{ route('admin.courses.select', ['course' => (int) $c['id'], 'key' => $adminKey]) }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="next" value="certificates">
                            <button type="submit" class="btn btn-ghost">Сертификаты</button>
                        </form>
                        <form method="post" action="{{ route('admin.courses.select', ['course' => (int) $c['id'], 'key' => $adminKey]) }}" style="margin:0">
                            @csrf
                            <input type="hidden" name="next" value="learners">
                            <button type="submit" class="btn btn-ghost">Обучающиеся</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

