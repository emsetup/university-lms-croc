@php
    use App\Support\StaffRoleGuide;

    $staffRoleGuide = StaffRoleGuide::roles();
    $staffRoleColumns = StaffRoleGuide::capabilityColumns();
    $staffRoleOrder = StaffRoleGuide::roleOrder();
@endphp

<div class="ap-staff-role-guide" id="ap-staff-role-guide" aria-live="polite">
    <div class="ap-staff-role-guide__detail" id="ap-staff-role-detail" hidden>
        <p class="ap-staff-role-guide__detail-kicker">Выбранная роль</p>
        <div class="ap-staff-role-guide__detail-head">
            <span class="ap-staff-badge ap-staff-role-guide__badge" id="ap-staff-role-detail-badge"></span>
            <p class="ap-staff-role-guide__detail-admin" id="ap-staff-role-detail-admin"></p>
        </div>
        <p class="ap-staff-role-guide__detail-summary" id="ap-staff-role-detail-summary"></p>
        <ul class="ap-staff-role-guide__perm-list" id="ap-staff-role-detail-perms"></ul>
    </div>

    <details class="ap-staff-role-guide__compare">
        <summary class="ap-staff-role-guide__compare-summary">Сравнить все роли</summary>
        <div class="ap-staff-role-guide__table-wrap">
            <table class="ap-staff-role-guide__table">
                <thead>
                <tr>
                    <th scope="col" class="ap-staff-role-guide__th-role">Роль</th>
                    @foreach ($staffRoleColumns as $col)
                        <th scope="col">{{ $col['title'] }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($staffRoleOrder as $roleKey)
                    @php
                        $role = $staffRoleGuide[$roleKey] ?? null;
                        if ($role === null) {
                            continue;
                        }
                    @endphp
                    <tr data-ap-staff-role-row="{{ $roleKey }}">
                        <th scope="row" class="ap-staff-role-guide__row-label">
                            <span class="ap-staff-badge ap-staff-badge--{{ $role['badge'] }}">{{ $role['label'] }}</span>
                        </th>
                        @foreach ($staffRoleColumns as $col)
                            @php
                                $cell = $role['capabilities'][$col['key']] ?? ['level' => 'no', 'label' => '—'];
                            @endphp
                            <td>
                                <span class="ap-staff-access ap-staff-access--{{ $cell['level'] }}" title="{{ $cell['label'] }}">
                                    <span class="ap-staff-access__icon" aria-hidden="true"></span>
                                    <span class="ap-staff-access__text">{{ $cell['label'] }}</span>
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>

<script type="application/json" id="ap-staff-role-guide-data">@json($staffRoleGuide)</script>
