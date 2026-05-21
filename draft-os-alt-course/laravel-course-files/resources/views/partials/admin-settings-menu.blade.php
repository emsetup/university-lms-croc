@php
    /** @var \App\Services\PortalStaffAccess|null $portalStaffAccess */
    $psa = $portalStaffAccess ?? null;
    $viewerPsa = \App\Services\PortalStaffAccess::fromLearnerId((int) session('learner_id', 0));
    $showSettings = $viewerPsa && ($viewerPsa->canManagePortalSettings() || $viewerPsa->canImpersonateLearners() || $viewerPsa->canPreviewStaffAdmin());
    $maintenanceOn = $showSettings && $viewerPsa->canManagePortalSettings()
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
            @if ($viewerPsa->canManagePortalSettings())
                <div class="admin-settings-menu__hint" role="none">
                    Заглушка:
                    @if ($maintenanceOn)
                        <span class="ap-badge ap-badge--draft" style="font-size:0.75rem">вкл</span>
                    @else
                        <span class="ap-badge ap-badge--published" style="font-size:0.75rem">выкл</span>
                    @endif
                </div>
            @endif
            @if ($viewerPsa->canImpersonateLearners())
                <a class="admin-settings-menu__item" href="{{ route('admin.settings') }}#prosmotr" role="menuitem">
                    Смотреть портал от лица обучающегося…
                </a>
            @endif
            @if ($viewerPsa->canPreviewStaffAdmin())
                <a class="admin-settings-menu__item" href="{{ route('admin.settings') }}#prosmotr-sotrudnik" role="menuitem">
                    Смотреть админку от лица сотрудника…
                </a>
            @endif
        </div>
    </div>
@endif
