@extends('layouts.admin')

@section('title', 'События — Панель администратора')

@section('content')
    <div class="ap-page ap-fade">
        <h1 class="ap-page-title">Все события</h1>
        <p class="ap-page-lead">
            Последние действия обучающихся по данным записей на курсы и сертификатов.
        </p>

        <p class="ap-muted ap-small" style="margin-bottom:1rem">
            <a class="ap-link-inline" href="{{ route('admin.panel') }}">
                @include('partials.ap-icon', ['name' => 'arrow-left', 'size' => 'sm'])
                К панели
            </a>
        </p>

        <section class="ap-card ap-dash-card">
            @if (($dashActivity ?? collect())->isEmpty())
                <p class="ap-muted">Пока нет событий.</p>
            @else
                <ul class="ap-activity-feed ap-activity-feed--wide" aria-label="События">
                    @foreach ($dashActivity as $ev)
                        <li class="ap-activity-feed__item">
                            <span class="ap-activity-feed__dot @if(!empty($ev['active_today'])) ap-activity-feed__dot--live @endif"
                                  aria-hidden="true"></span>
                            <div class="ap-activity-feed__body">
                                <p class="ap-activity-feed__text">
                                    <span class="ap-activity-feed__email">{{ $ev['email'] !== '' ? $ev['email'] : '—' }}</span>
                                    — {{ $ev['text'] }}
                                </p>
                                <time class="ap-activity-feed__time" datetime="{{ $ev['at']->toIso8601String() }}">
                                    {{ $ev['at']->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                                </time>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
