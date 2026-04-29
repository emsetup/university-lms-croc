@extends('layouts.course')

@section('title', 'Итоги обучения')

@section('content')
    <div class="cert-hero">
        <div class="tag" style="margin-bottom:0.75rem">Итоговая страница</div>
        <h1 style="margin:0 0 0.5rem">Курс "Особенности ОС Альт"</h1>
        <p class="muted" style="margin:0">Корпоративная почта</p>
        <div style="font-size:1.1rem;font-weight:700;margin:0.35rem 0 1rem">{{ $learner->email }}</div>
        <div class="cert-score">{{ $grand }}</div>
        <p class="muted" style="margin:0.35rem 0 0">суммарные баллы (модули + финальная лаба)</p>
    </div>

    <div class="card" style="margin-top:1.25rem">
        <h2 style="margin-top:0">Разбивка</h2>
        <p class="muted">Модули: <strong>{{ $modulePoints }}</strong> из {{ $modulePointsMax }}. Финальная лаба: <strong>{{ $finalPoints }}</strong> из 100. Попыток финальной: <strong>{{ $final->attempts }}</strong>.</p>
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Где были сложности</h2>
        @php
            $labels = [
                'theory' => 'теория',
                'theory_quiz' => 'тест по теории',
                'practice' => 'практика',
                'module_exam' => 'итоговый тест модуля',
            ];
        @endphp
        <ul class="muted" style="padding-left:1.1rem">
            @forelse ($moduleReport as $row)
                @php
                    $flags = $row['difficulties'] ?? [];
                    $active = array_filter($flags);
                @endphp
                @if (count($active))
                    @php $meta = config('course.modules.'.$row['module_id']); @endphp
                    <li style="margin:0.35rem 0">
                        <strong>{{ $meta['letter'] }}</strong> - {{ $meta['title'] }}:
                        @foreach ($active as $k => $_)
                            <span class="pill">{{ $labels[$k] ?? $k }}</span>
                        @endforeach
                    </li>
                @endif
            @empty
            @endforelse
        </ul>
        @php
            $any = false;
            foreach ($moduleReport as $row) {
                if (count(array_filter($row['difficulties'] ?? []))) {
                    $any = true;
                    break;
                }
            }
        @endphp
        @if (! $any)
            <p class="muted">Отметок о сложностях нет - или вы не отмечали этапы в модулях.</p>
        @endif
    </div>

    <div class="card" style="margin-top:1rem">
        <h2 style="margin-top:0">Справка по баллам</h2>
        <p class="muted" style="margin:0">Модули: до 100 баллов каждый (учитываются попытки тестов по теории и итогового теста). Финальная лаба: до 100 баллов с учетом числа попыток. Итог - сумма.</p>
    </div>
@endsection
