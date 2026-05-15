@php
    /** @var \App\Services\PortalStaffAccess|null $portalStaffAccess */
    $psa = $portalStaffAccess ?? null;
    $showSettings = $psa && ($psa->canManagePortalSettings() || $psa->canImpersonateLearners());
    $maintenanceOn = $showSettings && $psa->canManagePortalSettings()
        ? \App\Services\PortalMaintenance::isEnabled()
        : null;
@endphp
@if ($showSettings)
    <div class="admin-settings-menu" data-admin-settings-menu>
        <button
            type="button"
            class="nav-cmd-btn admin-settings-menu__trigger"
            title="Настройки портала"
            aria-haspopup="true"
            aria-expanded="false"
            data-admin-settings-trigger
        >
            @include('partials.ap-icon', ['name' => 'cog', 'size' => 'sm'])
            <span>Настройки</span>
        </button>
        <div class="admin-settings-menu__panel" role="menu" hidden data-admin-settings-panel>
            <a class="admin-settings-menu__item" href="{{ route('admin.settings') }}" role="menuitem">
                Все настройки
            </a>
            @if ($psa->canManagePortalSettings())
                <div class="admin-settings-menu__hint" role="none">
                    Заглушка:
                    @if ($maintenanceOn)
                        <span class="ap-badge ap-badge--draft" style="font-size:0.75rem">вкл</span>
                    @else
                        <span class="ap-badge ap-badge--published" style="font-size:0.75rem">выкл</span>
                    @endif
                </div>
            @endif
            @if ($psa->canImpersonateLearners())
                <a class="admin-settings-menu__item" href="{{ route('admin.settings') }}#prosmotr" role="menuitem">
                    Смотреть от лица пользователя…
                </a>
            @endif
        </div>
    </div>
@endif
