@if (($portalStaffAccess ?? null)?->isPortalAdmin())
    <a class="{{ $class ?? 'nav-cmd-btn' }}" href="{{ route('admin.incidents.index') }}">Логи</a>
    <a class="{{ $class ?? 'nav-cmd-btn' }}" href="{{ route('admin.mail.index') }}">Почта</a>
@endif
