@extends('layouts.course')

@section('title', 'Админ курса — сертификаты')

@section('content')
    <div class="card" style="max-width: 1200px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['active' => 'certificates'])

        <h1 style="margin-top: 0">Выданные сертификаты</h1>
        <p class="muted" style="margin-top: 0">
            Список всех сертификатов, для которых зафиксированы ФИО и номер.
        </p>

        <div style="overflow:auto; border:1px solid var(--line, #dfe8e4); border-radius:10px;">
            <table style="width:100%; border-collapse:collapse; min-width:900px;">
                <thead>
                <tr style="background:#f8faf9; border-bottom:1px solid #dfe8e4;">
                    <th style="text-align:left; padding:0.65rem 0.75rem;">№ сертификата</th>
                    <th style="text-align:left; padding:0.65rem 0.75rem;">ФИО</th>
                    <th style="text-align:left; padding:0.65rem 0.75rem;">Email</th>
                    <th style="text-align:left; padding:0.65rem 0.75rem;">Дата выдачи</th>
                    <th style="text-align:right; padding:0.65rem 0.75rem;">Лучший балл финалки</th>
                    <th style="text-align:center; padding:0.65rem 0.75rem;">Статус</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($items as $row)
                    <tr style="border-bottom:1px solid #edf2ef;">
                        <td style="padding:0.6rem 0.75rem; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;">{{ $row->certificate_serial }}</td>
                        <td style="padding:0.6rem 0.75rem; font-weight:600;">{{ $row->certificate_full_name }}</td>
                        <td style="padding:0.6rem 0.75rem;">{{ $row->learner->email ?? '—' }}</td>
                        <td style="padding:0.6rem 0.75rem;">{{ optional($row->certificate_issued_at)->format('d.m.Y H:i') ?? '—' }}</td>
                        <td style="padding:0.6rem 0.75rem; text-align:right;">{{ (int) $row->best_score }}%</td>
                        <td style="padding:0.6rem 0.75rem; text-align:center;">
                            @if ($row->passed)
                                <a
                                    href="{{ route('admin.certificates.show', ['result' => $row->id]) }}"
                                    class="pill"
                                    style="display:inline-block;text-decoration:none;background:#e8f7ee; color:#166534; border-color:#bbdfc8;"
                                >Выдан</a>
                            @else
                                <span class="pill" style="background:#fff4e8; color:#9a3412; border-color:#f5d7b5;">Черновик</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted" style="padding:0.9rem 0.75rem;">Пока нет выданных сертификатов.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($items, 'links'))
            <div style="margin-top: 1rem;">
                {{ $items->links() }}
            </div>
        @endif
    </div>
@endsection
