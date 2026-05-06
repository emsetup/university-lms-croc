@extends('layouts.course')

@section('title', 'Личный кабинет')

@section('content')
    <div class="card" style="max-width:1100px;margin:0 auto 1rem">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Личный кабинет</h1>
                <p class="muted" style="margin:0;line-height:1.5">
                    Аккаунт: <strong>{{ $learner->email }}</strong>. Здесь — все ваши курсы и сертификаты.
                </p>
            </div>
            <a class="btn btn-ghost" href="{{ route('portal') }}">К списку курсов</a>
        </div>
    </div>

    <div class="card" style="max-width:1100px;margin:0 auto">
        <h2 style="margin-top:0">Мои курсы</h2>
        <div class="module-grid" style="margin-top:0.85rem">
            @foreach ($rows as $r)
                @php
                    /** @var \App\Models\Course $c */
                    $c = $r['course'];
                    $started = (bool) ($r['started'] ?? false);
                    $pct = (int) ($r['progress_pct'] ?? 0);
                    $cert = (bool) ($r['certificate_available'] ?? false);
                @endphp
                <div class="module-card">
                    <div class="tag">Курс</div>
                    <div style="font-weight:800;font-size:1.05rem;line-height:1.25">{{ $c->title }}</div>
                    <div class="muted" style="font-size:0.92rem;line-height:1.45;margin-top:0.35rem">{{ $c->summary }}</div>

                    @if ($started)
                        <div style="margin-top:0.85rem">
                            <div class="muted small" style="margin:0 0 0.35rem">Прогресс: <strong>{{ $pct }}%</strong></div>
                            <div class="learner-track-summary__bar" aria-hidden="true" style="height:10px">
                                <div class="learner-track-summary__bar-fill" style="width: {{ min(100, max(0, $pct)) }}%"></div>
                            </div>
                            @if (!empty($r['started_at']))
                                <div class="muted small" style="margin-top:0.35rem">
                                    Начато: <strong>{{ optional($r['started_at'])->format('d.m.Y H:i') }}</strong>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="muted small" style="margin-top:0.85rem">Курс ещё не начат.</div>
                    @endif

                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.95rem;align-items:center">
                        <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                            @csrf
                            <button type="submit" class="btn btn-primary">{{ $started ? 'Продолжить' : 'Начать' }}</button>
                        </form>
                        @if ($cert)
                            <form method="post" action="{{ route('portal.enroll', ['course' => $c->id]) }}" style="margin:0">
                                @csrf
                                <input type="hidden" name="next" value="certificate">
                                <button type="submit" class="btn btn-ghost">Сертификат</button>
                            </form>
                        @else
                            <span class="btn btn-ghost" style="opacity:0.55;pointer-events:none" aria-disabled="true">Сертификат</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

