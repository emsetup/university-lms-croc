@extends('layouts.admin')

@section('title', 'Статистика портала — Панель администратора')

@php
    /** @var array<string, mixed> $stats */
    $engagementPct = (int) ($stats['engagement_pct'] ?? 0);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/platform-stats.css') }}?v={{ @filemtime(public_path('css/platform-stats.css')) ?: 1 }}">
@endpush

@section('content')
    <div class="ap-page ap-fade ps-page">
        <header class="ps-hero">
            <p class="ps-hero__kicker">Администратор и аудитор портала</p>
            <h1 class="ps-hero__title">Статистика активности</h1>
            <p class="ps-hero__lead">
                Живая картина портала: кто реально наполняет курсы, кого назначили на лабы
                и у кого есть прогресс — без «мёртвых» ролей и пустых заходов.
            </p>
            <div class="ps-hero__meta">
                <span class="ps-pill">Обновлено {{ $stats['generated_at'] ?? '—' }}</span>
                <span class="ps-pill">Вовлечённость {{ $engagementPct }}%</span>
                <span class="ps-pill">Доработок {{ (int) ($stats['changelog']['total'] ?? 0) }}</span>
            </div>
            <div class="ps-hero__actions">
                <a class="ps-export-btn" href="{{ route('admin.platform-stats.pdf', ['auto' => 1]) }}">
                    @include('partials.ap-icon', ['name' => 'download', 'size' => 'sm'])
                    <span>Скачать PDF</span>
                </a>
                <a class="ps-export-btn ps-export-btn--ghost" href="{{ route('admin.platform-stats.pdf') }}" target="_blank" rel="noopener">
                    <span>Предпросмотр отчёта</span>
                </a>
            </div>
        </header>

        @include('admin.partials.platform-stats-body', [
            'stats' => $stats,
            'staticBars' => false,
        ])
    </div>
@endsection

@push('scripts')
<script>
(function () {
    function animateNum(el) {
        var target = parseInt(el.getAttribute('data-ps-target') || '0', 10) || 0;
        var start = 0;
        var dur = 700;
        var t0 = null;
        function frame(ts) {
            if (!t0) t0 = ts;
            var p = Math.min(1, (ts - t0) / dur);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = String(Math.round(start + (target - start) * eased));
            if (p < 1) requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }
    document.querySelectorAll('.js-ps-num').forEach(animateNum);
    requestAnimationFrame(function () {
        document.querySelectorAll('.js-ps-bar').forEach(function (el) {
            el.style.width = (el.getAttribute('data-ps-width') || '0') + '%';
        });
        document.querySelectorAll('.js-ps-spark').forEach(function (el) {
            el.style.height = (el.getAttribute('data-ps-height') || '4') + 'px';
        });
    });
})();
</script>
@endpush
