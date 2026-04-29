@extends('layouts.course')

@php
    use App\Support\DurationFormat;
    $modCount = \App\Services\CourseScoringService::moduleCount();
    $keyQ = request()->filled('key') ? '?key=' . urlencode((string) request('key')) : '';
    $modsDone = (int) ($summaryRow['modules_passed_count'] ?? 0);
    $gt = (int) ($summaryRow['grand_total'] ?? 0);
    $maxGt = (int) ($summaryRow['max_grand_total'] ?? 0);
    if ($maxGt < 1) {
        $maxGt = $modCount * \App\Services\CourseScoringService::MAX_POINTS_PER_MODULE + \App\Services\CourseScoringService::MAX_FINAL_LAB_POINTS;
    }
    $gtPct = min(100, (int) round(100 * $gt / $maxGt));
    $timeTotal = (int) ($summaryRow['time_tracked']['total'] ?? 0);
@endphp

@section('title', 'Обучающийся: '.$learner->email)

@section('content')
    <nav class="tl-breadcrumb" aria-label="Навигация">
        <a href="{{ route('teacher.course-report').$keyQ }}">Все обучающиеся</a>
        <span class="tl-bc-sep">/</span>
        <span class="tl-bc-current">{{ $learner->email }}</span>
        @if ($keyQ !== '')
            <span class="tl-bc-sep" aria-hidden="true">·</span>
            <a href="{{ route('admin.theory.index', ['key' => request('key')]) }}">Теория модулей</a>
        @endif
    </nav>

    <style>
        .tl-breadcrumb{font-size:0.9rem;margin:0 0 1rem;color:var(--muted,#5c6b76);display:flex;flex-wrap:wrap;gap:0.35rem;align-items:center}
        .tl-breadcrumb a{color:var(--accent,#0a7);text-decoration:none;font-weight:600}
        .tl-breadcrumb a:hover{text-decoration:underline}
        .tl-bc-sep{opacity:0.45}
        .tl-bc-current{font-weight:600;color:var(--text,#0f172a)}
        .tl-hero{
            border:1px solid var(--line,#dfe8e4);border-radius:18px;padding:1.15rem 1.25rem 1.25rem;
            background:linear-gradient(145deg,#f3faf6 0%,#fff 42%,#f8fbfc 100%);
            box-shadow:0 4px 20px rgba(15,42,30,.06);
            margin-bottom:0.75rem;
        }
        .tl-hero h1{margin:0 0 0.35rem;font-size:1.38rem;line-height:1.2;word-break:break-word}
        .tl-hero-lead{margin:0 0 1rem;font-size:0.92rem;color:#4a5f56;line-height:1.45;max-width:52rem}
        .tl-hero-board{display:flex;flex-wrap:wrap;align-items:flex-end;gap:1rem 1.35rem}
        .tl-ring{
            width:4.5rem;height:4.5rem;border-radius:50%;flex-shrink:0;
            display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;
            border:2px solid #c5e0d4;background:linear-gradient(165deg,#fff,#ecf8f2);
            box-shadow:0 2px 10px rgba(26,157,107,.08);
        }
        .tl-ring--course{border-color:#1a9d6b;background:linear-gradient(165deg,#e5f7ee,#fff)}
        .tl-ring__v{font-weight:800;font-size:0.95rem;color:#0f172a;line-height:1.1}
        .tl-ring__l{font-size:0.6rem;text-transform:uppercase;letter-spacing:0.06em;color:#5c6b76;margin-top:0.1rem;max-width:3.8rem;line-height:1.05}
        .tl-bar-wrap{flex:1;min-width:min(100%,220px);max-width:28rem}
        .tl-bar-label{display:flex;justify-content:space-between;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.05em;color:#5c6b76;margin-bottom:0.28rem;font-weight:700}
        .tl-bar{height:10px;border-radius:999px;background:#e4ece8;overflow:hidden;border:1px solid #d5e3dc}
        .tl-bar__fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#1a9d6b,#3ecf8e);transition:width .35s ease}
        .tl-chips{display:flex;flex-wrap:wrap;gap:0.45rem;margin-top:0.85rem}
        .tl-chip{display:inline-flex;align-items:center;gap:0.35rem;padding:0.3rem 0.65rem;border-radius:999px;background:#eef4f1;border:1px solid #d0e3d8;font-size:0.82rem}
        .tl-chip__k{font-size:0.68rem;text-transform:uppercase;letter-spacing:0.04em;color:#6b7c76}
        .tl-chip__v{font-weight:700;color:#0f172a}
        .tl-quicknav{
            position:sticky;top:0;z-index:18;display:flex;flex-wrap:wrap;align-items:center;gap:0.35rem;
            padding:0.5rem 0.65rem;margin:0 0 1rem;
            background:rgba(255,255,255,.94);backdrop-filter:blur(8px);
            border:1px solid var(--line,#dfe8e4);border-radius:12px;
            box-shadow:0 4px 16px rgba(15,23,42,.05);
        }
        .tl-quicknav__t{font-size:0.7rem;text-transform:uppercase;letter-spacing:0.08em;color:#6b7c76;font-weight:800;margin-right:0.25rem}
        .tl-quicknav__a{
            padding:0.28rem 0.62rem;border-radius:999px;font-size:0.8rem;font-weight:700;text-decoration:none;
            color:#0d4d36;background:#e2f2ea;border:1px solid #b8dcc8;
        }
        .tl-quicknav__a:hover{background:#d2eadc}
        .tl-mod-list{display:flex;flex-direction:column;gap:0.75rem}
        .tl-mod-row{
            scroll-margin-top:5.25rem;
            display:grid;grid-template-columns:1fr;gap:0.65rem 1rem;
            padding:0.95rem 1.05rem;border:1px solid var(--line,#e1e8ea);border-radius:16px;background:var(--card,#fff);
            box-shadow:0 1px 4px rgba(15,23,42,.04);
            transition:border-color .15s,box-shadow .15s;
        }
        @media(min-width:800px){
            .tl-mod-row{grid-template-columns:auto 1fr auto;align-items:center}
        }
        .tl-mod-row:hover{border-color:#c5ddd2;box-shadow:0 4px 18px rgba(15,42,30,.07)}
        .tl-mod-row--idle{opacity:0.88;background:linear-gradient(180deg,#fcfdfd,#f7faf9)}
        .tl-mod-id{display:flex;align-items:center;gap:0.5rem}
        .tl-mod-num-wrap{position:relative;display:inline-block;flex-shrink:0}
        .tl-mod-num{
            width:2.85rem;height:2.85rem;border-radius:14px;display:flex;align-items:center;justify-content:center;
            font-weight:800;font-size:1.05rem;color:#0f3d2c;background:linear-gradient(145deg,#dff5ea,#fff);
            border:1px solid #bfe3cf;
        }
        .tl-mod-letter{
            position:absolute;top:-0.15rem;right:-0.2rem;min-width:1.15rem;height:1.15rem;padding:0 0.28rem;border-radius:999px;
            display:flex;align-items:center;justify-content:center;font-size:0.62rem;font-weight:800;color:#475569;
            background:#eef2f6;border:1px solid #cbd5e1;text-transform:uppercase;letter-spacing:0.04em;line-height:1;
        }
        .tl-mod-title{margin:0;font-size:1rem;line-height:1.35;font-weight:600;color:#0f172a}
        .tl-mod-title a{color:inherit;text-decoration:none}
        .tl-mod-title a:hover{color:var(--accent,#0a7)}
        .tl-mini-rings{display:flex;flex-wrap:wrap;gap:0.45rem;align-items:center;justify-content:flex-end}
        .tl-mini{
            width:3rem;height:3rem;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;
            border:2px solid #cfe5d8;background:#fff;font-size:0.72rem;font-weight:800;color:#0f172a
        }
        .tl-mini span{font-size:0.55rem;font-weight:700;color:#5c6b76;text-transform:uppercase;margin-top:0.06rem}
        .tl-mini--mute{border-color:#e2e8ec;background:#f8fafb;color:#94a3b8}
        .tl-time-chip{padding:0.25rem 0.55rem;border-radius:999px;background:#f1f5f3;border:1px solid #dde8e3;font-size:0.78rem;font-weight:700;color:#334155;white-space:nowrap}
        .tl-cta{display:flex;align-items:center;justify-content:flex-end;gap:0.5rem;flex-wrap:wrap}
        .tl-btn{
            display:inline-flex;align-items:center;gap:0.35rem;padding:0.45rem 0.95rem;border-radius:10px;font-weight:700;font-size:0.86rem;
            text-decoration:none;background:#0f6b4a;color:#fff;border:1px solid #0c5a3f;
            box-shadow:0 2px 8px rgba(15,107,74,.2);
        }
        .tl-btn:hover{filter:brightness(1.06)}
        .tl-section-h{margin:0.25rem 0 0.65rem;font-size:1.02rem;font-weight:700;color:#1e293b}
    </style>

    <header class="tl-hero">
        <h1>{{ $learner->email }}</h1>
        <p class="tl-hero-lead">Выберите модуль, чтобы увидеть попытки по шагам и журнал сбросов. Ниже — список модулей с кругами по теории, практике и экзамену; полоска — доля набранных баллов за курс. Меню «М1…М{{ $modCount }}» — быстрый переход.</p>
        <div class="tl-hero-board">
            <div class="tl-ring" title="Модулей сдано по порогу">
                <span class="tl-ring__v">{{ $modsDone }}</span>
                <span class="tl-ring__l">из {{ $modCount }} сдано</span>
            </div>
            <div class="tl-ring tl-ring--course" title="Баллы за курс">
                <span class="tl-ring__v">{{ $gt }}</span>
                <span class="tl-ring__l">из {{ $maxGt }} баллов</span>
            </div>
            <div class="tl-ring" title="Учтённое время в материалах курса">
                <span class="tl-ring__v" style="font-size:0.78rem;line-height:1.15">{{ DurationFormat::fromSeconds($timeTotal) }}</span>
                <span class="tl-ring__l">в курсе</span>
            </div>
            <div class="tl-bar-wrap" aria-label="Прогресс по баллам за курс">
                <div class="tl-bar-label"><span>Заполнение курса</span><span>{{ $gtPct }}%</span></div>
                <div class="tl-bar"><div class="tl-bar__fill" style="width:{{ $gtPct }}%"></div></div>
            </div>
        </div>
        <div class="tl-chips" aria-label="Дополнительно">
            <span class="tl-chip"><span class="tl-chip__k">Итого курс</span><span class="tl-chip__v">{{ $gt }} / {{ $maxGt }}</span></span>
            @if (isset($summaryRow['grand_total_percent']))
                <span class="tl-chip"><span class="tl-chip__k">Доля</span><span class="tl-chip__v">{{ $summaryRow['grand_total_percent'] }}%</span></span>
            @endif
            @if (isset($summaryRow['module_points_percent']))
                <span class="tl-chip"><span class="tl-chip__k">Модули (баллы)</span><span class="tl-chip__v">{{ $summaryRow['module_points_percent'] }}%</span></span>
            @endif
        </div>
    </header>

    <nav class="tl-quicknav" id="tl-jump" aria-label="Быстрый переход к модулю">
        <span class="tl-quicknav__t">К модулю</span>
        @foreach ($modulePanels as $pn)
            <a class="tl-quicknav__a" href="#tl-mod-{{ $pn['module_id'] }}">М{{ $pn['module_id'] }}</a>
        @endforeach
    </nav>

    <h2 class="tl-section-h">Модули</h2>
    <div class="tl-mod-list">
        @foreach ($modulePanels as $pn)
            @php
                $r = $pn['report'];
                $pr = $pn['progress'];
                $pts = is_array($r) ? (int) ($r['points'] ?? 0) : 0;
                $tq = is_array($r) ? (int) ($r['theory_quiz_pct'] ?? 0) : 0;
                $skipPr = is_array($r) && ! empty($r['skip_practice']);
                $prc = is_array($r) && ! $skipPr ? (int) ($r['practice_pct'] ?? 0) : null;
                $ex = is_array($r) ? (int) ($r['exam_pct'] ?? 0) : 0;
                $sec = $pr
                    ? (int) ($pr->seconds_theory ?? 0) + (int) ($pr->seconds_theory_quiz ?? 0)
                        + (int) ($pr->seconds_practice ?? 0) + (int) ($pr->seconds_module_exam ?? 0)
                    : 0;
                $idle = $pts === 0 && $tq === 0 && ($skipPr || $prc === 0) && $ex === 0 && $sec === 0;
            @endphp
            <article class="tl-mod-row {{ $idle ? 'tl-mod-row--idle' : '' }}" id="tl-mod-{{ $pn['module_id'] }}">
                <div class="tl-mod-id">
                    <div class="tl-mod-num-wrap" aria-hidden="true">
                        <div class="tl-mod-num">{{ $pn['module_id'] }}</div>
                        @if ($pn['letter'] !== '')
                            <span class="tl-mod-letter" title="Слот в программе">{{ $pn['letter'] }}</span>
                        @endif
                    </div>
                </div>
                <div>
                    <h3 class="tl-mod-title">
                        <a href="{{ route('teacher.course-report.learner.module', ['learner' => $learner->id, 'module' => $pn['module_id']]).$keyQ }}">{{ $pn['title'] }}</a>
                    </h3>
                </div>
                <div class="tl-cta">
                    <span class="tl-time-chip" title="Время по модулю">{{ DurationFormat::fromSeconds($sec) }}</span>
                    <div class="tl-mini-rings" aria-label="Проценты по шагам и балл">
                        <div class="tl-mini {{ $tq ? '' : 'tl-mini--mute' }}" title="Тест по теории">{{ $tq }}%<span>теор.</span></div>
                        <div class="tl-mini {{ $skipPr || $prc ? '' : 'tl-mini--mute' }}" title="{{ $skipPr ? 'Практика не предусмотрена' : 'Практика' }}">@if ($skipPr)<span class="muted">—</span>@else{{ $prc }}%@endif<span>практ.</span></div>
                        <div class="tl-mini {{ $ex ? '' : 'tl-mini--mute' }}" title="Экзамен">{{ $ex }}%<span>экз.</span></div>
                        <div class="tl-mini tl-ring--course" style="width:3.15rem;height:3.15rem;border-width:2px" title="Балл за модуль">{{ $pts }}<span>балл</span></div>
                    </div>
                    <a class="tl-btn" href="{{ route('teacher.course-report.learner.module', ['learner' => $learner->id, 'module' => $pn['module_id']]).$keyQ }}">Открыть</a>
                </div>
            </article>
        @endforeach
    </div>

    <p class="muted" style="margin-top:1.25rem">
        <a href="{{ route('teacher.course-report').$keyQ }}">← К сводке по всем обучающимся</a>
        ·
        <a href="#tl-jump">К меню модулей</a>
    </p>
@endsection
