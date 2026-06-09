@extends('layouts.admin')

@section('title', 'Практика модуля: '.$courseModule->title)

@section('content')
    @php
        $rp = array_merge(\App\Support\AdminNavigation::adminCourseRouteParams(), ($adminKey ?? '') !== '' ? ['key' => $adminKey] : []);
    @endphp
    <div class="ap-narrow-page">
        <div class="admin-card">
            <p class="u-m0"><a class="btn btn-ghost btn-sm" href="{{ route('admin.course.module.sections', array_merge($rp, ['courseModule' => $courseModule->id])) }}">К разделам модуля</a></p>
            <h1 class="practice-page__title u-mt-1">Практика · {{ $courseModule->title }}</h1>
            <p class="ap-muted small u-m0 u-mt-1">
                Здесь выбирается Docker-образ для лабораторного стенда модуля. Если не выбрать — будет использован fallback из <span class="mono">config/practice_lab.php</span> по <span class="mono">content_source_index</span> (как у Alt).
            </p>

            <form method="post" action="{{ route('admin.course.module.practice.save', array_merge($rp, ['courseModule' => $courseModule->id])) }}" class="u-mt-1">
                @csrf

                <div class="practice-module-grid">
                    <div class="practice-module-grid__col">
                        <label class="form-label" for="practice_image_id">Docker-образ (из библиотеки)</label>
                        <select id="practice_image_id" class="form-select practice-select-wide" name="practice_image_id">
                            <option value="" @if (! old('practice_image_id', $setting->practice_image_id)) selected @endif>— не выбрано (fallback) —</option>
                            @foreach ($images as $img)
                                <option value="{{ (int) $img->id }}" @if ((int) old('practice_image_id', (int) ($setting->practice_image_id ?? 0)) === (int) $img->id) selected @endif>
                                    {{ $img->title }} ({{ $img->docker_tag }})
                                </option>
                            @endforeach
                        </select>
                        <div class="admin-table__meta u-mt-1">
                            <a class="btn btn-secondary btn-sm" href="{{ route('admin.practice.images.index', ['key' => $adminKey]) }}">Библиотека образов</a>
                        </div>
                    </div>
                    <div class="practice-module-grid__col">
                        <label class="form-label" for="daemon_image_key_override">Переопределить ключ модуля для daemon (опц.)</label>
                        <input id="daemon_image_key_override" class="form-input form-input--md" name="daemon_image_key_override" value="{{ old('daemon_image_key_override', $setting->daemon_image_key_override) }}" placeholder="например 1..10">
                        <p class="admin-table__meta u-mt-1 u-m0">Обычно не нужно. По умолчанию используется <span class="mono">content_source_index</span>.</p>
                    </div>
                </div>

                <div class="actions-row u-mt-1">
                    <button type="submit" class="btn btn-primary btn-sm">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
@endsection
