@extends('layouts.course')

@section('title', 'Сотрудники портала')

@section('content')
    <div class="card" style="max-width: 960px; margin: 0 auto">
        @include('partials.admin-instructor-nav', ['active' => 'staff'])

        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
            <div>
                <h1 style="margin:0 0 0.35rem">Администраторы и роли</h1>
                <p class="muted" style="margin:0;max-width:42rem;line-height:1.5">
                    Доступ к разделу <code>/adm</code> только у перечисленных здесь учётных записей (после входа через SSO).
                </p>
            </div>
            <a class="btn btn-primary" href="{{ route('admin.staff.create') }}">Добавить</a>
        </div>

        <div style="overflow:auto;margin-top:1.25rem">
            <table style="width:100%;border-collapse:collapse;font-size:0.92rem">
                <thead>
                    <tr style="text-align:left;border-bottom:1px solid var(--line,#e5e7eb)">
                        <th style="padding:0.45rem 0.5rem">Email</th>
                        <th style="padding:0.45rem 0.5rem">Роль</th>
                        <th style="padding:0.45rem 0.5rem">Курсы</th>
                        <th style="padding:0.45rem 0.5rem;width:8rem"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $row)
                        @php
                            $roleLabel = match ($row->role) {
                                \App\Models\PortalStaff::ROLE_PORTAL_ADMIN => 'Администратор портала',
                                \App\Models\PortalStaff::ROLE_COURSE_MODERATOR => 'Модератор курсов',
                                \App\Models\PortalStaff::ROLE_INSTRUCTOR => 'Инструктор',
                                \App\Models\PortalStaff::ROLE_COURSE_TESTER => 'Тестировщик курса',
                                default => $row->role,
                            };
                        @endphp
                        <tr style="border-bottom:1px solid #f1f5f9;vertical-align:top">
                            <td style="padding:0.5rem">{{ $row->learner?->email ?? '—' }}</td>
                            <td style="padding:0.5rem">{{ $roleLabel }}</td>
                            <td style="padding:0.5rem">
                                @if ($row->courses->isEmpty())
                                    <span class="muted">—</span>
                                @else
                                    <ul style="margin:0;padding-left:1.1rem">
                                        @foreach ($row->courses as $c)
                                            <li>{{ $c->title }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </td>
                            <td style="padding:0.5rem">
                                <a class="btn btn-ghost" href="{{ route('admin.staff.edit', ['staff' => $row->id]) }}">Изменить</a>
                                <form method="post" action="{{ route('admin.staff.destroy', ['staff' => $row->id]) }}" style="margin:0.35rem 0 0" onsubmit="return confirm('Удалить сотрудника из списка?');">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost" style="color:#b91c1c">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($items->isEmpty())
                <p class="muted" style="margin:0.75rem 0 0">Пока никого не добавили.</p>
            @endif
        </div>
    </div>
@endsection
