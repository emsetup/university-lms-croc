@extends('layouts.admin')

@section('title', 'Практика модуля: '.$courseModule->title)

@section('content')
    @include('partials.admin-instructor-nav', ['active' => 'settings'])

    <div class="card">
        <p class="muted">
            <a href="{{ route('admin.course.module.sections', ['courseModule' => $courseModule->id]) }}">← К разделам модуля</a>
        </p>
        <h1 style="margin-top:0">Практика · {{ $courseModule->title }}</h1>
        <p class="muted" style="margin-top:0.25rem">
            Здесь выбирается Docker-образ для лабораторного стенда модуля. Если не выбрать — будет использован fallback из <code>config/practice_lab.php</code> по <code>content_source_index</code> (как у Alt).
        </p>

        <form method="post" action="{{ route('admin.course.module.practice.save', ['courseModule' => $courseModule->id]) }}" style="margin-top:1rem">
            @csrf

            <div style="display:grid;grid-template-columns:repeat(12,1fr);gap:0.75rem;align-items:start">
                <div style="grid-column:span 7">
                    <label class="muted small">Docker-образ (из библиотеки)</label>
                    <select class="input" name="practice_image_id">
                        <option value="" @selected(! old('practice_image_id', $setting->practice_image_id))>— не выбрано (fallback) —</option>
                        @foreach ($images as $img)
                            <option value="{{ (int) $img->id }}" @selected((int) old('practice_image_id', (int) ($setting->practice_image_id ?? 0)) === (int) $img->id)>
                                {{ $img->title }} ({{ $img->docker_tag }})
                            </option>
                        @endforeach
                    </select>
                    <div class="muted small" style="margin-top:0.35rem">
                        <a href="{{ route('admin.practice.images.index') }}">Открыть библиотеку образов</a>
                    </div>
                </div>
                <div style="grid-column:span 5">
                    <label class="muted small">Переопределить ключ модуля для daemon (опц.)</label>
                    <input class="input" name="daemon_image_key_override" value="{{ old('daemon_image_key_override', $setting->daemon_image_key_override) }}" placeholder="например 1..10">
                    <div class="muted small" style="margin-top:0.35rem">
                        Обычно не нужно. По умолчанию используется <code>content_source_index</code>.
                    </div>
                </div>
            </div>

            <div style="margin-top:1rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;justify-content:space-between">
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
@endsection

