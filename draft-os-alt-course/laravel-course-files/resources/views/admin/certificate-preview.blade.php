@extends('layouts.course')

@section('title', 'Админ курса — просмотр сертификата')

@section('content')
    <div class="card" style="max-width: 1200px; margin: 0 auto 1rem;">
        @include('partials.admin-instructor-nav', ['navKey' => $adminKey, 'active' => 'certificates'])
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Просмотр сертификата</h1>
                <p class="muted" style="margin:0">
                    {{ $row->certificate_full_name ?: 'ФИО не указано' }} — {{ $row->learner->email ?? '—' }}
                </p>
            </div>
            <a class="btn btn-ghost" href="{{ route('admin.certificates', ['key' => $adminKey]) }}">Назад к реестру</a>
        </div>
    </div>

    <div style="max-width:1240px;margin:0 auto;background:#fff">
        <div style="position:relative;width:100%;min-height:877px;box-sizing:border-box;padding:64px 72px;border:14px solid #0f6f64;background:linear-gradient(160deg,#f7fffb 0%,#ffffff 58%,#edf8f5 100%);font-family:Manrope,Arial,sans-serif;color:#0f172a;">
            <div style="position:absolute;inset:22px;border:2px solid #93c5bd;pointer-events:none"></div>
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                    <img src="{{ asset('croc-wordmark.svg') }}" alt="КРОК" style="height:38px;display:block">
                    <div style="margin-top:12px;font-size:13px;letter-spacing:0.18em;text-transform:uppercase;color:#0f766e;font-weight:700">Образовательный сертификат</div>
                </div>
                <div style="text-align:right;font-size:14px;line-height:1.55">
                    <div><strong>№ {{ $row->certificate_serial ?: '—' }}</strong></div>
                    <div>Дата выдачи: {{ optional($row->certificate_issued_at)->format('d.m.Y') ?: '—' }}</div>
                </div>
            </div>

            <div style="margin-top:92px;text-align:center">
                <div style="font-size:20px;color:#0f766e;letter-spacing:0.12em;text-transform:uppercase;font-weight:700">Настоящий сертификат подтверждает, что</div>
                <div style="margin-top:34px;font-size:52px;line-height:1.08;font-weight:800;color:#134e4a">{{ $row->certificate_full_name ?: 'Фамилия Имя Отчество' }}</div>
                <div style="margin-top:34px;font-size:24px;line-height:1.45;max-width:900px;margin-left:auto;margin-right:auto">
                    успешно завершил(а) курс
                    <strong>"Особенности ОС Альт"</strong>
                    и подтвердил(а) практические навыки администрирования.
                </div>
            </div>

            <div style="position:absolute;left:72px;right:72px;bottom:74px;display:flex;justify-content:space-between;align-items:flex-end;gap:24px">
                <div style="font-size:15px;line-height:1.5;color:#334155">
                    Лучший балл финальной лабораторной: <strong>{{ (int) $row->best_score }}/100</strong><br>
                    Статус: <strong>{{ $row->passed ? 'Выдан' : 'Черновик' }}</strong><br>
                    Платформа: учебный курс КРОК
                </div>
                <div style="text-align:right">
                    <div style="font-size:14px;color:#334155;margin-bottom:8px">Руководитель программы</div>
                    <div style="width:260px;border-top:1px solid #94a3b8;padding-top:8px;font-size:14px;color:#0f172a">КРОК, учебный центр</div>
                </div>
            </div>
        </div>
    </div>
@endsection
