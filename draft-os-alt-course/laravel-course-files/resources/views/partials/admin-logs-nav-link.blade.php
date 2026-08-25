@if (($portalStaffAccess ?? null)?->canViewPlatformStats())
    <a class="{{ $class ?? 'nav-cmd-btn' }} @if(request()->routeIs('admin.platform-stats')) active @endif"
       href="{{ route('admin.platform-stats') }}">Статистика</a>
@endif
@if (($portalStaffAccess ?? null)?->canViewPortalLogs())
    <a class="{{ $class ?? 'nav-cmd-btn' }}" href="{{ route('admin.incidents.index') }}">Логи</a>
    <a class="{{ $class ?? 'nav-cmd-btn' }}" href="{{ route('admin.mail.index') }}">Почта</a>
@endif
