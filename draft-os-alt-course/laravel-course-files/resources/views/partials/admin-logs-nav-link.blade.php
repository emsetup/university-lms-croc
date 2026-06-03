@if (($portalStaffAccess ?? null)?->isPortalAdmin())
    <a class="{{ $class ?? 'nav-cmd-btn' }}" href="{{ route('admin.incidents.index') }}">Логи</a>
@endif
