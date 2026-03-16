<aside class="sidebar" id="app-sidebar" data-collapsed="false">
    @php
        $roleCodes = $user->roles->pluck('code')->values()->all();
        $canManageMenu = in_array('super_admin', $roleCodes, true) || in_array('support_admin', $roleCodes, true);
        $enabledMenuItems = \App\Models\MenuItem::query()->pluck('enabled', 'key')->all();
        $menuItems = [
            [
                'key' => 'dashboard',
                'label' => __('ui.menu.dashboard'),
                'href' => route('dashboard'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-10h8V3h-8v8Z" fill="currentColor"/></svg>',
                'active' => request()->routeIs('dashboard'),
                'allowed_roles' => [],
            ],
            [
                'key' => 'students',
                'label' => __('ui.menu.students'),
                'href' => route('students.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 1.79-8 4v2h16v-2c0-2.21-3.58-4-8-4Z" fill="currentColor"/></svg>',
                'active' => request()->routeIs('students.*'),
                'allowed_roles' => ['teacher', 'director', 'super_admin'],
            ],
            [
                'key' => 'classes',
                'label' => __('ui.menu.classes'),
                'href' => route('classes.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v10H4z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M2 19h20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 9h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                'active' => request()->routeIs('classes.*'),
                'allowed_roles' => ['teacher', 'director', 'super_admin'],
            ],
            [
                'key' => 'kitchen',
                'label' => __('ui.menu.kitchen'),
                'href' => route('kitchen.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3v8M11 3v8M7 7h4M6 21V11h6v10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M16 3c2.21 0 4 1.79 4 4v14" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>',
                'active' => request()->routeIs('kitchen.*'),
                'allowed_roles' => ['director', 'super_admin'],
            ],
            [
                'key' => 'dishes',
                'label' => __('ui.menu.dishes'),
                'href' => route('dishes.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M7 12h10M9 18h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="2"/></svg>',
                'active' => request()->routeIs('dishes.*'),
                'allowed_roles' => ['director', 'super_admin'],
            ],
            [
                'key' => 'orders',
                'label' => __('ui.menu.orders'),
                'href' => route('orders.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10l1 5H6l1-5Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M6 9h12v10a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V9Z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M9 13h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                'active' => request()->routeIs('orders.*'),
                'allowed_roles' => ['teacher', 'director', 'super_admin'],
            ],
            [
                'key' => 'library',
                'label' => __('ui.menu.library'),
                'href' => route('library.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h4v16H5zM10 4h4v16h-4zM15 4h4v16h-4z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4 20h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                'active' => request()->routeIs('library.*'),
                'allowed_roles' => ['teacher', 'director', 'super_admin'],
            ],
            [
                'key' => 'reports',
                'label' => __('ui.menu.reports'),
                'href' => route('reports.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18H5z" fill="none" stroke="currentColor" stroke-width="2"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                'active' => request()->routeIs('reports.*'),
                'allowed_roles' => ['director', 'district_operator', 'region_operator', 'super_admin'],
            ],
            [
                'key' => 'devices',
                'label' => __('ui.menu.devices'),
                'href' => route('devices.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M10 19h4M8 15h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
                'active' => request()->routeIs('devices.*'),
                'allowed_roles' => ['support_admin', 'super_admin'],
            ],
            [
                'key' => 'support',
                'label' => __('ui.menu.support'),
                'href' => route('support.index'),
                'icon' =>
                    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a7 7 0 0 0-7 7v4a2 2 0 0 0 2 2h1v-6H7a5 5 0 1 1 10 0h-1v6h1a2 2 0 0 0 2-2v-4a7 7 0 0 0-7-7Z" fill="currentColor"/><path d="M10 18a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>',
                'active' => request()->routeIs('support.*'),
                'allowed_roles' => ['support_admin', 'super_admin'],
            ],
        ];

        $visibleMenuItems = array_values(
            array_filter($menuItems, function (array $item) use ($roleCodes, $canManageMenu, $enabledMenuItems): bool {
                if (($enabledMenuItems[$item['key']] ?? true) !== true) {
                    return false;
                }

                if ($canManageMenu) {
                    return true;
                }

                if ($item['allowed_roles'] === []) {
                    return true;
                }

                return count(array_intersect($item['allowed_roles'], $roleCodes)) > 0;
            }),
        );
    @endphp

    <div class="sidebar-header">
        <button class="sidebar-toggle" type="button" id="sidebar-toggle" aria-controls="app-sidebar" aria-expanded="true">
            <span class="sidebar-toggle-icon" aria-hidden="true">☰</span>
            <span class="sidebar-toggle-label">{{ __('ui.menu.collapse') }}</span>
        </button>
    </div>

    <div class="profile">
        <div class="profile-name">{{ $user->full_name ?: __('ui.dashboard.user_fallback') }}</div>
        <div class="muted profile-subtitle">{{ __('ui.common.web_cabinet') }}</div>
    </div>

    <nav class="nav nav-menu">
        @foreach ($visibleMenuItems as $item)
            <a href="{{ $item['href'] }}" @class(['active' => $item['active']]) title="{{ $item['label'] }}">
                <span class="nav-icon">{!! $item['icon'] !!}</span>
                <span class="nav-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($canManageMenu)
        <div class="sidebar-note">
            {{ __('ui.menu.role_visibility_hint') }}
        </div>
    @endif
</aside>
