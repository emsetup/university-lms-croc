@extends('layouts.admin')

@section('title', 'Сертификаты')

@section('content')
    <div class="ap-page ap-cert-page">
        <p class="ap-cert-page__subtitle">Реестр выданных сертификатов</p>

        @if ($items->isEmpty())
            <div class="empty-state" role="status">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/>
                    <circle cx="12" cy="8" r="6"/>
                </svg>
                <h3>Сертификатов пока нет</h3>
                <p>Сертификат выдаётся после успешного прохождения финальной лаборатории</p>
            </div>
        @else
            <div class="admin-card admin-card--flush u-mt-1">
                <div class="ap-cert-toolbar">
                    <label class="visually-hidden" for="ap-cert-filter-q">Поиск по таблице сертификатов</label>
                    <input id="ap-cert-filter-q" type="search" class="form-input form-input--md" placeholder="Поиск по ФИО или email..." autocomplete="off">
                </div>
                <div class="admin-table-wrap">
                    <table id="ap-cert-table" class="admin-table ap-cert-as-admin-table">
                        <thead>
                        <tr>
                            <th>№ сертификата</th>
                            <th>ФИО</th>
                            <th>Email</th>
                            <th>Дата выдачи</th>
                            <th class="ap-cert-table__score-head">Лучший балл</th>
                            <th class="ap-cert-table__status">Статус</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $row)
                            @php
                                $issued = $row->certificate_issued_at;
                                $issuedLabel = $issued
                                    ? $issued->locale('ru')->translatedFormat('j M Y')
                                    : '—';
                                $email = (string) ($row->learner->email ?? '');
                                $name = (string) ($row->certificate_full_name ?? '');
                                $filterHaystack = mb_strtolower(trim($name.' '.$email), 'UTF-8');
                                $scorePct = (int) $row->best_score;
                            @endphp
                            <tr data-cert-filter="{{ $filterHaystack }}">
                                <td class="ap-cert-table__mono">{{ $row->certificate_serial }}</td>
                                <td class="ap-cert-table__name">{{ $row->certificate_full_name }}</td>
                                <td class="ap-cert-table__email">{{ $row->learner->email ?? '—' }}</td>
                                <td>{{ $issuedLabel }}</td>
                                <td class="ap-cert-score @if ($scorePct === 100) ap-cert-score--full @endif">{{ $scorePct }}%</td>
                                <td class="ap-cert-table__status">
                                    @if ($row->passed)
                                        <a class="badge badge-green"
                                           href="{{ route('admin.certificates.show', array_merge($ap ?? [], ['result' => $row->id])) }}">Выдан</a>
                                    @else
                                        <span class="ap-badge ap-badge--cert-draft">Черновик</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if (method_exists($items, 'links'))
                <div class="ap-cert-page__pager">
                    {{ $items->links() }}
                </div>
            @endif

            <script>
                (function () {
                    var input = document.getElementById('ap-cert-filter-q');
                    var table = document.getElementById('ap-cert-table');
                    if (!input || !table) return;
                    var rows = table.querySelectorAll('tbody tr[data-cert-filter]');
                    input.addEventListener('input', function () {
                        var q = (input.value || '').trim().toLowerCase();
                        for (var i = 0; i < rows.length; i++) {
                            var tr = rows[i];
                            var hay = tr.getAttribute('data-cert-filter') || '';
                            tr.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
                        }
                    });
                })();
            </script>
        @endif
    </div>
@endsection
