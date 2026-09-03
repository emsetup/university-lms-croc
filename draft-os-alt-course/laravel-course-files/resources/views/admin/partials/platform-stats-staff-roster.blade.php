@php
    /** @var list<array<string, mixed>> $staffRoster */
    $staffRoster = $staffRoster ?? [];
@endphp

<section class="ps-card ps-export-section ps-staff-roster">
    <div class="ps-card__head">
        <h2 class="ps-card__title">Сотрудники с доступом к порталу</h2>
        <p class="ps-card__sub">всего {{ count($staffRoster) }} учётных записей · роли и группы на дату выгрузки</p>
    </div>

    @if ($staffRoster === [])
        <p class="ap-muted">Список сотрудников пуст.</p>
    @else
        <table class="ps-staff-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>ФИО</th>
                    <th>Роль</th>
                    <th>Группы</th>
                    <th>Курсы</th>
                    <th>Последний вход</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($staffRoster as $row)
                    <tr>
                        <td class="ps-staff-table__email">{{ $row['email'] !== '' ? $row['email'] : '—' }}</td>
                        <td>{{ ($row['name'] ?? '') !== '' ? $row['name'] : '—' }}</td>
                        <td>
                            <span class="ps-staff-badge ps-staff-badge--{{ $row['role'] ?? 'default' }}">
                                {{ $row['role_label'] ?? '—' }}
                            </span>
                            @if (! empty($row['access_comment']))
                                <div class="ps-staff-table__comment">{{ $row['access_comment'] }}</div>
                            @endif
                        </td>
                        <td>
                            @if (empty($row['groups']))
                                <span class="ps-staff-table__muted">—</span>
                            @else
                                <ul class="ps-staff-table__groups">
                                    @foreach ($row['groups'] as $grp)
                                        <li>
                                            <strong>{{ $grp['name'] }}</strong>
                                            <span class="ps-staff-table__muted">· {{ $grp['role_label'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td>
                            @if (empty($row['courses']))
                                <span class="ps-staff-table__muted">—</span>
                            @else
                                <ul class="ps-staff-table__courses">
                                    @foreach ($row['courses'] as $title)
                                        <li>{{ $title }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="ps-staff-table__login">{{ $row['last_login'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
