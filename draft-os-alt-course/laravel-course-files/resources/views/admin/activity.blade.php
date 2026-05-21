@extends('layouts.admin')

@section('title', 'События — Панель администратора')

@push('scripts')
    <script src="{{ asset('js/admin-activity-panel.js') }}" defer></script>
@endpush

@section('content')
    <div class="ap-page ap-fade">
        <h1 class="ap-page-title">Все события</h1>
        <p class="ap-page-lead">
            Действия обучающихся и сотрудников: курсы, сертификаты, заходы в админ-панель и заглушка обновления.
        </p>

        <p class="ap-muted ap-small" style="margin-bottom:1rem">
            <a class="ap-link-inline" href="{{ route('admin.panel') }}">
                @include('partials.ap-icon', ['name' => 'arrow-left', 'size' => 'sm'])
                К панели
            </a>
        </p>

        <section class="ap-card ap-dash-card ap-activity-page-card">
            @include('admin.partials.activity-panel', [
                'panelMode' => 'full',
                'activityFeedUrl' => $activityFeedUrl,
                'activityFilters' => $activityFilters,
                'activityKinds' => $activityKinds,
                'activityEmails' => $activityEmails,
                'initialItems' => [],
            ])
        </section>
    </div>
@endsection
