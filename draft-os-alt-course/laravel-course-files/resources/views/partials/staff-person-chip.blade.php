@php
    $staffId = isset($staffId) ? (int) $staffId : null;
    $name = trim((string) ($name ?? ''));
    $email = trim((string) ($email ?? ''));
    $initials = trim((string) ($initials ?? ''));
    $canLinkToProfile = ! empty($canLinkToProfile);
    $size = ($size ?? 'sm') === 'lg' ? 'lg' : 'sm';
    $label = $label ?? null;
    $hasPerson = $staffId > 0 && $name !== '' && $name !== '—';
    if ($initials === '' && ($name !== '' || $email !== '')) {
        $initials = \App\Support\LearnerDisplay::initials($email, $name !== '—' ? $name : '');
    }
@endphp

@if ($hasPerson)
    @php
        $chipClass = 'ap-staff-chip ap-staff-chip--'.$size.($canLinkToProfile ? ' ap-staff-chip--link' : '');
        $profileUrl = $canLinkToProfile ? route('admin.staff.show', ['staff' => $staffId]) : null;
    @endphp
    @if ($canLinkToProfile && $profileUrl)
        <a href="{{ $profileUrl }}" class="{{ $chipClass }}" title="{{ $email !== '' ? $email : $name }}">
            <span class="ap-staff-chip__avatar" aria-hidden="true">{{ $initials }}</span>
            <span class="ap-staff-chip__text">
                @if ($label)
                    <span class="ap-staff-chip__label">{{ $label }}</span>
                @endif
                <span class="ap-staff-chip__name">{{ $name }}</span>
            </span>
        </a>
    @else
        <span class="{{ $chipClass }}" title="{{ $email !== '' ? $email : $name }}">
            <span class="ap-staff-chip__avatar" aria-hidden="true">{{ $initials }}</span>
            <span class="ap-staff-chip__text">
                @if ($label)
                    <span class="ap-staff-chip__label">{{ $label }}</span>
                @endif
                <span class="ap-staff-chip__name">{{ $name }}</span>
            </span>
        </span>
    @endif
@else
    <span class="ap-staff-chip ap-staff-chip--empty ap-staff-chip--{{ $size }}">
        @if ($label)
            <span class="ap-staff-chip__label">{{ $label }}</span>
        @endif
        <span class="ap-muted">не указан</span>
    </span>
@endif
